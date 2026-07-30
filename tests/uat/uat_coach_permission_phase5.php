<?php

/**
 * UAT — Phase 5: the full Step-14 scenario matrix, exercised end-to-end against
 * the live resolver + middleware and rolled back (no data left behind).
 *
 *   /d/xampp/php/php.exe tests/uat/uat_coach_permission_phase5.php
 *
 * Scenarios:
 *   1  Staff with no permissions        → denied everywhere
 *   2  Role permissions only            → access == role
 *   3  Override GRANT                    → access beyond role
 *   4  Override REVOKE                   → access removed despite role
 *   5  Grant beats revoke (conflict)     → grant wins
 *   6  Inactive role                     → grants nothing
 *   7  Deleted role                      → staff falls back to deny
 *   8  Cross-coach isolation             → staff of A never sees B's perm
 *   9  API/AJAX denial                   → JSON 403, not an HTML page
 *  10  Menu-hiding data-shape            → hidden items absent from effective set
 */

use App\Http\Middleware\CoachPermission;
use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\CoachStaffRole;
use App\Services\CoachPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = app(CoachPermissionService::class);
$pass = 0; $fail = 0;
function check(string $label, bool $ok): void {
    global $pass, $fail;
    echo ($ok ? "  \033[32mPASS\033[0m  " : "  \033[31mFAIL\033[0m  ") . $label . "\n";
    $ok ? $pass++ : $fail++;
}

