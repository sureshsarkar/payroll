<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression guard for admin-controller permission-check coverage.
 *
 * Every admin controller method that mutates state (or reveals
 * sensitive data via index/show) must call:
 *   checkAdminHasPermissionAndThrowException('<permission>')
 * Without it, the only gate is `auth:admin` + `2fa:admin` — meaning
 * any sub-admin (support staff, etc.) can hit every endpoint
 * regardless of their role's granted permissions.
 *
 * 2026-05-06 audit added the missing checks across:
 *   - Admin\ReferralCommissionController (5 methods, all on the
 *     money path — approve/pay/reverse/updatePercent)
 *   - Admin\ReferralController          (5 methods)
 *   - Admin\MembershipPlanController    (6 methods)
 *   - Admin\UserMembershipController    (6 methods incl. refund)
 *
 * Plus fixed an inconsistent password.min error message in
 * AdminController::update (rule was min:8, message said "4 characters").
 *
 * Allowlist: TwoFactorController is each admin's own 2FA management
 * — operates on auth('admin')->user() only, no cross-admin access,
 * so a per-permission check would be wrong (every admin needs to be
 * able to manage their own 2FA).
 */
class AdminPermissionTest extends TestCase
{
    /**
     * Files in app/Http/Controllers/Admin/ that legitimately don't
     * need per-method permission checks. Adding a path here requires
     * a per-file review confirming all methods only operate on the
     * caller's own data.
     */
    private const EXEMPT_FILES = [
        'app/Http/Controllers/Admin/TwoFactorController.php',
    ];

    /**
     * Method names that mutate state or expose sensitive data and so
     * MUST carry a permission check. The list is intentionally broad
     * — better to have a false-positive that's allowlisted than to
     * miss a real escalation hole.
     */
    private const SENSITIVE_METHOD_NAMES = [
        // CRUD
        'index', 'create', 'store', 'edit', 'update', 'destroy', 'show',
        // common admin verbs
        'changeStatus', 'approve', 'reject', 'pay', 'reverse', 'extend',
        'refund', 'cancel', 'confirm', 'updatePercent', 'updateSettings',
        'settings',
        // reports
        'conversionReport',
    ];

    public function test_every_admin_controller_method_has_permission_check(): void
    {
        $files = glob(app_path('Http/Controllers/Admin/*.php')) ?: [];

        $violations = [];
        foreach ($files as $abs) {
            $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
            if (in_array($rel, self::EXEMPT_FILES, true)) continue;

            $body = (string) file_get_contents($abs);
            $methodNamePattern = implode('|', array_map('preg_quote', self::SENSITIVE_METHOD_NAMES));

            // Find every public method whose name is in the sensitive list.
            // Match the method body (greedy up to the next "    public " or end of file).
            if (!preg_match_all(
                "/^\s*public\s+function\s+($methodNamePattern)\s*\([^)]*\)[^{]*\{(.*?)(?=^\s*public\s+function|\z)/sm",
                $body,
                $matches,
                PREG_SET_ORDER
            )) continue;

            foreach ($matches as $m) {
                [$_, $methodName, $methodBody] = $m;
                if (!str_contains($methodBody, 'checkAdminHasPermissionAndThrowException')) {
                    $violations[] = "$rel::$methodName";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Admin controller methods missing permission check.\n" .
            "Each must call checkAdminHasPermissionAndThrowException('<permission>')\n" .
            "as the first statement, OR be added to AdminPermissionTest::EXEMPT_FILES\n" .
            "with a per-file review confirming the methods only operate on the\n" .
            "caller's own data (e.g. own profile, own 2FA setup).\n\n" .
            "Sites:\n  " . implode("\n  ", $violations)
        );
    }
}
