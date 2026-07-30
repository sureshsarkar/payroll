<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One per-staff permission override row (grant or revoke) relative to the
 * staff member's role. Managed exclusively by CoachPermissionService.
 */
class StaffPermissionOverride extends Model
{
    protected $fillable = ['user_id', 'coach_staff_permission_id', 'effect', 'added_by'];

    public const GRANT  = 'grant';
    public const REVOKE = 'revoke';

    public function permission(): BelongsTo
    {
        return $this->belongsTo(CoachStaffPermission::class, 'coach_staff_permission_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
