<?php

namespace App\Services\Payment;

use App\Models\CoachPaymentGateway;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Traits\GetGlobalInformationTrait;

/**
 * Resolves WHICH payment gateway configuration should process a given course
 * owner's payment, in strict priority order:
 *
 *   1. coach_self_managed   — the coach is on an ACTIVE Enterprise membership
 *                             AND has an active, fully-configured self row.
 *   2. super_admin_for_coach — the Super-Admin configured an active, fully-
 *                             configured row for this specific coach.
 *   3. super_admin_default   — the existing global gateway (unchanged behaviour).
 *
 * A coach tier is skipped when its row is inactive OR missing required
 * credentials, so a half-configured coach gateway can never break checkout — it
 * transparently falls back to the next tier.
 *
 * Pass $coachId = null/0 to force the global default (used for mixed multi-coach
 * carts, where one charge cannot be split across merchant accounts).
 *
 * The resolver is the single seam for BOTH directions:
 *   - checkout init   → resolve($courseOwnerId, $gateway)
 *   - webhook/callback → resolveForOrder($order) (re-resolves from stored metadata)
 */
class PaymentGatewayResolverService
{
    use GetGlobalInformationTrait;

    public function resolve(?int $coachId, string $gateway): ResolvedGateway
    {
        $gateway = strtolower(trim($gateway));

        if ($coachId && $coachId > 0 && GatewayFieldRegistry::isKnown($gateway)) {
            $coach = User::find($coachId);

            if ($coach) {
                // Tier 1 — coach self-managed (Enterprise only).
                if ($this->coachIsEnterprise($coach)) {
                    $row = $this->activeRow($coachId, $gateway, CoachPaymentGateway::MANAGER_COACH);
                    if ($row && $row->hasUsableCredentials()) {
                        return $this->fromRow($row, ResolvedGateway::OWNER_COACH_SELF);
                    }
                }

                // Tier 2 — Super-Admin configured for this coach (any plan).
                $row = $this->activeRow($coachId, $gateway, CoachPaymentGateway::MANAGER_ADMIN);
                if ($row && $row->hasUsableCredentials()) {
                    return $this->fromRow($row, ResolvedGateway::OWNER_ADMIN_COACH);
                }
            }
        }

        // Tier 3 — global default (current platform behaviour).
        return $this->defaultGateway($gateway);
    }

    /**
     * Re-resolve the gateway that an order was created with, using the metadata
     * persisted on the order (never session state). Used by webhook + callback
     * verification so they validate against the SAME credentials that initiated
     * the payment. Falls back to the global default for legacy/NULL orders.
     */
    public function resolveForOrder(\Modules\Order\app\Models\Order $order): ResolvedGateway
    {
        $gateway   = strtolower((string) $order->payment_method);
        $ownerType = $order->gateway_owner_type ?? ResolvedGateway::OWNER_DEFAULT;

        // Legacy orders (pre-feature) or explicit default → global default.
        if (! GatewayFieldRegistry::isKnown($gateway) || $ownerType === ResolvedGateway::OWNER_DEFAULT) {
            return $this->defaultGateway($gateway);
        }

        $coachId     = (int) ($order->gateway_coach_id ?? 0);
        $managerType = $ownerType === ResolvedGateway::OWNER_COACH_SELF
            ? CoachPaymentGateway::MANAGER_COACH
            : CoachPaymentGateway::MANAGER_ADMIN;

        // Use the EXACT config row recorded on the order. If it was stamped but
        // has since been deleted, fail safe to the global default rather than
        // tier-rematching to a DIFFERENT row — that other row could be a
        // different merchant account, which would verify/capture the payment
        // against credentials that never initiated it. Only fall back to a tier
        // match when NO config id was stamped (legacy/edge orders).
        $row = null;
        if (! empty($order->gateway_config_id)) {
            $row = CoachPaymentGateway::find($order->gateway_config_id);
            if (! $row) {
                return $this->defaultGateway($gateway);
            }
        } elseif ($coachId > 0) {
            $row = $this->activeRow($coachId, $gateway, $managerType);
        }

        if ($row && is_array($row->credentials)) {
            return $this->fromRow($row, $ownerType);
        }

        // Config was deleted/rotated — fail safe to the global default secret so
        // a legitimate webhook isn't silently dropped (still amount-reconciled
        // downstream in markPaid()).
        return $this->defaultGateway($gateway);
    }

    /* --------------------------------------------------------------------- */

    public function coachIsEnterprise(User $coach): bool
    {
        $plan = $coach->activePlan();

        return $plan instanceof MembershipPlan && $plan->isEnterprise();
    }

