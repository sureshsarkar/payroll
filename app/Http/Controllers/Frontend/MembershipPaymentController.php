<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\UserMembership;
use App\Services\MembershipService;
use App\Traits\GetGlobalInformationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Razorpay\Api\Api;

/**
 * Razorpay payment flow for membership purchases.
 *
 * Wallet credit is debited at checkout (in MembershipController::pay → service
 * ::startCheckout), so by the time we land here we only need to charge the
 * remaining cash portion. After Razorpay confirms the payment we hand the
 * UserMembership to MembershipService::activate which fires the referral
 * reward hook.
 */
class MembershipPaymentController extends Controller
{
    use GetGlobalInformationTrait;

    public function __construct(private MembershipService $service) {}

    /**
     * Render the Razorpay checkout for a pending membership row.
     * Reachable as: GET /membership/pay/{membership}/razorpay
     */
    public function showRazorpay(int $membershipId)
    {
        $membership = UserMembership::with('plan')
            ->where('user_id', auth()->id())
            ->where('id', $membershipId)
            ->where('payment_status', 'pending')
            ->firstOrFail();

        // F1 (audit 2026-06-26) — charge the price for the membership's ACTUAL
        // billing period. Previously this always used plan->price (monthly), so
        // an annual checkout was billed one month yet activated for 365 days.
        $cashAmount = $this->expectedCashAmount($membership);
        if ($cashAmount <= 0) {
            // Wallet covered everything — should already be active. Return to index.
            return redirect()->route('membership.index')->with([
                'messege' => __('This membership is already paid in full.'), 'alert-type' => 'info',
            ]);
        }

        $creds = $this->resolveRazorpayCredentials();
        if (empty($creds['key']) || empty($creds['secret'])) {
            return redirect()->route('membership.index')->with([
                'messege' => __('Razorpay is not configured. Please ask the administrator.'), 'alert-type' => 'error',
            ]);
        }

        // Razorpay expects amount in the smallest currency unit (paise / cents).
        // Default settings use the platform currency; we keep it simple and
        // assume 1 main unit = 100 sub-units (true for INR/USD/EUR/GBP).
        $amountInSubunits = (int) round($cashAmount * 100);

        try {
            $api = new Api($creds['key'], $creds['secret']);
            $rzpOrder = $api->order->create([
                'receipt'  => 'mbm-' . $membership->id,
                'amount'   => $amountInSubunits,
                'currency' => Cache::get('setting')?->currency_code ?? 'INR',
                'notes'    => [
                    'membership_id' => (string) $membership->id,
                    'user_id'       => (string) $membership->user_id,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Razorpay order create failed: ' . $e->getMessage());
            return redirect()->route('membership.index')->with([
                'messege' => __('Could not initiate payment. Try again later.'), 'alert-type' => 'error',
            ]);
        }

        $membership->transaction_id = $rzpOrder['id']; // store the Razorpay order id
        $membership->save();

        return view('frontend.membership.razorpay-checkout', [
            'membership'   => $membership,
            'razorpayKey'  => $creds['key'],
            'rzpOrderId'   => $rzpOrder['id'],
            'amount'       => $amountInSubunits,
            'currency'     => Cache::get('setting')?->currency_code ?? 'INR',
            'user'         => auth()->user(),
        ]);
    }

    /**
     * Razorpay redirects here after the user completes/declines payment.
     * Verifies signature, captures the payment, activates the membership.
     *
     * Reachable as: POST /membership/pay/{membership}/razorpay/callback
     */
    public function razorpayCallback(Request $request, int $membershipId)
    {
        $membership = UserMembership::with('plan')->where('user_id', auth()->id())->findOrFail($membershipId);

        $request->validate([
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_order_id'   => ['required', 'string'],
            'razorpay_signature'  => ['required', 'string'],
        ]);

        // F25 (audit 2026-06-26) — bind the callback to THIS membership's own
        // Razorpay order. Without this, a validly-signed payment for a different
        // (cheaper) order created on the same merchant account could be replayed
        // to activate a more expensive membership. Reject BEFORE any verify/
        // activate work and leave the row pending (graceful redirect, no 500).
        if (! hash_equals((string) $membership->transaction_id, (string) $request->razorpay_order_id)) {
            \Log::warning("Razorpay order id mismatch for membership #{$membership->id}");
            return redirect()->route('membership.index')->with([
                'messege'    => __('Payment could not be verified. Please try again.'),
                'alert-type' => 'error',
            ]);
        }

        $creds = $this->resolveRazorpayCredentials();
        try {
            $api = new Api($creds['key'], $creds['secret']);

            // Verify signature first — no chance of replay/forged callbacks.
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
            ]);

            // Fetch + capture the payment.
            $payment = $api->payment->fetch($request->razorpay_payment_id);
            if ($payment->status !== 'captured') {
                $payment->capture(['amount' => $payment->amount]);
            }

            // F11 (audit 2026-06-26) — the captured amount must equal what this
            // membership actually owed (billing-period aware). A signed callback
            // proves authenticity, NOT that the right amount was charged.
            $expectedSubunits = (int) round($this->expectedCashAmount($membership) * 100);
            if ((int) $payment->amount !== $expectedSubunits) {
                throw new \RuntimeException(
                    "Razorpay amount mismatch for membership #{$membership->id}: charged {$payment->amount}, expected {$expectedSubunits}"
                );
            }

            $cashPaid = (float) $payment->amount / 100;

            // Activate — this fires referral reward.
            $this->service->activate($membership, paidAmount: $cashPaid, transactionId: $payment->id);

            return redirect()->route('membership.index')->with([
                'messege'    => __('Payment successful — membership activated.'),
                'alert-type' => 'success',
            ]);

        } catch (\Throwable $e) {
            \Log::error("Razorpay verify/capture failed for membership #{$membership->id}: " . $e->getMessage());
            $membership->update(['payment_status' => 'failed']);
            return redirect()->route('membership.index')->with([
                'messege'    => __('Payment verification failed. Please contact support if the amount was charged.'),
                'alert-type' => 'error',
            ]);
        }
    }

    /**
     * The cash this membership owes Razorpay — billing-period aware (annual uses
     * the plan's annual_price), minus any wallet credit already applied at
     * checkout. Single source of truth shared by the render + callback so the
     * amount charged and the amount validated can never diverge.
     */
    private function expectedCashAmount(UserMembership $membership): float
    {
        $plan = $membership->plan;
        $isAnnual = ($membership->billing_period ?? 'monthly') === 'annual'
            && $plan && method_exists($plan, 'hasAnnual') && $plan->hasAnnual();
        $planPrice = $isAnnual ? (float) $plan->annual_price : (float) ($plan->price ?? 0);

        return max(0, $planPrice - (float) $membership->wallet_credit_used);
    }

    /**
     * Pull Razorpay key/secret from the cached payment_setting bag.
     * Returns ['key' => ..., 'secret' => ...] or empties if not configured.
     */
    private function resolveRazorpayCredentials(): array
    {
        $info = $this->get_payment_gateway_info();
        return [
            'key'    => $info->razorpay_key ?? '',
            'secret' => $info->razorpay_secret ?? '',
        ];
    }
}
