<?php

namespace App\Services\Payment;

/**
 * Immutable result of PaymentGatewayResolverService::resolve().
 *
 * Carries the winning gateway's credentials (normalised to the same keys the
 * global config uses, so it's a drop-in at the checkout/webhook seams), the
 * resolved webhook/verification secret, and the provenance needed to stamp the
 * order + re-resolve at webhook time.
 */
final class ResolvedGateway
{
    public const OWNER_DEFAULT     = 'super_admin_default';
    public const OWNER_ADMIN_COACH = 'super_admin_for_coach';
    public const OWNER_COACH_SELF  = 'coach_self_managed';

    /**
     * @param array<string,mixed> $credentials normalised credential field map
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $ownerType,
        public readonly ?int $configId,
        public readonly ?int $coachId,
        public readonly array $credentials,
        public readonly ?string $webhookSecret = null,
        public readonly float $charge = 0.0,
        public readonly ?int $currencyId = null,
        public readonly ?string $image = null,
        public readonly string $status = 'active',
    ) {
    }

    public function isDefault(): bool
    {
        return $this->ownerType === self::OWNER_DEFAULT;
    }

    public function isCoachOwned(): bool
    {
        return $this->ownerType !== self::OWNER_DEFAULT;
    }

    /** @return mixed */
    public function credential(string $key, $default = null)
    {
        return $this->credentials[$key] ?? $default;
    }

    /**
     * A redacted view safe for logging / debugging — never exposes secrets.
     *
     * @return array<string,mixed>
     */
    public function toAuditArray(): array
    {
        return [
            'gateway'     => $this->gateway,
            'owner_type'  => $this->ownerType,
            'config_id'   => $this->configId,
            'coach_id'    => $this->coachId,
            'has_webhook' => $this->webhookSecret !== null && $this->webhookSecret !== '',
        ];
    }
}