    private function activeRow(int $coachId, string $gateway, string $managerType): ?CoachPaymentGateway
    {
        return CoachPaymentGateway::query()
            ->where('coach_id', $coachId)
            ->where('gateway', $gateway)
            ->where('manager_type', $managerType)
            ->where('status', 'active')
            ->first();
    }

    private function fromRow(CoachPaymentGateway $row, string $ownerType): ResolvedGateway
    {
        $creds         = is_array($row->credentials) ? $row->credentials : [];
        $webhookField  = GatewayFieldRegistry::webhookSecretField($row->gateway);
        $webhookSecret = $webhookField ? ($creds[$webhookField] ?? null) : null;

        return new ResolvedGateway(
            gateway:       $row->gateway,
            ownerType:     $ownerType,
            configId:      $row->id,
            coachId:       $row->coach_id,
            credentials:   $creds,
            webhookSecret: $webhookSecret !== '' ? $webhookSecret : null,
            charge:        (float) $row->charge,
            currencyId:    $row->currency_id,
            image:         $row->image,
            status:        $row->status,
        );
    }

    /**
     * Build the global-default ResolvedGateway from the existing platform
     * configuration, so the default tier behaves EXACTLY like the current
     * system (same credentials, same env-based webhook secrets).
     */
    private function defaultGateway(string $gateway): ResolvedGateway
    {
        return new ResolvedGateway(
            gateway:       $gateway,
            ownerType:     ResolvedGateway::OWNER_DEFAULT,
            configId:      null,
            coachId:       null,
            credentials:   $this->defaultCredentials($gateway),
            webhookSecret: $this->defaultWebhookSecret($gateway),
        );
    }

    /** @return array<string,mixed> global credentials in normalised keys */
    private function defaultCredentials(string $gateway): array
    {
        switch ($gateway) {
            case 'stripe':
                $b = $this->get_basic_payment_info();
                return [
                    'stripe_key'    => $b->stripe_key ?? null,
                    'stripe_secret' => $b->stripe_secret ?? null,
                ];

            case 'paypal':
                $b = $this->get_basic_payment_info();
                return [
                    'paypal_client_id'    => $b->paypal_client_id ?? null,
                    'paypal_secret_key'   => $b->paypal_secret_key ?? null,
                    'paypal_app_id'       => $b->paypal_app_id ?? null,
                    'paypal_account_mode' => $b->paypal_account_mode ?? null,
                ];

            case 'razorpay':
                $p = $this->get_payment_gateway_info();
                return [
                    'razorpay_key'         => $p->razorpay_key ?? null,
                    'razorpay_secret'      => $p->razorpay_secret ?? null,
                    'razorpay_name'        => $p->razorpay_name ?? null,
                    'razorpay_description' => $p->razorpay_description ?? null,
                    'razorpay_theme_color' => $p->razorpay_theme_color ?? null,
                ];

            case 'mollie':
                $p = $this->get_payment_gateway_info();
                return ['mollie_key' => $p->mollie_key ?? null];

            case 'instamojo':
                $p = $this->get_payment_gateway_info();
                return [
                    'instamojo_client_id'     => $p->instamojo_client_id ?? null,
                    'instamojo_client_secret' => $p->instamojo_client_secret ?? null,
                    'instamojo_account_mode'  => $p->instamojo_account_mode ?? null,
                ];

            case 'flutterwave':
                $p = $this->get_payment_gateway_info();
                return [
                    'flutterwave_public_key' => $p->flutterwave_public_key ?? null,
                    'flutterwave_secret_key' => $p->flutterwave_secret_key ?? null,
                    'flutterwave_app_name'   => $p->flutterwave_app_name ?? null,
                ];

            case 'paystack':
                $p = $this->get_payment_gateway_info();
                return [
                    'paystack_public_key' => $p->paystack_public_key ?? null,
                    'paystack_secret_key' => $p->paystack_secret_key ?? null,
                ];

            default:
                return [];
        }
    }

    /** Global webhook secret — same env/config source the webhooks use today. */
    private function defaultWebhookSecret(string $gateway): ?string
    {
        return match ($gateway) {
            'stripe'   => config('services.stripe.webhook_secret') ?: env('STRIPE_WEBHOOK_SECRET'),
            'razorpay' => config('services.razorpay.webhook_secret') ?: env('RAZORPAY_WEBHOOK_SECRET'),
            'paypal'   => config('services.paypal.webhook_id') ?: env('PAYPAL_WEBHOOK_ID'),
            default    => null,
        };
    }
}
