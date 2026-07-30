<?php

namespace App\Services\Payment;

use Modules\Order\app\Models\Order;

/**
 * Thin checkout-facing wrapper over PaymentGatewayResolverService.
 *
 * Encapsulates the single-coach rule: a coach-specific gateway is used ONLY
 * when every course in the cart belongs to the SAME coach. Mixed multi-coach
 * carts resolve to the global default (one charge cannot be split across
 * separate merchant accounts) — this is the platform's money-safety guarantee.
 *
 * Also stamps the resolved provenance onto the order so webhook + callback
 * verification can re-resolve the exact credentials later, without session.
 */
class CheckoutGatewayResolver
{
    public function __construct(private PaymentGatewayResolverService $resolver)
    {
    }

    /**
     * The single distinct course-owner id, or null when the cart is empty or
     * spans more than one coach (→ caller uses the global default gateway).
     *
     * @param array<int,int|null> $ownerIds course instructor_id for each cart line
     */
    public function singleCoachId(array $ownerIds): ?int
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ownerIds),
            static fn (int $id) => $id > 0
        )));

        return count($ids) === 1 ? $ids[0] : null;
    }

    /**
     * Resolve the gateway for a cart given each line's course-owner id.
     *
     * @param array<int,int|null> $ownerIds
     */
    public function resolveForOwners(array $ownerIds, string $gateway): ResolvedGateway
    {
        return $this->resolver->resolve($this->singleCoachId($ownerIds), $gateway);
    }

    /** Convenience for single-course checkout. */
    public function resolveForOwner(?int $ownerId, string $gateway): ResolvedGateway
    {
        return $this->resolver->resolve($ownerId, $gateway);
    }

    /**
     * Persist the gateway provenance onto the order. Safe no-op on the columns
     * if a legacy DB hasn't run the metadata migration yet (guarded by fillable
     * + the columns being nullable).
     */
    public function stamp(Order $order, ResolvedGateway $gateway): void
    {
        $order->gateway_owner_type = $gateway->ownerType;
        $order->gateway_config_id  = $gateway->configId;
        $order->gateway_coach_id   = $gateway->coachId;
        $order->save();
    }
}
