<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachLandingPage;
use App\Models\CoachTrainer;
use App\Models\TrainerSessionPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Coach-panel trainer + session-package management (2026-07-15, Phase 1).
 * Every query is coach-scoped (instructor = self, staff = their coach_id) so a
 * coach only ever manages their own trainers/packages. Powers the public
 * Trainer Detail Page + the "Book Personal Class Session" flow.
 */
class TrainerController extends Controller
{
    protected $pageName = 'trainers';

    private function coachId(): int
    {
        return userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
    }

    private function trainerFor(int $id): CoachTrainer
    {
        return CoachTrainer::forCoach($this->coachId())->findOrFail($id);
    }

    public function index(Request $request)
    {
        checkPermission($this->pageName);
        $coachId  = $this->coachId();
        $trainers = CoachTrainer::forCoach($coachId)->withCount('packages')
            ->orderBy('sort_order')->orderBy('id')->get();

        return view('frontend.instructor-dashboard.trainers.index', compact('trainers'));
    }

    public function store(Request $request)
    {
        checkPermission($this->pageName, 'create');
        $coachId = $this->coachId();
        $data = $this->validateTrainer($request);

        $data['coach_id']   = $coachId;
        $data['slug']       = CoachTrainer::uniqueSlug($coachId, $data['name']);
        $data['website_id'] = CoachLandingPage::where('added_by', $coachId)->value('id');
        $data['sort_order'] = (int) CoachTrainer::forCoach($coachId)->max('sort_order') + 1;
        $data['tags']       = $this->parseTags($request->input('tags'));
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('coach-trainers/' . $coachId, 'public');
        }

        $trainer = CoachTrainer::create($data);
        \App\Services\ActivityLogger::log(\App\Models\ActivityLog::CREATED, 'trainer', $trainer, null, ['name' => $trainer->name], 'Trainer "' . $trainer->name . '" created');

