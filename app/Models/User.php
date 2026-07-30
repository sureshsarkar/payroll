<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Modules\InstructorRequest\app\Models\InstructorRequest;
use Modules\Location\app\Models\Country;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;

class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable, HasPushSubscriptions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // SECURITY (audit 2026-05-22) — removed FOUR dangerous columns from $fillable:
    //   - `id`               (allowing this lets any $user->fill($request->all())
    //                         set/change a user's primary key — catastrophic)
    //   - `role_id`          (role table lookup — bypassing the string `role`
    //                         column lets an attacker promote to an admin role
    //                         row id by guessing)
    //   - `two_factor_secret`        (encrypted TOTP shared secret — should
    //                                 ONLY be set via TwoFactorAuthService)
    //   - `two_factor_recovery_codes` (encrypted backup codes — ditto)
    //
    // Kept in $fillable: `role`, `status`, `is_banned`, `added_by`, `coach_id`,
    // `email_verified_at`, `two_factor_enabled_at`, `two_factor_confirmed_at`.
    // These are still used by existing controllers (RegisteredUserController,
    // InstructorDashboardController::storeStudetns, etc.) which pass explicit
    // arrays — they don't do $request->all() so mass-assignment via those
    // routes isn't reachable. Removing them would break working flows.
    //
    // A follow-up audit should switch ALL User::create($request->...) sites
    // to FormRequests with explicit ::validated() arrays, then move toward
    // $guarded = ['*'] + explicit fill().
    protected $fillable = [
        'role',
        'name',
        'username',
        'email',
        'password',
        'status',
        'is_banned',
        'verification_token',
        'forget_password_token',
        'email_verified_at',
        'added_by',
        'coach_id',
        'coach_unique_id',
        'notification_preferences',
        'two_factor_enabled_at',
        'two_factor_confirmed_at',
        'referral_code',
        'referred_by_user_id',
        'referred_at',
        'onboarding_theme_chosen_at',   // Theme phase 1 (audit 2026-05-25)
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        // Audit fix C2-user-side (2026-05-12) — sensitive; pre-fix the raw
        // token was stored here, post-fix the sha256 hash is. Either way
        // it shouldn't leak through toArray()/toJson()/API responses.
        'forget_password_token',
        'forget_password_token_expires_at',
        'verification_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at'         => 'datetime',
        'trial_used_at'             => 'datetime',
        'trial_started_at'          => 'datetime',
        'trial_expired_at'          => 'datetime',
        'password'                  => 'hashed',
        'notification_preferences'  => 'array',
        'two_factor_secret'         => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_enabled_at'     => 'datetime',
        'two_factor_confirmed_at'   => 'datetime',
    ];

    /** True iff this user has completed 2FA enrollment. */
    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_enabled_at) && !is_null($this->two_factor_confirmed_at);
    }

    /**
     * Audit 2026-05-18 Req 3 — resolve the commission rate that should
     * apply to an order against this coach's courses.
     *
     * Resolution order:
     *   1. users.commission_rate (per-coach override, NULL means "use default")
     *   2. settings.commission_rate (global)
     *   3. 0%
     *
     * Returns a float between 0 and 100.
     */
    public function effectiveCommissionRate(): float
    {
        // 1. Super-Admin pricing plan (centralized, 2026-06-24). When the coach
        //    is on a plan that defines a platform commission, THAT rate wins
        //    (already clamped, incl. the Enterprise 0–1% band). This is the
        //    "commission as per assigned plan" rule.
        $planRate = optional($this->activePlan())->effectiveCommissionRate();
        if ($planRate !== null) {
            return max(0.0, min(100.0, (float) $planRate));
        }
        // 2. Per-coach admin override.
        if ($this->commission_rate !== null) {
            return max(0.0, min(100.0, (float) $this->commission_rate));
        }
        // 3. Global default.
        $global = \Illuminate\Support\Facades\Cache::get('setting')?->commission_rate ?? 0;
        return max(0.0, min(100.0, (float) $global));
    }

    /* ===================== Referral / affiliate ===================== */

    /**
     * Auto-generate a unique referral code on first read. Persisted only when
     * the user is saved next time (lazy — avoids DB writes on every page view).
     */
    public function getReferralCodeAttribute(?string $value): string
    {
        if ($value) return $value;
        // Generate a 7-char alphanumeric, retry on collision.
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789'; // omit 0/O/1/l for readability
        do {
            $code = '';
            for ($i = 0; $i < 7; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        } while (static::where('referral_code', $code)->exists());

        // Persist immediately — referral codes are meaningful to share.
        $this->attributes['referral_code'] = $code;
        if ($this->exists) {
            static::withoutEvents(fn() => $this->newQuery()->whereKey($this->getKey())->update(['referral_code' => $code]));
        }
        return $code;
    }

    /** Public referral URL the user can share. */
    public function getReferralUrlAttribute(): string
    {
        // 2026-07-13 — point at the sign-up form (was '/?ref=' which dropped the
        // referred friend on the home page). Used by the Affiliate page's copy
        // link + all social-share buttons. Matches the dashboard widget +
        // ReferralController's /register?ref= link.
        return rtrim(config('app.url'), '/') . '/register?ref=' . $this->referral_code;
    }

    public function referrer()
    {
        return $this->belongsTo(self::class, 'referred_by_user_id');
    }

    public function referrals()
    {
        return $this->hasMany(self::class, 'referred_by_user_id');
    }

    public function commissionsEarned()
    {
        return $this->hasMany(\App\Models\ReferralCommission::class, 'referrer_user_id');
    }

    // ---- Referral wallet (non-withdrawable, membership-only) ----------------

    public function referralWalletTransactions()
    {
        return $this->hasMany(\App\Models\ReferralWalletTransaction::class, 'user_id')->latest();
    }

    public function referralRecords()
    {
        return $this->hasMany(\App\Models\Referral::class, 'referrer_user_id');
    }

    // ---- Membership ---------------------------------------------------------

    public function memberships()
    {
        return $this->hasMany(\App\Models\UserMembership::class, 'user_id');
    }

    public function activeMembership()
    {
        return $this->hasOne(\App\Models\UserMembership::class, 'user_id')
            ->where('status', 'active')
            ->where('payment_status', 'paid')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->latestOfMany('started_at');
    }

    public function hasActiveMembership(): bool
    {
        return $this->activeMembership()->exists();
    }

    /**
     * The MembershipPlan behind this coach's active membership (or null). Used
     * by pricing/commission/settlement logic. Read-only — coaches never change
     * their plan; only the Super Admin assigns it.
     */
    public function activePlan(): ?\App\Models\MembershipPlan
    {
        // F18 (audit 2026-06-26) — staff sub-accounts (role=instructor WITH a
        // coach_id) share their HEAD coach's plan. Resolve to the membership-
        // bearing identity so plan-driven capacity / commission / settlement
        // don't silently default to the free tier for staff-owned courses.
        if (! empty($this->coach_id)) {
            $head = self::find($this->coach_id);
            if ($head) {
                return $head->activeMembership?->plan
                    ?? optional($head->activeMembership()->with('plan')->first())->plan;
            }
        }

        return $this->activeMembership?->plan
            ?? optional($this->activeMembership()->with('plan')->first())->plan;
    }

    /* ---- Plan-driven capacity & settlement (2026-06-24, Phase 3) ---- */

    /** Active students on this coach's roster (the capacity unit). */
    public function coachStudentCount(): int
    {
        return (int) \App\Models\CoachStudentLink::where('coach_id', $this->id)
            ->where('status', 'active')->count();
    }

    /**
     * Would adding $adding new students exceed the coach's plan capacity?
     * Unlimited (no plan / NULL capacity / enterprise) is never at capacity.
     */
    public function coachAtStudentCapacity(int $adding = 1): bool
    {
        $plan = $this->activePlan();
        if (! $plan || $plan->isUnlimitedStudents()) {
            return false;
        }
        return ($this->coachStudentCount() + $adding) > (int) $plan->student_capacity;
    }

    /** Whether this coach raises payout requests (true) or is on direct settlement (false). */
    public function coachPayoutRequired(): bool
    {
        $plan = $this->activePlan();
        // No plan → keep today's behaviour (payout requests required).
        return $plan ? (bool) $plan->payout_required : true;
    }

    /** Enterprise: course earnings settle straight to the coach/institute bank. */
    public function coachUsesDirectSettlement(): bool
    {
        return (bool) optional($this->activePlan())->direct_settlement;
    }

    /**
     * The events this app sends notifications for. Used to render the
     * preferences UI and as the source of truth for what's controllable.
     */
    public const NOTIFICATION_EVENTS = [
        'lesson_question_replied' => [
            'label'       => 'Replies to my questions',
            'description' => 'When a coach replies to a question you asked on a lesson.',
            'roles'       => ['student'],
        ],
        'order_status_changed' => [
            'label'       => 'Order status updates',
            'description' => 'When your order is paid, refunded, or cancelled.',
            'roles'       => ['student'],
        ],
        'new_lesson_question' => [
            'label'       => 'New lesson questions',
            'description' => 'When a student posts a new question on one of your courses.',
            'roles'       => ['instructor'],
        ],
        'course_sold' => [
            'label'       => 'Course sales',
            'description' => 'When one of your courses is sold to a student.',
            'roles'       => ['instructor'],
        ],
        'new_course_review' => [
            'label'       => 'New course reviews',
            'description' => 'When a student leaves a rating or review on your course.',
            'roles'       => ['instructor'],
        ],
        'live_class_starting_soon' => [
            'label'       => 'Live class starting soon',
            'description' => 'Reminder N minutes before a live class begins.',
            'roles'       => ['student', 'instructor'],
        ],
        'live_class_scheduled' => [
            'label'       => 'New live class scheduled',
            'description' => 'When your coach schedules a new live class on a course you are enrolled in.',
            'roles'       => ['student'],
        ],
        'course_completed' => [
            'label'       => 'Course completed',
            'description' => 'When you finish all lessons in a course and your certificate is ready.',
            'roles'       => ['student'],
        ],
        'quiz_result' => [
            'label'       => 'Quiz results',
            'description' => 'Your score and pass/fail status when a quiz attempt is graded.',
            'roles'       => ['student'],
        ],
        'refund_status_changed' => [
            'label'       => 'Refund status updates',
            'description' => 'When your refund request is approved or rejected.',
            'roles'       => ['student'],
        ],
        'payment_due_reminder' => [
            'label'       => 'Payment due reminders',
            'description' => 'Periodic reminders to complete payment on pending orders.',
            'roles'       => ['student'],
        ],
        'new_enrollment' => [
            'label'       => 'New enrollments',
            'description' => 'When a student enrolls in one of your courses.',
            'roles'       => ['instructor'],
        ],
        'course_approval_status' => [
            'label'       => 'Course approval status',
            'description' => 'When admin approves or rejects one of your courses.',
            'roles'       => ['instructor'],
        ],
        'withdrawal_status_changed' => [
            'label'       => 'Payout status updates',
            'description' => 'When your withdrawal request is approved or rejected.',
            'roles'       => ['instructor'],
        ],
        'membership_activated' => [
            'label'       => 'Membership activated',
            'description' => 'When your membership is paid and activated (or renewed).',
            'roles'       => ['student', 'instructor'],
        ],
        'trial_expiring' => [
            'label'       => 'Trial expiring soon',
            'description' => 'Heads-up when your free trial is about to end (3 days, 1 day, day-of).',
            'roles'       => ['instructor'],
        ],
    ];

    /**
     * Channels controllable per-event. Database = the bell dropdown.
     * Mail/broadcast respect the same toggle scheme.
     */
    public const NOTIFICATION_CHANNELS = ['database', 'mail', 'broadcast'];

    /**
     * Default channel state when the user hasn't saved any preferences yet.
     */
    public function notificationChannelEnabled(string $event, string $channel): bool
    {
        $prefs = $this->notification_preferences ?? [];
        if (!isset($prefs[$event])) {
            return true; // default ON for everything
        }
        if (!isset($prefs[$event][$channel])) {
            return true;
        }
        return (bool) $prefs[$event][$channel];
    }

  

    public function favoriteCourses() {
        return $this->belongsToMany(Course::class, 'favorite_course_user')->withTimestamps();
    }

    public function scopeActive($query) {
        return $query->where('status', UserStatus::ACTIVE);
    }

    public function scopeInactive($query) {
        return $query->where('status', UserStatus::DEACTIVE);
    }

    public function scopeBanned($query) {
        return $query->where('is_banned', UserStatus::BANNED);
    }

    public function scopeUnbanned($query) {
        return $query->where('is_banned', UserStatus::UNBANNED);
    }

    public function socialite() {
        return $this->hasMany(SocialiteCredential::class, 'user_id');
    }

     public function youtube_credential(): HasOne
    {
        return $this->hasOne(YoutubeCredential::class, 'instructor_id', 'id');
    }
    
    function instructorInfo(): HasOne {
        return $this->hasOne(InstructorRequest::class, 'user_id', 'id');
    }

    public function courses() {
        return $this->hasMany(Course::class, 'instructor_id');
    }
    function enrollments(): HasMany {
        return $this->hasMany(Enrollment::class, 'user_id', 'id');
    }

    function country(): BelongsTo {
        return $this->belongsTo(Country::class, 'country_id');
    }
    function orders(): HasMany {
        return $this->hasMany(Order::class, 'buyer_id', 'id');
    }
    function zoom_credential(): HasOne {
        return $this->hasOne(ZoomCredential::class, 'instructor_id', 'id');
    }
    public function carts() {
        return $this->hasMany(Cart::class, 'user_id', 'id')->whereHas('course', function ($query) {
            $query->where(['is_approved' => 'approved', 'status' => 'active']);
        });
    }

    // Accessor for cart count
    public function getCartCountAttribute() {
        return $this->carts()->sum('qty');
    }
    public function getCartTotalAttribute() {
        return $this->carts()->join('courses', 'courses.id', '=', 'carts.course_id')->selectRaw('SUM(carts.qty * IFNULL(NULLIF(courses.discount, 0), courses.price)) as total')->value('total') ?? 0;
    }

    /**
     * Boot the model.
     */
    protected static function boot() {
        parent::boot();

        static::deleting(function ($user) {
            // Delete related instructor request
            $user->instructorInfo()->delete();
        });

        // 2026-05-21 P6 — per-coach white-label.
        // Every newly-created instructor gets an auto-assigned
        // subdomain on the platform's parent domain so they have
        // a white-label URL from day one. SubdomainAssigner is
        // idempotent (no-op if any domain already exists) and
        // silently skips on localhost (where auto-subdomains
        // don't help). Wrapped in try/catch so a transient DB
        // hiccup doesn't block user creation — the coach can
        // always add a subdomain manually via brand-settings.
        static::created(function ($user) {
            if (($user->role ?? null) !== 'instructor') return;
            try {
                app(\App\Services\SubdomainAssigner::class)->ensureForCoach($user);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('coach-subdomain-auto-assign-failed', [
                    'coach_id' => $user->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        });
    }
}
