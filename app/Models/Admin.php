<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'forget_password_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_enabled_at',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * forget_password_token + its expiry are sensitive — pre-C2 fix the
     * raw token was stored here; post-fix the sha256 hash is, but either
     * way it shouldn't leak through toArray()/toJson()/API responses.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'forget_password_token',
        'forget_password_token_expires_at',
    ];

    /**
     * The attributes that should be cast.
     * - two_factor_secret + recovery_codes are encrypted at rest.
     */
    protected $casts = [
        'password'                  => 'hashed',
        'two_factor_secret'         => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_enabled_at'     => 'datetime',
        'two_factor_confirmed_at'   => 'datetime',
    ];

    /**
     * True iff this admin has completed 2FA enrollment.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_enabled_at) && !is_null($this->two_factor_confirmed_at);
    }

    public static function getPermissionGroup()
    {
        $permission_group = DB::table('permissions')
            ->select('group_name as name')
            ->groupBy('group_name')
            ->get();

        return $permission_group;
    }

    public static function getpermissionsByGroupName($group_name)
    {
        $permissions = DB::table('permissions')
            ->select('name', 'id')
            ->where('group_name', $group_name)
            ->get();

        return $permissions;
    }

    public static function roleHasPermission($role, $permissions)
    {
        $hasPermission = true;
        foreach ($permissions as $permission) {
            if (! $role->hasPermissionTo($permission->name)) {
                $hasPermission = false;

                return $hasPermission;
            }
        }

        return $hasPermission;
    }
}
