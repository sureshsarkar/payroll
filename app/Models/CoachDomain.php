<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Per-coach white-label — Phase 2.
 *
 * One row per (coach, hostname) the platform recognises. The
 * ResolveCoachByDomain middleware looks up the request host in this
 * table; if matched + verified, the coach id is stamped on the
 * request for BrandResolver to pick up.
 *
 * Two read paths, both cached 60s:
 *
 *   coachIdForHost($host)
 *     The middleware's hot path. Lower-case + strip-port the host,
 *     match against hostname column. Returns null for unmatched
 *     (the platform's own root domain, admin paths, etc.) — the
 *     middleware treats null as "no tenant context, use defaults".
 *
 *   primaryHostFor($coachId)
 *     Used by email composers + share-link generators that need to
 *     build absolute URLs targeting THIS coach's domain (not the
 *     platform's APP_URL). Returns null if coach has no domain row;
 *     callers should fall back to config('app.url') in that case.
 *
 * Cache invalidation happens on save / delete via the model events.
 */
class CoachDomain extends Model
{
    use HasFactory;

    /** Status state machine (2026-06 governance layer). */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_VERIFIED  = 'verified';   // DNS proven, awaiting approval (strict mode)
    public const STATUS_ACTIVE    = 'active';     // live + serving
    public const STATUS_FAILED    = 'failed';     // verification attempted + failed
    public const STATUS_SUSPENDED = 'suspended';  // admin-disabled

    /** SSL state. */
    public const SSL_NONE   = 'none';
    public const SSL_PENDING = 'pending';
    public const SSL_ISSUED = 'issued';
    public const SSL_FAILED = 'failed';

    protected $fillable = [
        'coach_id',
        'hostname',
        'kind',
        'is_primary',
        'verified_at',
        // 2026-06 governance fields
        'status',
        'last_verified_at',
        'verify_attempts',
        'last_error',
        'ssl_status',
        'ssl_checked_at',
        'approved_at',
        'approved_by',
        'rejected_reason',
    ];

    protected $casts = [
        'is_primary'       => 'boolean',
        'verified_at'      => 'datetime',
        'last_verified_at' => 'datetime',
        'verify_attempts'  => 'integer',
        'ssl_checked_at'   => 'datetime',
        'approved_at'      => 'datetime',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CoachDomainEvent::class, 'coach_domain_id')->latest();
    }

    /* ───────── scopes ───────── */

    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending($q)
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeCustom($q)
    {
        return $q->where('kind', 'custom');
    }

    /**
     * Transition the domain to a new status and write an audit event.
     *
     * @param  array  $opts  fill=>[extra columns], actor_type, actor_id,
     *                       ip, meta, event (defaults to the status name)
     */
    public function markStatus(string $status, array $opts = []): self
    {
        $fill = array_merge(['status' => $status], $opts['fill'] ?? []);
        $this->forceFill($fill)->save();

        self::recordEvent($this, $opts['event'] ?? $status, [
            'actor_type' => $opts['actor_type'] ?? 'system',
            'actor_id'   => $opts['actor_id']   ?? null,
            'ip'         => $opts['ip']          ?? null,
            'meta'       => $opts['meta']        ?? null,
        ]);

        return $this;
    }

    /**
     * Apply a successful DNS proof. In strict mode (admin approval required)
     * the domain becomes VERIFIED and waits; otherwise it goes straight to
     * ACTIVE (live). Shared by the coach verify() flow and the auto-recheck
     * job. Returns the resulting status.
     */
    public function applyDnsVerified(bool $requiresApproval, array $opts = []): string
    {
        $actor = [
            'actor_type' => $opts['actor_type'] ?? 'system',
            'actor_id'   => $opts['actor_id']   ?? null,
            'ip'         => $opts['ip']          ?? null,
        ];
        $meta = array_merge(['method' => $opts['method'] ?? null], $opts['meta'] ?? []);
        $base = ['last_verified_at' => now(), 'verify_attempts' => 0, 'last_error' => null];

        if ($requiresApproval && $this->status !== self::STATUS_ACTIVE) {
            $this->markStatus(self::STATUS_VERIFIED, array_merge($actor, [
                'fill'  => $base,
                'event' => 'verified',
                'meta'  => $meta + ['awaiting_approval' => true],
            ]));
            return self::STATUS_VERIFIED;
        }

        $this->markStatus(self::STATUS_ACTIVE, array_merge($actor, [
            'fill'  => array_merge($base, ['verified_at' => now()]),
            'event' => 'verified',
            'meta'  => $meta,
        ]));
        return self::STATUS_ACTIVE;
    }

    /** Write one audit-trail row. Tolerant — never breaks the main flow. */
    public static function recordEvent(self $row, string $event, array $opts = []): void
    {
        try {
            CoachDomainEvent::create([
                'coach_domain_id' => $row->id,
                'coach_id'        => $row->coach_id,
                'hostname'        => $row->hostname,
                'event'           => $event,
                'actor_type'      => $opts['actor_type'] ?? 'system',
                'actor_id'        => $opts['actor_id']   ?? null,
                'ip'              => $opts['ip']          ?? null,
                'meta'            => $opts['meta']        ?? null,
            ]);
        } catch (\Throwable $e) {
            // Audit must never block a domain operation.
        }
    }

    /**
     * Coach id for a given request host, or null if no match.
     * Only returns matches for VERIFIED rows — unverified custom
     * domains would otherwise let a coach spoof a brand on a
     * hostname they don't actually control.
     */
    public static function coachIdForHost(?string $host): ?int
    {
        if (! $host) return null;
        $h = self::normalise($host);
        if ($h === '') return null;

        $key = "coach_domain:host:$h";
        return Cache::remember($key, 60, function () use ($h) {
            // 2026-06 governance: a host resolves only when it is verified
            // (live) AND not suspended. A SUSPENDED domain stops serving
            // immediately; an unverified / pending custom domain never
            // resolves. (Backward compatible: legacy rows that set
            // verified_at without an explicit status still resolve.)
            $row = static::query()
                ->where('hostname', $h)
                ->whereNotNull('verified_at')
                ->where('status', '!=', self::STATUS_SUSPENDED)
                ->first(['coach_id']);
            return $row ? (int) $row->coach_id : null;
        });
    }

    /**
     * The 'kind' (subdomain|custom) of the coach_domains row matching a
     * host, or null when unmatched. Cheap cached lookup the resolver uses
     * to decide whether the current request is on a subdomain that should
     * be canonicalised onto the coach's live custom domain. Mirrors the
     * verified+not-suspended gate of coachIdForHost().
     */
    public static function kindForHost(?string $host): ?string
    {
        if (! $host) return null;
        $h = self::normalise($host);
        if ($h === '') return null;

        return Cache::remember("coach_domain:kind:$h", 60, function () use ($h) {
            $row = static::query()
                ->where('hostname', $h)
                ->whereNotNull('verified_at')
                ->where('status', '!=', self::STATUS_SUSPENDED)
                ->first(['kind']);
            return $row?->kind;
        });
    }

    /**
     * The ACTIVE, verified PRIMARY custom domain hostname for a coach, or
     * null when the coach has no live custom domain. Used to permanently
     * canonicalise subdomain traffic onto the coach's main domain — only
     * a STATUS_ACTIVE custom row qualifies (pending/verified-awaiting/
     * suspended never redirect), so we never send visitors to a domain
     * that is not yet actually serving.
     */
    public static function activeCustomPrimaryHostFor(int $coachId): ?string
    {
        return Cache::remember("coach_domain:active_custom_primary:$coachId", 60, function () use ($coachId) {
            $row = static::query()
                ->where('coach_id', $coachId)
                ->where('kind', 'custom')
                ->whereNotNull('verified_at')
                ->where('status', self::STATUS_ACTIVE)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->first(['hostname']);
            return $row?->hostname;
        });
    }

    /**
     * Primary hostname for a coach (for outbound URL building).
     * Returns null when the coach has no rows or no verified row;
     * callers should fall back to config('app.url').
     */
    public static function primaryHostFor(int $coachId): ?string
    {
        $key = "coach_domain:primary:$coachId";
        return Cache::remember($key, 60, function () use ($coachId) {
            $row = static::query()
                ->where('coach_id', $coachId)
                ->whereNotNull('verified_at')
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->first(['hostname']);
            return $row?->hostname;
        });
    }

    /**
     * Drop the cached lookup for one host. Used by tests + the
     * onboarding flow when a coach verifies a new custom domain.
     */
    public static function forgetCacheForHost(string $host): void
    {
        $h = self::normalise($host);
        Cache::forget('coach_domain:host:' . $h);
        Cache::forget('coach_domain:kind:' . $h);
    }

    public static function normalise(string $host): string
    {
        $h = strtolower(trim($host));
        // Strip protocol if anyone fed in a URL.
        $h = preg_replace('#^https?://#i', '', $h);
        // Strip path / query.
        $h = explode('/', $h, 2)[0];
        // Strip port.
        $h = explode(':', $h, 2)[0];
        return $h;
    }

    protected static function booted(): void
    {
        $bust = function (self $m) {
            $h = self::normalise((string) $m->hostname);
            Cache::forget("coach_domain:host:" . $h);
            Cache::forget("coach_domain:kind:" . $h);
            Cache::forget("coach_domain:primary:" . (int) $m->coach_id);
            Cache::forget("coach_domain:active_custom_primary:" . (int) $m->coach_id);
        };
        static::saved($bust);
        static::deleted($bust);
    }
}
