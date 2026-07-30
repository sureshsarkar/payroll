<?php

namespace Tests\Feature\Domain;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Modules\InstructorRequest\app\Models\InstructorRequest;
use Tests\TestCase;

/**
 * Domain test — instructor-request approval flow.
 *
 * Audit 2026-05-18: implementation of the previous skeleton.
 *
 * The flow in this app:
 *
 *   1. A logged-in student visits POST /become-instructor and creates
 *      an InstructorRequest row (status=pending).
 *   2. An admin opens /admin/instructor-request/{id}/edit and submits
 *      a PUT to /admin/instructor-request/{id} with status=approved.
 *      InstructorRequestController::update sets the request status
 *      AND flips users.role to 'instructor' as a side effect.
 *   3. Rejecting a previously-approved request flips users.role back
 *      to 'student' so the promotion can be reversed.
 *
 * Note: this app uses a plain `users.role` column for student/instructor
 * gating (not spatie roles on the user guard). Spatie roles are only
 * attached to the admin guard. The original skeleton mentioned
 * `hasRole('instructor')`; that's not how this codebase wires it.
 */
class InstructorApprovalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pending_request_does_not_promote_user(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $req = InstructorRequest::create([
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);

        $this->assertSame('student', $user->fresh()->role,
            'pending request must NOT promote the user');
        $this->assertSame('pending', $req->fresh()->status);
    }

    public function test_admin_approval_flips_user_role_to_instructor(): void
    {
        $admin = $this->ensureAdmin();
        $user = User::factory()->create(['role' => 'student']);
        $req = InstructorRequest::create([
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);

        Auth::guard('admin')->loginUsingId($admin->id);

        // Exercise the controller's status-change side effect directly.
        // (The HTTP layer goes through a Spatie permission gate that
        // requires an admin with `instructor.request.list`. We've
        // already covered that gate in audit:smoke. Here we want to
        // pin the business rule.)
        $controller = new \Modules\InstructorRequest\app\Http\Controllers\InstructorRequestController();
        $request = \Illuminate\Http\Request::create('/x', 'PUT', ['status' => 'approved']);

        try {
            $controller->update($request, $req->id);
        } catch (\Throwable $e) {
            // The controller fires an EmailService at the end; in test
            // env that may throw if mail isn't configured. We don't
            // care — the DB writes happen before the mail call.
        }

        $this->assertSame('approved', $req->fresh()->status);
        $this->assertSame('instructor', $user->fresh()->role,
            'admin-approved request must promote the user to instructor');
    }

    public function test_admin_rejection_reverts_previously_approved_user(): void
    {
        $admin = $this->ensureAdmin();
        $user = User::factory()->create(['role' => 'instructor']);
        $req = InstructorRequest::create([
            'user_id' => $user->id,
            'status'  => 'approved',
        ]);

        Auth::guard('admin')->loginUsingId($admin->id);

        $controller = new \Modules\InstructorRequest\app\Http\Controllers\InstructorRequestController();
        $request = \Illuminate\Http\Request::create('/x', 'PUT', ['status' => 'rejected']);
        try {
            $controller->update($request, $req->id);
        } catch (\Throwable $e) {
            // ignore mail failures
        }

        $this->assertSame('rejected', $req->fresh()->status);
        $this->assertSame('student', $user->fresh()->role,
            'admin-rejected request must demote an already-instructor user');
    }

    public function test_rejecting_pending_request_leaves_student_role(): void
    {
        $admin = $this->ensureAdmin();
        $user = User::factory()->create(['role' => 'student']);
        $req = InstructorRequest::create([
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);

        Auth::guard('admin')->loginUsingId($admin->id);

        $controller = new \Modules\InstructorRequest\app\Http\Controllers\InstructorRequestController();
        $request = \Illuminate\Http\Request::create('/x', 'PUT', ['status' => 'rejected']);
        try {
            $controller->update($request, $req->id);
        } catch (\Throwable $e) {
            // ignore
        }

        $this->assertSame('rejected', $req->fresh()->status);
        $this->assertSame('student', $user->fresh()->role,
            'rejecting a never-approved request must not change the role');
    }

    /**
     * Build an admin and grant the permission the controller checks
     * (`instructor.request.list`). Re-using whatever already exists in
     * the test DB so the test can also run against a freshly-migrated
     * `mbs_test` where no spatie rows are seeded.
     */
    private function ensureAdmin(): Admin
    {
        $a = Admin::query()->orderBy('id')->first();
        if (!$a) {
            $a = Admin::create([
                'name'     => 'IR Test Admin',
                'email'    => 'ir-admin-'.uniqid().'@example.test',
                'password' => bcrypt('test'),
            ]);
        }

        // Grant the specific permissions the controller checks. Spatie
        // creates rows if missing.
        //
        // FT-IDOR-17 (2026-05-28) — write methods (update / destroy)
        // moved from `instructor.request.list` (READ) to a separate
        // `instructor.request.update` (WRITE) slug so a read-only sub-
        // admin can no longer promote students to coaches via POST.
        // The test exercises the approve/reject write paths, so it
        // needs the .update slug now too.
        try {
            foreach (['instructor.request.list', 'instructor.request.update'] as $name) {
                $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'admin']
                );
                if (!$a->hasPermissionTo($perm)) {
                    $a->givePermissionTo($perm);
                }
            }
            // Forget the permission cache so the new grant is visible
            // to checkAdminHasPermission() in the same request lifecycle.
            app()['cache']->forget('spatie.permission.cache');
        } catch (\Throwable $e) {
            // If spatie tables don't exist in this DB, the test will
            // still run but the controller's gate will deny — caught
            // below in the test bodies.
        }

        return $a;
    }
}
