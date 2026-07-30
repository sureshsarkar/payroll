<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\UserMembership;
use App\Services\MembershipService;
use App\Traits\GetGlobalInformationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Razorpay\Api\Api;

/**
 * Mobile-app companion to the web Membership flow.
 *
 * Mirrors Frontend\MembershipController + MembershipPaymentController exactly
 * — same MembershipService for the activation side-effects (wallet debit,
 * referral reward minting), same Razorpay order shape (so the mobile
 * Checkout SDK and the web Hosted form behave identically).
 *
 * Routes (all auth:sanctum):
 *   GET  /api/membership/plans                  — list active plans for user role
 *   GET  /api/membership/quote/{plan}           — price preview with + without wallet
 *   POST /api/membership/create-order/{plan}    — start a pending UserMembership + Razorpay order
 *   POST /api/membership/verify/{membership}    — verify signature, capture, activate
 */
class MembershipController extends Controller
{
    use GetGlobalInformationTrait;

    public function __construct(private MembershipService $service) {}

    public function plans(Request $request): JsonResponse
    {
        $user  = $request->user();
        $role  = $user->role ?? 'student';
        $plans = MembershipPlan::activeForRoleCached($role);

        $current = $this->service->currentFor($user);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'currency' => Cache::get('setting')?->currency_code ?? 'INR',
                'role'     => $role,
                'plans'    => $plans->map(fn ($p) => $this->shapePlan($p))->values(),
                'current'  => $current ? $this->shapeMembership($current) : null,
            ],
        ]);
    }

    public function quote(Request $request, int $planId): JsonResponse
    {
        $user = $request->user();
        $plan = MembershipPlan::active()->forRole($user->role ?? 'student')->find($planId);
        if (!$plan) {
            return response()->json(['status' => 'error', 'message' => 'Plan not available'], 404);
        }

        $with    = $this->service->quote($plan, $user, true);
        $without = $this->service->quote($plan, $user, false);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'plan'           => $this->shapePlan($plan),
                'currency'       => Cache::get('setting')?->currency_code ?? 'INR',
                'wallet_balance' => (float) ($user->referral_wallet_balance ?? 0),
                'with_wallet'    => $with,
                'without_wallet' => $without,
            ],
        ]);
    }

    /**
     * Step 1: server creates a pending UserMembership + Razorpay order.
     * Client receives `order_id` + `amount_in_subunits` + `razorpay_key`
     * and hands those to the in-app Checkout SDK.
     *
     * If wallet credit covers the entire price, no Razorpay order is
     * needed — we activate immediately and return the activated row.
     */
    public function createOrder(Request $request, int $planId): JsonResponse
    {
        $user = $request->user();
        $plan = MembershipPlan::active()->forRole($user->role ?? 'student')->find($planId);
        if (!$plan) {
            return response()->json(['status' => 'error', 'message' => 'Plan not available'], 404);
        }

        $request->validate(['apply_wallet' => 'nullable|boolean']);
        $applyWallet = (bool) $request->input('apply_wallet', true);

        // startCheckout debits wallet credit + creates the pending row.
        // One-time-trial enforcement: a duplicate trial claim throws → 403 JSON.
        try {
            $membership = $this->service->startCheckout(
                user: $user,
                plan: $plan,
                applyWalletCredit: $applyWallet,
                paymentMethod: 'razorpay',
            );
        } catch (\App\Exceptions\TrialAlreadyUsedException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }

        // Wallet covered everything → activated already, no payment needed.
        if ($membership->status === 'active') {
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'fully_paid_by_wallet' => true,
                    'membership'           => $this->shapeMembership($membership->fresh('plan')),
                ],
            ]);
        }

        // Otherwise we need a Razorpay order for the cash portion.
        $cashAmount = (float) $plan->price - (float) $membership->wallet_credit_used;
        $creds = $this->resolveRazorpayCredentials();
        if (empty($creds['key']) || empty($creds['secret'])) {
            // Roll back the pending row — Razorpay isn't usable so we don't
            // want to leak a stuck pending UserMembership.
            $membership->update(['status' => 'cancelled']);
            return response()->json([
                'status'  => 'error',
                'message' => 'Razorpay is not configured. Contact support.',
            ], 503);
        }

        $amountInSubunits = (int) round($cashAmount * 100);
        $currency = Cache::get('setting')?->currency_code ?? 'INR';

        try {
            $api = new Api($creds['key'], $creds['secret']);
            $rzpOrder = $api->order->create([
                'receipt'  => 'mbm-' . $membership->id,
                'amount'   => $amountInSubunits,
                'currency' => $currency,
                'notes'    => [
                    'membership_id' => (string) $membership->id,
                    'user_id'       => (string) $membership->user_id,
                    'source'        => 'android',
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('API Razorpay order create failed: ' . $e->getMessage());
            $membership->update(['status' => 'cancelled']);
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not initiate payment. Try again later.',
            ], 502);
        }

        // Stash Razorpay order id so verify() can cross-check.
        $membership->transaction_id = $rzpOrder['id'];
        $membership->save();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'fully_paid_by_wallet' => false,
                'membership_id'        => $membership->id,
                'razorpay_key'         => $creds['key'], // public key — safe to ship
                'order_id'             => $rzpOrder['id'],
                'amount_in_subunits'   => $amountInSubunits,
                'currency'             => $currency,
                'plan_name'            => $plan->name,
                // Pre-fill payload for the SDK Checkout dialog.
                'prefill' => [
                    'name'    => $user->name,
                    'email'   => $user->email,
                    'contact' => (string) ($user->phone ?? ''),
                ],
            ],
        ]);
    }

    /**
     * Step 2: client posts the Razorpay payment_id + signature it received
     * from the SDK. We verify, capture, then call MembershipService::activate
     * which fires the referral reward hook.
     */
    public function verify(Request $request, int $membershipId): JsonResponse
    {
        $membership = UserMembership::where('user_id', $request->user()->id)->find($membershipId);
        if (!$membership) {
            return response()->json(['status' => 'error', 'message' => 'Membership not found'], 404);
        }

        $request->validate([
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_order_id'   => ['required', 'string'],
            'razorpay_signature'  => ['required', 'string'],
        ]);

        // Defensive: the order id must match what we stashed during createOrder.
        if ($request->razorpay_order_id !== $membership->transaction_id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Order mismatch for this membership.',
            ], 422);
        }

        $creds = $this->resolveRazorpayCredentials();
        try {
            $api = new Api($creds['key'], $creds['secret']);

            $api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
            ]);

            $payment = $api->payment->fetch($request->razorpay_payment_id);
            if ($payment->status !== 'captured') {
                $payment->capture(['amount' => $payment->amount]);
            }

            $cashPaid = (float) $payment->amount / 100;
            $activated = $this->service->activate(
                membership: $membership,
                paidAmount: $cashPaid,
                transactionId: $payment->id,
            );

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'membership' => $this->shapeMembership($activated->fresh('plan')),
                ],
                'message' => 'Payment successful — membership activated.',
            ]);

        } catch (\Throwable $e) {
            \Log::error("API Razorpay verify failed for membership #{$membership->id}: " . $e->getMessage());
            $membership->update(['payment_status' => 'failed']);
            return response()->json([
                'status'  => 'error',
                'message' => 'Payment verification failed. Contact support if you were charged.',
            ], 422);
        }
    }

    /* ─── Shaping helpers ──────────────────────────────────────────────── */

    private function shapePlan(MembershipPlan $p): array
    {
        return [
            'id'           => $p->id,
            'name'         => $p->name,
            'role'         => $p->role,
            'price'        => (float) $p->price,
            'currency'     => Cache::get('setting')?->currency_code ?? 'INR',
            'duration_days'=> (int) ($p->duration_days ?? 0),
            'description'  => (string) ($p->description ?? ''),
            // benefits is usually a JSON / array column; cast safely.
            'features'     => $this->parseFeatures($p),
        ];
    }

    private function parseFeatures(MembershipPlan $p): array
    {
        $raw = $p->features ?? $p->benefits ?? null;
        if (is_array($raw)) return array_values(array_filter(array_map('strval', $raw)));
        if (is_string($raw) && $raw !== '') {
            // Try JSON, fall back to newline-split.
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return array_values(array_filter(array_map('strval', $decoded)));
            return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw))));
        }
        return [];
    }

    private function shapeMembership(UserMembership $m): array
    {
        return [
            'id'                 => $m->id,
            'plan_id'            => $m->plan_id,
            'plan_name'          => $m->plan?->name,
            'status'             => $m->status,
            'payment_status'     => $m->payment_status,
            'payment_method'     => $m->payment_method,
            'price_paid'         => (float) $m->price_paid,
            'wallet_credit_used' => (float) $m->wallet_credit_used,
            'transaction_id'     => $m->transaction_id,
            'started_at'         => $m->started_at?->toIso8601String(),
            'expires_at'         => $m->expires_at?->toIso8601String(),
            'is_trial'           => $m->payment_method === 'trial',
        ];
    }

    private function resolveRazorpayCredentials(): array
    {
        $info = $this->get_payment_gateway_info();
        return [
            'key'    => $info->razorpay_key ?? '',
            'secret' => $info->razorpay_secret ?? '',
        ];
    }
}
