<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Modules\Location\app\Models\Country;

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

    /* LMS removal phase 2 (2026-08-27) — removed from this model:
     *   - effectiveCommissionRate(): platform commission on course sales.
     *   - the referral / affiliate block: referral_code + referral_url
     *     accessors, referrer/referrals, commissionsEarned,
     *     referralWalletTransactions, referralRecords.
     *   - the membership block: memberships, activeMembership,
     *     hasActiveMembership, activePlan.
     *   - plan-driven coach capacity + settlement: coachStudentCount,
     *     coachAtStudentCapacity, coachPayoutRequired,
     *     coachUsesDirectSettlement.
     *   - the NOTIFICATION_EVENTS catalogue, whose seventeen entries were all
     *     course / order / quiz / live-class / payout / membership / trial
     *     events. It is now empty: the preferences UI renders nothing until HR
     *     events are added, and notificationChannelEnabled() still defaults ON
     *     for any event id, so nothing silently stops being delivered.
     */

    /**
     * The events this app sends notifications for. Used to render the
     * preferences UI and as the source of truth for what is controllable.
     */
    public const NOTIFICATION_EVENTS = [];

    /**
     * Channels controllable per-event. Database = the bell dropdown.
     * Mail/broadcast respect the same toggle scheme.
     */
    public const NOTIFICATION_CHANNELS = ['database', 'mail', 'broadcast'];

    /**
     * Default channel state when the user has not saved any preferences yet.
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

  

    /* LMS removal phase 2 (2026-08-27) — removed the relations that pointed at
     * deleted models: favoriteCourses (wishlist), youtube_credential,
     * zoom_credential, instructorInfo (InstructorRequest), courses,
     * enrollments, orders, carts and the cart_count / cart_total accessors.
     * socialite() stays — social login is auth infrastructure, not LMS. */

    public function socialite() {
        return $this->hasMany(SocialiteCredential::class, 'user_id');
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

    function country(): BelongsTo {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /* LMS removal phase 2 (2026-08-27) — boot() removed entirely. It had two
     * hooks, both LMS: a deleting() hook that cascaded the user's
     * InstructorRequest row, and a created() hook that auto-assigned every new
     * instructor a white-label subdomain via SubdomainAssigner. */
}
