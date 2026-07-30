<?php
/**
 * UAT — Coach Permission Resolver (Phase 1). Runs a real coach → role → staff →
 * override scenario against the DB and prints a PASS/FAIL report. Everything runs
 * inside a transaction that is ROLLED BACK, so it leaves no data behind.
 *
 *   DB_DATABASE=mbs_test /d/xampp/php/php.exe <this file>
 */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\CoachStaffRole;
use App\Models\User;
use App\Services\CoachPermissionService;
use Illuminate\Support\Facades\DB;

$svc  = app(CoachPermissionService::class);
$pass = 0; $fail = 0; $lines = [];
$check = function (string $name, bool $ok) use (&$pass, &$fail, &$lines) {
    $ok ? $pass++ : $fail++;
    $lines[] = ($ok ? "  \033[32mPASS\033[0m  " : "  \033[31mFAIL\033[0m  ") . $name;
};

DB::beginTransaction();
try {
    $mkUser = function (string $role, ?int $coachId = null) {
        return DB::table('users')->insertGetId([
            'role' => $role, 'name' => ucfirst($role) . ' ' . uniqid(), 'email' => substr($role,0,3) . uniqid() . '@uat.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'coach_id' => $coachId, 'added_by' => $coachId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    $mkPerm = fn (string $slug) => CoachStaffPermission::create(['name' => ucfirst($slug), 'slug' => $slug . '-' . uniqid()]);

    // ── Scenario: Academic Manager who must NOT access Payout, but CAN export reports ──
    $coach   = User::find($mkUser('instructor'));
    $coachB  = User::find($mkUser('instructor'));

    $pCourses = $mkPerm('courses');
    $pStudents= $mkPerm('coach-students');
    $pPayout  = $mkPerm('payout');
    $pReport  = $mkPerm('reports-export');   // not in the role initially

    $role  = CoachStaffRole::create(['role_name' => 'Academic Manager', 'role_slug' => 'academic-manager-'.uniqid(), 'added_by' => $coach->id, 'status' => 1]);
    $role->permissions()->attach([$pCourses->id, $pStudents->id, $pPayout->id]);

    $staff = CoachStaff::find($mkUser('Academic Manager', $coach->id));

    echo "\n\033[1mUAT — Coach Permission Resolver (Phase 1)\033[0m\n";
    echo "Coach #{$coach->id} · Role 'Academic Manager' [courses, coach-students, payout] · Staff #{$staff->id}\n\n";

    // 1) Real coach = full access
    $check('Real coach has unrestricted access', $svc->isRealCoach($coach) && $svc->can($coach, 'anything'));

    // 2) Fresh staff, before sync = deny all
    $check('New staff denied before any sync', ! $svc->can($staff->fresh(), $pCourses->slug));

    // 3) Assign role only → inherits all 3 role permissions
    $svc->syncStaff($staff, $role->id, [], [], $coach->id);
    $s = $staff->fresh();
    $check('Role assigned → can access Courses',        $svc->can($s, $pCourses->slug));
    $check('Role assigned → can access Students',        $svc->can($s, $pStudents->slug));
    $check('Role assigned → can access Payout (default)', $svc->can($s, $pPayout->slug));
    $check('Role assigned → CANNOT export reports yet',  ! $svc->can($s, $pReport->slug));

    // 4) Override: REVOKE Payout, GRANT reports-export (the exact spec example)
    $svc->syncStaff($staff, $role->id, [$pReport->id], [$pPayout->id], $coach->id);
    $s = $staff->fresh();
    $check('Override revoke → Payout now BLOCKED',        ! $svc->can($s, $pPayout->slug));
    $check('Override grant  → Reports export ALLOWED',    $svc->can($s, $pReport->slug));
    $check('Untouched perms still work (Courses)',        $svc->can($s, $pCourses->slug));
    $check('Untouched perms still work (Students)',       $svc->can($s, $pStudents->slug));
    $check('Original ROLE unchanged (payout still on role)', $role->fresh()->permissions->contains('id', $pPayout->id));

    // 5) Reset overrides → back to role defaults
    $svc->syncStaff($staff, $role->id, [], [], $coach->id);
    $s = $staff->fresh();
    $check('Reset overrides → Payout restored',          $svc->can($s, $pPayout->slug));
    $check('Reset overrides → Reports export removed',   ! $svc->can($s, $pReport->slug));

    // 6) Inactive role → grants nothing
    $role->update(['status' => 0]);
    $svc->syncStaff($staff, $role->id, [], [], $coach->id);
    $check('Inactive role → all access removed', ! $svc->can($staff->fresh(), $pCourses->slug));
    $role->update(['status' => 1]);

    // 7) Cross-coach isolation
    $staffB = CoachStaff::find($mkUser('Manager', $coachB->id));
    $svc->syncStaff($staff, $role->id, [], [], $coach->id);
    $check("Coach B's staff cannot see Coach A's permission", ! $svc->can($staffB->fresh(), $pCourses->slug));
    $check('Owning coach id resolves correctly for staff',    $svc->coachIdFor($staff) === $coach->id);

    echo implode("\n", $lines) . "\n\n";
    echo "\033[1mResult:\033[0m {$pass} passed, {$fail} failed\n";
    echo ($fail === 0 ? "\033[42;30m UAT PASSED \033[0m\n" : "\033[41;37m UAT FAILED \033[0m\n");
} finally {
    DB::rollBack();   // leave no trace
}
exit($fail === 0 ? 0 : 1);