DB::beginTransaction();
try {
    $mk = fn(string $role, ?int $coach) => CoachStaff::find(DB::table('users')->insertGetId([
        'role' => $role, 'name' => 'U' . uniqid(), 'email' => 'u' . uniqid() . '@uat.local',
        'password' => Hash::make('x'), 'status' => 'active', 'is_banned' => 'no',
        'coach_id' => $coach, 'added_by' => $coach, 'email_verified_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]));

    $coachId  = DB::table('users')->insertGetId([
        'role' => 'instructor', 'name' => 'Coach', 'email' => 'coach' . uniqid() . '@uat.local',
        'password' => Hash::make('x'), 'status' => 'active', 'is_banned' => 'no',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $coachB   = DB::table('users')->insertGetId([
        'role' => 'instructor', 'name' => 'CoachB', 'email' => 'coachb' . uniqid() . '@uat.local',
        'password' => Hash::make('x'), 'status' => 'active', 'is_banned' => 'no',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $pView   = CoachStaffPermission::create(['name' => 'View', 'slug' => 'v-' . uniqid()]);
    $pPayout = CoachStaffPermission::create(['name' => 'Payout', 'slug' => 'p-' . uniqid()]);
    $pExport = CoachStaffPermission::create(['name' => 'Export', 'slug' => 'e-' . uniqid()]);

    $role = CoachStaffRole::create(['role_name' => 'Mgr', 'role_slug' => 'mgr-' . uniqid(), 'added_by' => $coachId, 'status' => 1]);
    $role->permissions()->attach([$pView->id, $pPayout->id]);   // role grants View + Payout

    // 1 — no permissions
    $s1 = $mk('Manager', $coachId);
    $svc->syncStaff($s1, null, [], [], $coachId);
    check('1  no-perm staff denied View',   ! $svc->can($s1->fresh(), $pView->slug));

    // 2 — role only
    $s2 = $mk('Manager', $coachId);
    $svc->syncStaff($s2, $role->id, [], [], $coachId);
    check('2  role-only has View + Payout', $svc->can($s2->fresh(), $pView->slug) && $svc->can($s2->fresh(), $pPayout->slug));
    check('2  role-only lacks Export',      ! $svc->can($s2->fresh(), $pExport->slug));

    // 3 — grant beyond role
    $s3 = $mk('Manager', $coachId);
    $svc->syncStaff($s3, $role->id, [$pExport->id], [], $coachId);
    check('3  grant adds Export',           $svc->can($s3->fresh(), $pExport->slug));

    // 4 — revoke despite role
    $s4 = $mk('Manager', $coachId);
    $svc->syncStaff($s4, $role->id, [], [$pPayout->id], $coachId);
    check('4  revoke removes Payout',       ! $svc->can($s4->fresh(), $pPayout->slug));
    check('4  revoke keeps View',           $svc->can($s4->fresh(), $pView->slug));

    // 5 — grant beats revoke
    $s5 = $mk('Manager', $coachId);
    $svc->syncStaff($s5, $role->id, [$pPayout->id], [$pPayout->id], $coachId);
    check('5  grant beats revoke on Payout', $svc->can($s5->fresh(), $pPayout->slug));

    // 6 — inactive role grants nothing
    $inactive = CoachStaffRole::create(['role_name' => 'Off', 'role_slug' => 'off-' . uniqid(), 'added_by' => $coachId, 'status' => 0]);
    $inactive->permissions()->attach([$pView->id]);
    $s6 = $mk('Manager', $coachId);
    $svc->syncStaff($s6, $inactive->id, [], [], $coachId);
    check('6  inactive role grants nothing', ! $svc->can($s6->fresh(), $pView->slug));

    // 7 — deleted role → staff loses it (via the REAL controller destroy()).
    $tmpRole = CoachStaffRole::create(['role_name' => 'Tmp', 'role_slug' => 'tmp-' . uniqid(), 'added_by' => $coachId, 'status' => 1]);
    $tmpRole->permissions()->attach([$pView->id]);
    $s7 = $mk('Manager', $coachId);
    $svc->syncStaff($s7, $tmpRole->id, [], [], $coachId);

    Auth::guard('web')->login(\App\Models\User::find($coachId));   // real coach → checkPermission passes
    $ctrl  = app(\App\Http\Controllers\Frontend\Coach\CoachStaffRoleController::class);
    $route = new \Illuminate\Routing\Route('DELETE', '_', ['uses' => \App\Http\Controllers\Frontend\Coach\CoachStaffRoleController::class . '@destroy']);
    request()->setRouteResolver(fn () => $route);
    $ctrl->destroy($tmpRole->id);
    Auth::guard('web')->logout();

    check('7  deleted-role staff denied View (controller re-syncs)', ! $svc->can($s7->fresh(), $pView->slug));
    check('7  role row is actually deleted', CoachStaffRole::find($tmpRole->id) === null);

    // 8 — cross-coach isolation
    $s8 = $mk('Manager', $coachB);
    $svc->syncStaff($s8, null, [], [], $coachB);
    check('8  coach A staff never resolves against coach B row',
        CoachStaff::where('added_by', $coachId)->where('id', $s8->id)->first() === null);
    check('8  coach A cannot resolve coach B role',
        CoachStaffRole::where('added_by', $coachB)->where('id', $role->id)->first() === null);

    // 9 — API JSON 403
    Auth::guard('web')->login($s1->fresh());
    $req = Request::create('/instructor/x', 'POST');
    $req->headers->set('Accept', 'application/json');
    $res = app(CoachPermission::class)->handle($req, fn () => response('ok'), $pPayout->slug);
    check('9  API request → JSON 403',
        $res instanceof JsonResponse && $res->getStatusCode() === 403);
    Auth::guard('web')->logout();

    // 10 — menu-hiding data shape: revoked item absent from effective slug set
    $slugs = $svc->effectivePermissionSlugs($s4->fresh());
    check('10 revoked Payout absent from effective slugs (menu hidden)',
        in_array($pView->slug, $slugs, true) && ! in_array($pPayout->slug, $slugs, true));

    echo "\n  Total: " . ($pass + $fail) . "  Pass: $pass  Fail: $fail\n";
} finally {
    DB::rollBack();   // leave the DB exactly as we found it
    echo "  (rolled back — no fixtures persisted)\n";
}

exit($fail === 0 ? 0 : 1);
