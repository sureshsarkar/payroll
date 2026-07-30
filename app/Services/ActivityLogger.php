<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Enterprise H-A — central audit-log writer.
 *
 * Design rules:
 *  - NEVER breaks the calling flow: every write is wrapped in try/catch, so a
 *    logging failure can never roll back a payment, login, or CRUD action.
 *  - Resolves the actor from whichever guard is active (admin > web > system)
 *    and denormalizes name/role so the entry survives account deletion.
 *  - Strips secrets from old/new value diffs (passwords, tokens, gateway keys).
 */
class ActivityLogger
{
    /** Keys never persisted into the value diff. */
    private const SECRET_KEYS = [
        'password', 'password_confirmation', 'current_password', 'remember_token',
        'client_secret', 'sdk_secret', 'api_key', 'secret', 'token', 'access_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    public static function log(
        string $action,
        ?string $module = null,
        $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null,
        array $actorOverride = []
    ): void {
        try {
            $actor = $actorOverride ?: self::resolveActor();

            ActivityLog::create([
                'actor_id'     => $actor['id'] ?? null,
                'actor_type'   => $actor['type'] ?? 'system',
                'actor_name'   => $actor['name'] ?? 'System',
                'actor_role'   => $actor['role'] ?? null,
                'action'       => $action,
                'module'       => $module,
                'subject_type' => is_object($subject) ? get_class($subject) : null,
                'subject_id'   => is_object($subject) ? ($subject->id ?? null) : (is_numeric($subject) ? (int) $subject : null),
                'description'  => $description !== null ? mb_substr($description, 0, 500) : null,
                'old_values'   => self::sanitize($old),
                'new_values'   => self::sanitize($new),
                'ip_address'   => self::requestValue(fn ($r) => $r->ip()),
                'user_agent'   => self::requestValue(fn ($r) => mb_substr((string) $r->userAgent(), 0, 512)),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the audited action.
            Log::warning('ActivityLogger write failed: ' . $e->getMessage(), [
                'action' => $action, 'module' => $module,
            ]);
        }
    }

    private static function resolveActor(): array
    {
        if (Auth::guard('admin')->check()) {
            $a = Auth::guard('admin')->user();
            return ['id' => $a->id, 'type' => 'admin', 'name' => $a->name ?? 'Admin', 'role' => 'admin'];
        }
        if (Auth::guard('web')->check()) {
            $u = Auth::guard('web')->user();
            $role = $u->role ?? 'user';
            // Coach staff carry a coach_id; surface that distinction in the role label.
            if (! empty($u->coach_id) && $role === 'instructor') {
                $role = 'coach-staff';
            }
            return ['id' => $u->id, 'type' => 'user', 'name' => $u->name ?? 'User', 'role' => $role];
        }
        return ['id' => null, 'type' => 'system', 'name' => 'System', 'role' => null];
    }

    private static function sanitize(?array $values): ?array
    {
        if (empty($values)) {
            return null;
        }
        foreach (self::SECRET_KEYS as $k) {
            unset($values[$k]);
        }
        return $values ?: null;
    }

    private static function requestValue(callable $fn): ?string
    {
        try {
            $r = request();
            return $r ? $fn($r) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
