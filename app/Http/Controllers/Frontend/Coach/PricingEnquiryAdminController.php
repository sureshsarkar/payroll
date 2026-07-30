<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachPricingEnquiry;
use Illuminate\Http\Request;

/**
 * Coach-panel view of Pricing & Plans leads. Every query is scoped to the
 * owning coach (instructor = self, staff = their coach_id), so a coach only
 * ever sees / manages their own enquiries.
 */
class PricingEnquiryAdminController extends Controller
{
    private function coachId(): int
    {
        return userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
    }

    public function index(Request $request)
    {
        $coachId = $this->coachId();
        $search  = trim((string) $request->get('search'));
        $status  = $request->get('status');
        $payment = $request->get('payment');   // payment_status filter
        $class   = trim((string) $request->get('class'));    // schedule_id
        $trainer = trim((string) $request->get('trainer'));  // trainer_id
        $plan    = trim((string) $request->get('plan'));     // category / plan type
        $course  = trim((string) $request->get('course'));   // course_type
        $from    = $request->get('from');                    // booking date range
        $to      = $request->get('to');

        $enquiries = CoachPricingEnquiry::forCoach($coachId)
            ->with('payments')   // effectivePayment() → Gateway / Txn / Paid date (no N+1)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('mobile', 'like', "%{$search}%")
                      ->orWhere('trainer_id', 'like', "%{$search}%")   // trainer
                      ->orWhere('schedule_id', 'like', "%{$search}%"); // class
                });
            })
            ->when(in_array($status, ['new', 'contacted', 'converted', 'closed'], true), fn ($q) => $q->where('status', $status))
            ->when(in_array($payment, ['unpaid', 'pending', 'paid', 'failed', 'cancelled'], true), fn ($q) => $q->where('payment_status', $payment))
            ->when($class !== '',   fn ($q) => $q->where('schedule_id', $class))
            ->when($trainer !== '', fn ($q) => $q->where('trainer_id', $trainer))
            ->when($plan !== '',    fn ($q) => $q->where('category', $plan))
            ->when($course !== '',  fn ($q) => $q->where('course_type', $course))
            ->when($from,           fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,             fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Coach-scoped distinct option lists for the filter dropdowns.
        $opt = fn (string $col) => CoachPricingEnquiry::forCoach($coachId)
            ->whereNotNull($col)->where($col, '!=', '')->distinct()->orderBy($col)->pluck($col)->take(100)->values();
        $classOpts   = $opt('schedule_id');
        $trainerOpts = $opt('trainer_id');
        $planOpts    = $opt('category');
        $courseOpts  = $opt('course_type');

        $counts = [
            'all'       => CoachPricingEnquiry::forCoach($coachId)->count(),
            'new'       => CoachPricingEnquiry::forCoach($coachId)->where('status', 'new')->count(),
        ];

        return view('frontend.instructor-dashboard.pricing-enquiries.index', compact(
            'enquiries', 'search', 'status', 'payment', 'class', 'trainer', 'plan', 'course', 'from', 'to',
            'classOpts', 'trainerOpts', 'planOpts', 'courseOpts', 'counts'
        ));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:new,contacted,converted,closed']);
        $enq = CoachPricingEnquiry::forCoach($this->coachId())->findOrFail($id);
        $enq->update(['status' => $request->status]);

        return redirect()->back()->with(['messege' => __('Status updated.'), 'alert-type' => 'success']);
    }

    public function destroy($id)
    {
        CoachPricingEnquiry::forCoach($this->coachId())->findOrFail($id)->delete();

        return redirect()->back()->with(['messege' => __('Enquiry deleted.'), 'alert-type' => 'success']);
    }
}
