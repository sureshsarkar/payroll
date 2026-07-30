<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Enterprise H-A — one audited action per row. See ActivityLogger for writes.
 * Only created_at is tracked (the table has no updated_at).
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // Canonical action verbs.
    public const LOGIN                  = 'login';
    public const LOGOUT                 = 'logout';
    public const LOGIN_FAILED           = 'login_failed';
    public const CREATED                = 'created';
    public const UPDATED                = 'updated';
    public const DELETED                = 'deleted';
    public const STATUS_CHANGED         = 'status_changed';
    public const PAYMENT_STATUS_CHANGED = 'payment_status_changed';
    public const COMMISSION_CHANGED     = 'commission_changed';
    public const ROLE_CHANGED           = 'role_changed';
    public const EXPORTED               = 'exported';
    public const PLAN_CHANGED           = 'plan_changed';        // pricing-plan config edits
    public const PLAN_ASSIGNED          = 'plan_assigned';       // coach plan assignment
    public const TRIAL_ACTIVATED        = 'trial_activated';     // free trial granted to a coach
    public const TRIAL_BLOCKED          = 'trial_blocked';       // duplicate free-trial attempt rejected
    public const TRIAL_EXPIRED          = 'trial_expired';       // free trial lapsed
}
