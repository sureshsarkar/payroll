<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Per-coach white-label brand profile.
 *
 * Stores ONLY the coach's overrides. Reads should go through
 * App\Services\BrandResolver which composes this row with the
 * platform defaults from cache('setting'), so a coach who hasn't
 * customised a particular field automatically inherits the
 * platform's value for that field.
 *
 * One row per coach (UNIQUE coach_id at the schema level).
 *
 * Cached 60s per coach via firstOrCreateForCoach() — the most
 * frequent read path. Cache is invalidated on save via the
 * model's saved/deleted events.
 */
class CoachBrandSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id',
        'brand_name',
        'logo_path',
        'favicon_path',
        'primary_color',
        'accent_color',
        'support_email',
        'support_phone',
        'terms_url',
        'privacy_url',
        'footer_text',
        'email_signature',
        // P3 — per-coach email config
        'mail_from_address',
        'mail_from_name',
        'mail_reply_to',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password_encrypted',
        'smtp_encryption',
        'smtp_verified_at',
        // Offline Payment (2026-07-11) — per-coach approval toggle + enabled methods
        'offline_payment_needs_approval',
        'offline_payment_methods',
        'offline_payment_email_receipt',
        // Pricing & Plans (2026-07-13) — opt-in to collect payment on plan bookings.
        'pricing_plan_collect_payment',
    ];

    /**
     * smtp_password_encrypted uses Laravel's built-in encrypted cast.
     * Reads return plaintext; writes encrypt automatically. The
     * column stores the ciphertext, so a DB-read leak doesn't hand
     * out usable SMTP creds.
     */
    protected $casts = [
        'smtp_password_encrypted' => 'encrypted',
        'smtp_verified_at'        => 'datetime',
        'offline_payment_methods' => 'array',
        'pricing_plan_collect_payment' => 'boolean',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * Lazy row creation — every coach should have a row eventually,
     * but the backfill migration handles existing data. New coaches
     * (registered after the migration) get their row on first read.
     *
     * Cached 60s — the resolver hits this on every page render via
     * the view composer.
     */
    public static function firstOrCreateForCoach(int $coachId): self
    {
        return Cache::remember(
            self::cacheKey($coachId),
            60,
            fn () => static::firstOrCreate(['coach_id' => $coachId])
        );
    }

    public static function forgetCacheForCoach(int $coachId): void
    {
        Cache::forget(self::cacheKey($coachId));
    }

    protected static function cacheKey(int $coachId): string
    {
        return "cbs:coach:$coachId";
    }

    protected static function booted(): void
    {
        // Save / delete invalidates the resolver cache so changes
        // take effect on the very next request, no 60s lag.
        static::saved(fn (self $m) => self::forgetCacheForCoach((int) $m->coach_id));
        static::deleted(fn (self $m) => self::forgetCacheForCoach((int) $m->coach_id));
    }
}