        return redirect()->route('instructor.trainers.edit', $trainer->id)->with(['messege' => __('Trainer added.'), 'alert-type' => 'success']);
    }

    public function edit(int $id)
    {
        checkPermission($this->pageName);
        $trainer  = $this->trainerFor($id);
        $packages = $trainer->packages()->orderBy('sort_order')->orderBy('id')->get();

        return view('frontend.instructor-dashboard.trainers.edit', compact('trainer', 'packages'));
    }

    public function update(Request $request, int $id)
    {
        checkPermission($this->pageName, 'update');
        $trainer = $this->trainerFor($id);
        $data = $this->validateTrainer($request);
        $data['tags'] = $this->parseTags($request->input('tags'));
        if ($request->boolean('regenerate_slug') || $trainer->name !== $data['name']) {
            $data['slug'] = CoachTrainer::uniqueSlug($this->coachId(), $data['name'], $trainer->id);
        }
        if ($request->hasFile('photo')) {
            if ($trainer->photo) {
                Storage::disk('public')->delete($trainer->photo);
            }
            $data['photo'] = $request->file('photo')->store('coach-trainers/' . $this->coachId(), 'public');
        }

        $trainer->update($data);
        \App\Services\ActivityLogger::log(\App\Models\ActivityLog::UPDATED, 'trainer', $trainer, null, ['name' => $trainer->name], 'Trainer "' . $trainer->name . '" updated');

        return redirect()->back()->with(['messege' => __('Trainer updated.'), 'alert-type' => 'success']);
    }

    public function toggle(int $id)
    {
        checkPermission($this->pageName, 'update');
        $trainer = $this->trainerFor($id);
        $trainer->update(['is_active' => ! $trainer->is_active]);

        return redirect()->back()->with(['messege' => __('Trainer visibility updated.'), 'alert-type' => 'success']);
    }

    public function destroy(int $id)
    {
        checkPermission($this->pageName, 'delete');
        $trainer = $this->trainerFor($id);
        if ($trainer->photo) {
            Storage::disk('public')->delete($trainer->photo);
        }
        \App\Services\ActivityLogger::log(\App\Models\ActivityLog::DELETED, 'trainer', $trainer, ['name' => $trainer->name], null, 'Trainer "' . $trainer->name . '" deleted');
        $trainer->delete();   // cascades packages

        return redirect()->route('instructor.trainers.index')->with(['messege' => __('Trainer deleted.'), 'alert-type' => 'success']);
    }

    /* ───────────────────────── trainer bookings ───────────────────────── */

    /**
     * Dedicated coach-panel list of "Book Personal Class Session" bookings —
     * ONLY enquiry_type = trainer_personal_session (kept separate from the mixed
     * Pricing Enquiries list). Tenant-scoped, server-side filters + KPIs.
     */
    public function bookings(Request $request)
    {
        checkPermission($this->pageName);
        $coachId = $this->coachId();

        $base = fn () => \App\Models\CoachPricingEnquiry::forCoach($coachId)
            ->where('enquiry_type', \App\Models\CoachPricingEnquiry::TYPE_TRAINER_SESSION);

        $search    = trim((string) $request->get('search'));
        $trainerId = (int) $request->get('trainer_id');
        $packageId = (int) $request->get('package_id');
        $payment   = $request->get('payment');
        $from      = $request->get('from');
        $to        = $request->get('to');

        $enquiries = $base()
            ->with('payments')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('mobile', 'like', "%{$search}%");
                });
            })
            ->when($trainerId > 0, fn ($q) => $q->where('trainer_ref_id', $trainerId))
            ->when($packageId > 0, fn ($q) => $q->where('trainer_package_id', $packageId))
            ->when(in_array($payment, ['unpaid', 'pending', 'paid', 'failed', 'cancelled'], true), fn ($q) => $q->where('payment_status', $payment))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Coach-scoped filter option lists.
        $trainerOpts = CoachTrainer::forCoach($coachId)->orderBy('name')->get(['id', 'name']);
        $packageOpts = TrainerSessionPackage::forCoach($coachId)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'sessions', 'validity_value', 'validity_unit', 'price', 'currency']);

        $kpis = [
            'total'     => (int) $base()->count(),
            'paid'      => (int) $base()->where('payment_status', \App\Models\CoachPricingEnquiry::PAY_PAID)->count(),
            'pending'   => (int) $base()->where('payment_status', \App\Models\CoachPricingEnquiry::PAY_PENDING)->count(),
            'collected' => (float) $base()->where('payment_status', \App\Models\CoachPricingEnquiry::PAY_PAID)->sum('paid_amount'),
        ];

        return view('frontend.instructor-dashboard.trainers.bookings', compact(
            'enquiries', 'search', 'trainerId', 'packageId', 'payment', 'from', 'to',
            'trainerOpts', 'packageOpts', 'kpis'
        ));
    }

    /** Update the follow-up status of a trainer booking (tenant + type gated). */
    public function updateBookingStatus(Request $request, int $id)
    {
        checkPermission($this->pageName, 'update');
        $request->validate(['status' => 'required|in:new,contacted,converted,closed']);
        $this->trainerBookingFor($id)->update(['status' => $request->status]);

        return redirect()->back()->with(['messege' => __('Booking status updated.'), 'alert-type' => 'success']);
    }

    /** Delete a trainer booking enquiry (tenant + type gated). */
    public function destroyBooking(int $id)
    {
        checkPermission($this->pageName, 'delete');
        $this->trainerBookingFor($id)->delete();

        return redirect()->back()->with(['messege' => __('Booking deleted.'), 'alert-type' => 'success']);
    }

    /** Tenant + discriminator gate for a single trainer booking. */
    private function trainerBookingFor(int $id): \App\Models\CoachPricingEnquiry
    {
        return \App\Models\CoachPricingEnquiry::forCoach($this->coachId())
            ->where('enquiry_type', \App\Models\CoachPricingEnquiry::TYPE_TRAINER_SESSION)
            ->findOrFail($id);
    }

    /* ───────────────────────── session packages ───────────────────────── */

    public function storePackage(Request $request, int $trainerId)
    {
        checkPermission($this->pageName, 'update');
        $trainer = $this->trainerFor($trainerId);
        $data = $this->validatePackage($request);
        $data['trainer_id'] = $trainer->id;
        $data['coach_id']   = $this->coachId();
        $data['sort_order'] = (int) $trainer->packages()->max('sort_order') + 1;

        $pkg = TrainerSessionPackage::create($data);
        \App\Services\ActivityLogger::log(\App\Models\ActivityLog::CREATED, 'trainer_package', $pkg, null, ['sessions' => $pkg->sessions, 'price' => $pkg->price], 'Package for trainer #' . $trainer->id . ' created');

        return redirect()->back()->with(['messege' => __('Package added.'), 'alert-type' => 'success']);
    }

    public function updatePackage(Request $request, int $trainerId, int $packageId)
    {
        checkPermission($this->pageName, 'update');
        $trainer = $this->trainerFor($trainerId);
        $pkg = $trainer->packages()->findOrFail($packageId);
        $prev = ['price' => $pkg->price];
        $pkg->update($this->validatePackage($request));
        if ((string) $prev['price'] !== (string) $pkg->price) {
            \App\Services\ActivityLogger::log(\App\Models\ActivityLog::PLAN_CHANGED, 'trainer_package', $pkg, $prev, ['price' => $pkg->price], 'Package #' . $pkg->id . ' price changed');
        }

        return redirect()->back()->with(['messege' => __('Package updated.'), 'alert-type' => 'success']);
    }

    public function togglePackage(int $trainerId, int $packageId)
    {
        checkPermission($this->pageName, 'update');
        $trainer = $this->trainerFor($trainerId);
        $pkg = $trainer->packages()->findOrFail($packageId);
        $pkg->update(['is_active' => ! $pkg->is_active]);

        return redirect()->back()->with(['messege' => __('Package updated.'), 'alert-type' => 'success']);
    }

    public function destroyPackage(int $trainerId, int $packageId)
    {
        checkPermission($this->pageName, 'delete');
        $trainer = $this->trainerFor($trainerId);
        $trainer->packages()->findOrFail($packageId)->delete();

        return redirect()->back()->with(['messege' => __('Package deleted.'), 'alert-type' => 'success']);
    }

    /* ───────────────────────────── helpers ────────────────────────────── */

    private function validateTrainer(Request $request): array
    {
        $data = $request->validate([
            'name'               => 'required|string|max:150',
            'specialisation'     => 'nullable|string|max:190',
            'experience'         => 'nullable|string|max:60',
            'certificate_date'   => 'nullable|string|max:60',
            'certificate_number' => 'nullable|string|max:100',
            'bio'                => 'nullable|string|max:2000',
            'is_active'          => 'nullable|boolean',
            'photo'              => 'nullable|image|max:4096',
            // Booking-form taxonomy (comma-separated in the form).
            'plan_types'         => 'nullable|string|max:500',
            'course_types'       => 'nullable|string|max:500',
            'reasons'            => 'nullable|string|max:500',
        ]);

        $data['is_active']    = $request->boolean('is_active', true);
        $data['plan_types']   = $this->parseTags($request->input('plan_types'));
        $data['course_types'] = $this->parseTags($request->input('course_types'));
        $data['reasons']      = $this->parseTags($request->input('reasons'));

        return $data;
    }

    private function validatePackage(Request $request): array
    {
        return $request->validate([
            'name'           => 'nullable|string|max:120',
            'sessions'       => 'required|integer|min:1|max:1000',
            'validity_value' => 'required|integer|min:1|max:3650',
            'validity_unit'  => 'required|in:days,weeks,months',
            'price'          => 'required|numeric|min:0|max:99999999',
            'currency'       => 'nullable|string|max:8',
        ]) + ['is_active' => $request->boolean('is_active', true), 'currency' => $request->input('currency') ?: 'INR'];
    }

    private function parseTags($raw): ?array
    {
        if (! $raw) {
            return null;
        }
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
        return $tags ?: null;
    }
}
