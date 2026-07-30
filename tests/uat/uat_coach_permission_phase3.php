<?php
/**
 * UAT — Coach Staff Override lifecycle (Phase 3). Logs in as a coach and drives
 * the REAL CoachStaffController::update() through: assign role → revoke a module
 * → grant an extra module → reset to role. Prints PASS/FAIL. Runs inside a
 * transaction that is ROLLED BACK, so it leaves no data behind.
 *
 *   DB_DATABASE=mbs_test /d/xampp/php/php.exe tests/uat/uat_coach_permission_phase3.php
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Frontend\Coach\CoachStaffController;
use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\CoachStaffRole;
use App\Models\User;
use App\Services\CoachPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$svc = app(CoachPermissionService::class);
$pass = 0; $fail = 0; $lines = [];
$check = function (string $n, bool $ok) use (&$pass, &$fail, &$lines) {
    $ok ? $pass++ : $fail++;
    $lines[] = ($ok ? "  \033[32mPASS\033[0m  " : "  \033[31mFAIL\033[0m  ") . $n;
};

DB::beginTransaction();
try {
    $mkUser = fn (string $role, ?int $coachId = null) => DB::table('users')->insertGetId([
        'role' => $role, 'name' => ucfirst($role) . ' ' . uniqid(), 'email' => substr($role,0,3) . uniqid() . '@uat.local',
        'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
        'coach_id' => $coachId, 'added_by' => $coachId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $mkPerm = fn (string $slug) => CoachStaffPermission::create(['name' => ucfirst($slug), 'slug' => $slug . '-' . uniqid()]);

    $coach = User::find($mkUser('instructor'));
    $A = $mkPerm('courses'); $B = $mkPerm('coach-students'); $C = $mkPerm('payout'); $D = $mkPerm('reports-export');
    $role = CoachStaffRole::create(['role_name' => 'Academic Manager', 'role_slug' => 'am-'.uniqid(), 'added_by' => $coach->id, 'status' => 1]);
    $role->permissions()->attach([$A->id, $B->id, $C->id]);
    $staff = CoachStaff::find($mkUser('Academic Manager', $coach->id));

    Auth::guard('web')->login($coach);   // act as the coach
    $ctrl = app(CoachStaffController::class);
    $update = function (array $permIds) use ($ctrl, $staff, $role) {
        $req = Request::create('/x', 'PUT', ['name' => 'Priya', 'email' => $staff->email, 'status' => 'active', 'role_id' => $role->id, 'permissions' => $permIds]);
        app()->instance('request', $req);
        $ctrl->update($staff->id, $req);
    };

    echo "\n\033[1mUAT — Coach Staff Override (Phase 3)\033[0m\n";
    echo "Coach #{$coach->id} · Role 'Academic Manager' [courses, coach-students, payout] · Staff #{$staff->id}\n\n";

    // 1) Assign role as-is (all defaults).
    $update([$A->id, $B->id, $C->id]);
    $s = $staff->fresh();
    $check('Role defaults applied → Courses/Students/Payout all allowed',
        $svc->can($s, $A->slug) && $svc->can($s, $B->slug) && $svc->can($s, $C->slug));
    $check('No override rows when identical to role', DB::table('staff_permission_overrides')->where('user_id', $staff->id)->count() === 0);

    // 2) Revoke Payout + grant Reports-export (the spec example).
    $update([$A->id, $B->id, $D->id]);
    $s = $staff->fresh();
    $check('Revoke Payout → BLOCKED',            ! $svc->can($s, $C->slug));
    $check('Grant Reports-export → ALLOWED',     $svc->can($s, $D->slug));
    $check('Untouched Courses/Students still ok', $svc->can($s, $A->slug) && $svc->can($s, $B->slug));
    $check('Override rows recorded (1 grant + 1 revoke)',
        DB::table('staff_permission_overrides')->where('user_id', $staff->id)->count() === 2);
    $check('Role itself unchanged (still has Payout)', $role->fresh()->permissions->contains('id', $C->id));

    // 3) Reset to role → overrides cleared, Payout back, Reports gone.
    $update([$A->id, $B->id, $C->id]);
    $s = $staff->fresh();
    $check('Reset to role → Payout restored',      $svc->can($s, $C->slug));
    $check('Reset to role → Reports-export removed', ! $svc->can($s, $D->slug));
    $check('Reset to role → override rows cleared', DB::table('staff_permission_overrides')->where('user_id', $staff->id)->count() === 0);

    echo implode("\n", $lines) . "\n\n";
    echo "\033[1mResult:\033[0m {$pass} passed, {$fail} failed\n";
    echo ($fail === 0 ? "\033[42;30m UAT PASSED \033[0m\n" : "\033[41;37m UAT FAILED \033[0m\n");
} finally {
    Auth::guard('web')->logout();
    DB::rollBack();
}
exit($fail === 0 ? 0 : 1);
