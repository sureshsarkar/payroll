<?php

namespace Modules\InstructorRequest\app\Http\Controllers;

use App\Enums\RedirectType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\RedirectHelperTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\InstructorRequest\app\Models\InstructorRequest;
use Modules\InstructorRequest\app\Services\EmailService;

class InstructorRequestController extends Controller
{
    use RedirectHelperTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('instructor.request.list');
        $query = InstructorRequest::query();
        $query->with(['user']);
        $query->when($request->keyword, function($q) use ($request) {
            $q->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->keyword}%")
                    ->orWhere('email', 'like', "%{$request->keyword}%")
                    ->orWhere('phone', 'like', "%{$request->keyword}%");
            });
        });
        $query->when($request->status, fn ($q) => $q->where('status', $request->status));
        $orderBy = $request->order_by == 1 ? 'asc' : 'desc';
        $instructorRequests = $request->get('par-page') == 'all' ?
            $query->orderBy('id', $orderBy)->get() :
            $query->orderBy('id', $orderBy)->paginate($request->get('par-page') ?? null)->withQueryString();
        return view('instructorrequest::instructor-request.index', compact('instructorRequests'));
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        checkAdminHasPermissionAndThrowException('instructor.request.list');

        $instructorRequest = InstructorRequest::findOrFail($id);
        $user = User::find($instructorRequest->user_id);
        // Orphaned request (the applicant's account was deleted) — the detail
        // view dereferences $user throughout, so bail out gracefully with a
        // message instead of a 500.
        if (! $user) {
            return redirect()->route('admin.instructor-request.index')
                ->with('error', __("This request's user account no longer exists."));
        }
        return view('instructorrequest::instructor-request.edit', compact('user', 'instructorRequest'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        // FT-IDOR-17 fix (2026-05-28) — was `instructor.request.list`
        // (a READ permission). This method flips status to 'approved'
        // and PROMOTES the underlying user to role='instructor' — a
        // global-tenant privilege escalation. A read-only sub-admin
        // could therefore mint coaches via POST. Gated on the new
        // `instructor.request.update` permission seeded by
        // 2026_05_28_110000_seed_instructor_request_update_permission.
        checkAdminHasPermissionAndThrowException('instructor.request.update');

        $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $instructorRequest = InstructorRequest::findOrFail($id);
        $instructorRequest->status = $request->status;
        $instructorRequest->save();

        $user = User::findOrFail($instructorRequest->user_id);

        if ($request->status === 'approved') {
            $user->role = 'instructor';
            $user->save();
        } elseif ($request->status === 'rejected' && $user->role === 'instructor') {
            // Reverse a previous promotion if the admin changes their mind.
            $user->role = 'student';
            $user->save();
        }

        (new EmailService)->handleInstructorRequestStatusMailSending([
            'user_email' => $instructorRequest->user->email,
            'user_name' => $instructorRequest->user->name,
            'status' => $instructorRequest->status
        ]);

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'admin.instructor-request.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // FT-IDOR-17 fix — destroy is also a write operation and was
        // gated on the READ permission. Use the same write-level
        // slug as update().
        checkAdminHasPermissionAndThrowException('instructor.request.update');

        $request = InstructorRequest::findOrFail($id);
        $request->delete();

        return $this->redirectWithMessage(RedirectType::DELETE->value, 'admin.instructor-request.index');
    }
}
