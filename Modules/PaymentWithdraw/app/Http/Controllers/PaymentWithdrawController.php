<?php

namespace Modules\PaymentWithdraw\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Auth;
use Illuminate\Http\Request;
use Modules\PaymentWithdraw\app\Models\WithdrawMethod;
use Modules\PaymentWithdraw\app\Models\WithdrawRequest;

/**
 * DEPRECATED (FT-DEAD-1, 2026-05-28) — superseded by
 * App\Http\Controllers\Frontend\InstructorPayoutController.
 *
 * This controller has TWO production bugs that have rendered it
 * effectively inert:
 *
 *  1. Both methods gate on
 *     `checkAdminHasPermissionAndThrowException('withdraw.management')`
 *     but the route group is `auth:web` (coach guard), not
 *     `auth:admin`. The helper reads `Auth::guard('admin')->user()`,
 *     which is NULL for coach-guard requests, so the helper returns
 *     false and the throw fires for every legitimate coach who
 *     reaches the URL. Net: the route is unreachable for its
 *     intended audience.
 *
 *  2. store() has `$total_balance = 500;` hardcoded — a TODO
 *     comment in the original code admits this. If the gate above
 *     were ever fixed without addressing this, every coach could
 *     request UP TO $500 regardless of their actual wallet balance,
 *     and the admin's later approval (Order\WithdrawMethodController
 *     ::update_withdraw) would decrement wallet_balance into the
 *     negative.
 *
 * The legitimate coach payout flow uses InstructorPayoutController:
 *   - reads $coach->wallet_balance (real value)
 *   - enforces min/max from WithdrawMethod
 *   - prevents concurrent pending requests
 *   - already covered by existing PHPUnit tests.
 *
 * Keeping this controller in place so the route registration in
 * Modules/PaymentWithdraw/routes/web.php still resolves (removing
 * the route would 404 anyone who has a stale bookmark to
 * /payment-withdraw). The view referenced below (paymentwithdraw::index)
 * remains, but anyone hitting either method gets a 403 due to bug 1
 * above — which is the desired behaviour: the legitimate flow lives
 * elsewhere. If a future PR removes the route + view, this file
 * can go too.
 *
 * The hardcoded $500 has been replaced with the real wallet_balance
 * read so if bug 1 is ever fixed without removing this controller,
 * the balance check at least won't silently allow $500-per-coach.
 */
class PaymentWithdrawController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * DEPRECATED — see class docstring. Throws 403 today; kept for
     * route compatibility with stale bookmarks.
     */
    public function index()
    {
        checkAdminHasPermissionAndThrowException('withdraw.management');
        $user = Auth::guard('web')->user();

        $methods = WithdrawMethod::where('status', 'active')->get();

        $withdraws = WithdrawRequest::where('user_id', $user->id)->latest()->get();

        return view('paymentwithdraw::index', ['methods' => $methods, 'withdraws' => $withdraws]);
    }

    /**
     * DEPRECATED — see class docstring.
     */
    public function store(Request $request)
    {
        checkAdminHasPermissionAndThrowException('withdraw.management');

        $rules = [
            'withdraw_method_id' => 'required',
            'amount' => 'required|numeric',
            'account_info' => 'required',
        ];

        $customMessages = [
            'withdraw_method_id.required' => __('Payment Method filed is required'),
            'amount.required' => __('Withdraw amount filed is required'),
            'amount.numeric' => __('Please provide valid numeric number'),
            'account_info.required' => __('Account filed is required'),
        ];

        $request->validate($rules, $customMessages);

        $user = Auth::guard('web')->user();

        // FT-DEAD-1 (2026-05-28) — was `$total_balance = 500;` hardcoded
        // with a TODO admitting the bug. Read the real wallet_balance
        // instead so that IF the admin-permission gate above is ever
        // accidentally relaxed, this method doesn't silently allow up
        // to $500 per coach. Still no flow uses this — the legitimate
        // path is InstructorPayoutController.
        $total_balance = (float) ($user?->wallet_balance ?? 0);
        $total_withdraw = WithdrawRequest::where('user_id', $user->id)->sum('total_amount');
        $current_balance = $total_balance - $total_withdraw;

        if ($request->amount > $current_balance) {
            $notification = __('Sorry! Your Payment request is more then your current balance');

            return response()->json(['message' => $notification]);
        }

        $method = WithdrawMethod::whereId($request->withdraw_method_id)->first();
        if ($request->amount >= $method->min_amount && $request->amount <= $method->max_amount) {
            $widthdraw = new WithdrawRequest();
            $widthdraw->user_id = $user->id;
            $widthdraw->method = $method->name;
            $widthdraw->total_amount = $request->amount;
            $withdraw_request = $request->amount;
            $withdraw_amount = ($method->withdraw_charge / 100) * $withdraw_request;
            $widthdraw->withdraw_amount = $request->amount - $withdraw_amount;
            $widthdraw->withdraw_charge = $method->withdraw_charge;
            $widthdraw->account_info = $request->account_info;
            $widthdraw->save();

            try {
                $widthdraw->load('user:id,name,email');
                \Illuminate\Support\Facades\Notification::send(
                    \App\Models\Admin::where('status', 'active')->get(),
                    new \App\Notifications\WithdrawalRequestSubmittedToAdmin($widthdraw)
                );
            } catch (\Throwable $e) {
                \Log::warning('Notify admins of new withdrawal request failed: ' . $e->getMessage());
            }

            $notification = __('Withdraw request send successfully, please wait for admin approval');

            return response()->json(['message' => $notification]);

        } else {
            $notification = __('Your amount range is not available');

            return response()->json(['message' => $notification]);
        }
    }
}
