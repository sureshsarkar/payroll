<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\InstructorRequest\app\Models\InstructorRequest;
use Modules\Order\app\Models\OrderItem;
use Modules\PaymentWithdraw\app\Models\WithdrawMethod;
use Modules\PaymentWithdraw\app\Models\WithdrawRequest;

class InstructorPayoutController extends Controller
{
    /**
     * The coach whose wallet/payouts we're operating on. For a coach this is themselves;
     * for a staff member it's the coach they belong to (so staff can view/request payouts
     * on behalf of the coach without each staff having their own wallet).
     */
    private function effectiveCoachId(): int
    {
        return userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
    }

    private function coach(): User
    {
        return User::findOrFail($this->effectiveCoachId());
    }

    function index()
    {
        $coachId = $this->effectiveCoachId();
        $courseIds = Course::where('instructor_id', $coachId)->pluck('id')->toArray();
        $totalCourseSold = OrderItem::whereIn('course_id', $courseIds)->count();
        $withdrawRequests = WithdrawRequest::where('user_id', $coachId)->orderBy('id', 'desc')->paginate(30);
        $totalWithdraw = WithdrawRequest::where(['user_id' => $coachId, 'status' => 'approved'])->sum('withdraw_amount');
        return view('frontend.instructor-dashboard.payout.index', compact('totalCourseSold', 'withdrawRequests', 'totalWithdraw'));
    }

    /**
     * Enterprise / direct-settlement plans don't use payout requests — earnings
     * settle straight to the coach's bank. Block the request entry points and
     * tell the coach why. (2026-06-24, Phase 3.)
     */
    private function directSettlementRedirect()
    {
        if (! $this->coach()->coachPayoutRequired()) {
            return redirect()->route('instructor.payout.index')->with([
                'alert-type' => 'info',
                'messege'    => __('Your plan uses direct settlement — course earnings are paid straight to your bank account, so no payout request is needed.'),
            ]);
        }
        return null;
    }

    function create()
    {
        if ($r = $this->directSettlementRedirect()) return $r;
        $coachId = $this->effectiveCoachId();
        $courseIds = Course::where('instructor_id', $coachId)->pluck('id')->toArray();
        $totalCourseSold = OrderItem::whereIn('course_id', $courseIds)->count();
        $gateway = InstructorRequest::where('user_id', $coachId)->first();
        $withdrawMethod = WithdrawMethod::where('name', $gateway->payout_account ?? '')->first();
        $totalWithdraw = WithdrawRequest::where(['user_id' => $coachId, 'status' => 'approved'])->sum('withdraw_amount');
        return view('frontend.instructor-dashboard.payout.create', compact('totalCourseSold', 'gateway', 'totalWithdraw', 'withdrawMethod'));
    }

    function store(Request $request)
    {
        if ($r = $this->directSettlementRedirect()) return $r;
        $request->validate([
            'amount' => 'required|numeric',
        ], [
            'amount.required' => 'The amount field is required.',
            'amount.numeric' => 'The amount must be a number.',
        ]);

        $coachId = $this->effectiveCoachId();
        $coach = $this->coach();

        $gateway = InstructorRequest::where('user_id', $coachId)->first();
        if (!$gateway) {
            return redirect()->back()->with(['alert-type' => 'error', 'messege' => __('Payout method not configured. Please complete instructor onboarding first.')]);
        }

        $withdrawMethod = WithdrawMethod::where('name', $gateway->payout_account)->first();
        if (!$withdrawMethod) {
            return redirect()->back()->with(['alert-type' => 'error', 'messege' => __('Payout method not found.')]);
        }

        if ($request->amount < 0 || $request->amount > $coach->wallet_balance) {
            return redirect()->back()->with(['alert-type' => 'error', 'messege' => __('Invalid amount')]);
        } elseif ($withdrawMethod->min_amount > $request->amount) {
            return redirect()->back()->with(['alert-type' => 'error', 'messege' => __('Minimum payout amount is :amount', ['amount' => $withdrawMethod->min_amount])]);
        } elseif ($withdrawMethod->max_amount < $request->amount) {
            return redirect()->back()->with(['alert-type' => 'error', 'messege' => __('Maximum payout amount is :amount', ['amount' => $withdrawMethod->max_amount])]);
        } elseif (WithdrawRequest::where('user_id', $coachId)->where('status', 'pending')->exists()) {
            return redirect()->back()->with(['alert-type' => 'error', 'messege' => __('You already have a pending payout request.')]);
        }

        WithdrawRequest::create([
            'user_id' => $coachId,
            'withdraw_amount' => $request->amount,
            'method' => $gateway->payout_account,
            'account_info' => $gateway->payout_information,
            'status' => 'pending',
            'current_amount' => $coach->wallet_balance,
        ]);

        return redirect()->route('instructor.payout.index')->with(['alert-type' => 'success', 'messege' => __('Payout request sent successfully.')]);
    }

    function destroy(string $id)
    {
        $coachId = $this->effectiveCoachId();
        $request = WithdrawRequest::where('id', $id)->where(['user_id' => $coachId, 'status' => 'pending'])->first();

        if ($request) {
            $request->delete();
            return response(['status' => 'success', 'message' => __('Deleted Successfully')]);
        }
        return response(['status' => 'success', 'message' => __('Request not found')]);
    }
}
