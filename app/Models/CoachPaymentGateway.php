<?php

namespace App\Models;

use App\Services\Payment\GatewayFieldRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Per-coach payment gateway configuration.
 *
 * One row per (coach, gateway, manager_type). `credentials` is encrypted at
 * rest via the `encrypted:array` cast, so API secrets / webhook secrets are
 * never stored in plain text. Read it back as a normal PHP array.
 *
 * @property int    $coach_id
 * @property string $gateway       canonical lowercase name (razorpay|stripe|paypal|...)
 * @property string $manager_type  self::MANAGER_COACH | self::MANAGER_ADMIN
 * @property string $status        'active' | 'inactive'
 * @property float  $charge
 * @property ?int   $currency_id
 * @property ?string $image
 * @property array  $credentials   decrypted field map
 */
class CoachPaymentGateway extends Model
{
    /** Coach self-managed (Enterprise only) -> owner_type coach_self_managed */
    public const MANAGER_COACH = 'coach';

    /** Super-Admin configured for this coach -> owner_type super_admin_for_coach */
    public const MANAGER_ADMIN = 'admin';

    protected $table = 'coach_payment_gateways';

    protected $fillable = [
        'coach_id',
        'gateway',
        'manager_type',
        'status',
        'charge',
        'currency_id',
        'image',
        'credentials',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'charge'      => 'float',
        'currency_id' => 'integer',
    ];

    /**
     * Bust the per-coach resolver cache whenever a config changes, mirroring
     * the cache-invalidation pattern used by the global PaymentGateway model.
     */
    protected static function booted(): void
    {
        $bust = function (self $model) {
            Cache::forget(self::cacheKey($model->coach_id));
        };
        static::saved($bust);
        static::deleted($bust);
    }

    public static function cacheKey(int $coachId): string
    {
        return 'coach_payment_gateways_' . $coachId;
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * True when every gateway-required credential field is non-empty. A config
     * that fails this is treated as not-yet-usable and the resolver falls back
     * to the next priority tier (so a half-finished coach setup can never break
     * checkout).
     */
    public function hasUsableCredentials(): bool
    {
        $creds = is_array($this->credentials) ? $this->credentials : [];
        foreach (GatewayFieldRegistry::requiredFields($this->gateway) as $field) {
            if (trim((string) ($creds[$field] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Credentials with every secret field masked, for safe display in the UI /
     * API responses. Real secrets never leave the server.
     *
     * @return array<string,mixed>
     */
    public function maskedCredentials(): array
    {
        $creds   = is_array($this->credentials) ? $this->credentials : [];
        $secrets = GatewayFieldRegistry::secretFields($this->gateway);

        $out = [];
        foreach ($creds as $key => $value) {
            if (in_array($key, $secrets, true) && trim((string) $value) !== '') {
                $out[$key] = self::maskSecret((string) $value);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /** Show only the last 4 characters of a secret, e.g. "••••••••cd34". */
    public static function maskSecret(string $value): string
    {
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('•', max($len, 4));
        }
        return str_repeat('•', min($len - 4, 12)) . substr($value, -4);
    }
}
