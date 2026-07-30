<?php

/**
 * Idempotent E2E fixture seeder for the RBAC suite.
 *
 * Run against the demo DB before the Playwright run:
 *   /d/xampp/php/php.exe tests/e2e/seed-fixtures.php
 *
 * Creates (or refreshes) a real coach + staff members so the permission specs
 * can log in and assert live enforcement. Everything is coach-scoped by
 * `added_by`, passwords are hashed, and re-running is safe (find-or-create).
 *
 * Fixtures:
 *   e2e-instructor@mbsguru.test   real coach (bypasses every gate)
 *   e2e-student@mbsguru.test      a student of that coach
 *   e2e-staff@mbsguru.test        role "E2E Limited"  → courses, courses-create, coach-students
 *   e2e-staff-settings@...        role "E2E Settings" → courses, settings-zoom  (menu-hiding test)
 *   role "E2E Manager"            5 perms (used by the override spec 26)
 */

use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\CoachStaffRole;
use App\Services\CoachPermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const PW = 'e2e!Test#2026';

/** Find-or-create a user row by email, returning its id. */
function ensureUser(string $email, array $attrs): int
{
    // email_verified_at is required — the coach panel sits behind `verified`
    // middleware, so an unverified fixture bounces to the email-verify page.
    $row = DB::table('users')->where('email', $email)->first();
    if ($row) {
        DB::table('users')->where('id', $row->id)->update($attrs + ['email_verified_at' => now(), 'updated_at' => now()]);
        return $row->id;
    }
    return DB::table('users')->insertGetId($attrs + [
        'email' => $email, 'password' => Hash::make(PW), 'status' => 'active',
        'is_banned' => 'no', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Find-or-create a coach-scoped role, returning the model. */
function ensureRole(int $coachId, string $name, array $slugs): CoachStaffRole
{
    $role = CoachStaffRole::firstOrCreate(
        ['added_by' => $coachId, 'role_slug' => \Illuminate\Support\Str::slug($name)],
        ['role_name' => $name, 'status' => 1]
    );
    $permIds = CoachStaffPermission::whereIn('slug', $slugs)->pluck('id')->all();
    $role->permissions()->sync($permIds);
    return $role;
}

$coachId = ensureUser('e2e-instructor@mbsguru.test', [
    'name' => 'E2E Coach', 'role' => 'instructor', 'coach_id' => null, 'password' => Hash::make(PW),
]);

ensureUser('e2e-student@mbsguru.test', [
    'name' => 'E2E Student', 'role' => 'student', 'coach_id' => $coachId,
]);

$limited  = ensureRole($coachId, 'E2E Limited',  ['courses', 'courses-create', 'coach-students']);
$settings = ensureRole($coachId, 'E2E Settings', ['courses', 'settings-zoom']);
ensureRole($coachId, 'E2E Manager', ['courses', 'courses-create', 'courses-edit', 'coach-students', 'coach-orders']);

$svc = app(CoachPermissionService::class);

$staffId = ensureUser('e2e-staff@mbsguru.test', [
    'name' => 'E2E Staff (Limited)', 'role' => 'Manager', 'coach_id' => $coachId, 'added_by' => $coachId,
]);
$svc->syncStaff(CoachStaff::find($staffId), $limited->id, [], [], $coachId);

$staffSetId = ensureUser('e2e-staff-settings@mbsguru.test', [
    'name' => 'E2E Staff (Settings)', 'role' => 'Manager', 'coach_id' => $coachId, 'added_by' => $coachId,
]);
$svc->syncStaff(CoachStaff::find($staffSetId), $settings->id, [], [], $coachId);

// All-permissions staff — used by the access-denied UAT + the module probe to
// prove that a staff holding every permission can reach every module (guards the
// checkPermission URL-segment regression).
$staffAllId = ensureUser('e2e-staff-all@mbsguru.test', [
    'name' => 'E2E Staff (All)', 'role' => 'Manager', 'coach_id' => $coachId, 'added_by' => $coachId,
]);
CoachStaff::find($staffAllId)->permissions()->sync(CoachStaffPermission::pluck('id')->all());

// 2026-07-13 (July-13 doc #3) — a staff who can VIEW orders but was NOT granted
// "Create Order". Proves the create button + endpoint are hidden/blocked without
// the granular coach-orders-create slug (the fix gates on that slug now).
$ordersView = ensureRole($coachId, 'E2E Orders View', ['coach-orders']);
$staffOrdersViewId = ensureUser('e2e-staff-orders-view@mbsguru.test', [
    'name' => 'E2E Staff (Orders view only)', 'role' => 'Manager', 'coach_id' => $coachId, 'added_by' => $coachId,
]);
$svc->syncStaff(CoachStaff::find($staffOrdersViewId), $ordersView->id, [], [], $coachId);

echo "OK — coach {$coachId}, staff-limited {$staffId}, staff-settings {$staffSetId}, staff-all {$staffAllId}, staff-orders-view {$staffOrdersViewId}\n";
echo "  E2E Limited  perms: " . implode(', ', $svc->effectivePermissionSlugs(CoachStaff::find($staffId))) . "\n";
echo "  E2E Settings perms: " . implode(', ', $svc->effectivePermissionSlugs(CoachStaff::find($staffSetId))) . "\n";
echo "  E2E Staff-All perms: " . CoachStaffPermission::count() . " (every catalog slug)\n";
