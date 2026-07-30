<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression tripwire for tasks B + F (2026-05-12).
 *
 * B — Coach panel IDOR sweep. Three places used raw Model::find($id)
 *     without ownership checks, letting one coach read/write another
 *     coach's data by guessing ids. Plus one plaintext verification
 *     token (same family as the C2 password-reset fix). Verified
 *     against the actual source so a future refactor that "simplifies"
 *     these helpers re-introduces the IDOR has a failing test waiting.
 *
 * F — DashboardController had three uncached queries running on every
 *     admin dashboard hit: the monthly chart aggregate, Order::min,
 *     and Order::max for the year range. Now all three are cached.
 *     Mirror of the existing Cache::remember pattern in the same file.
 */
class CoachPanelIdorAndDashboardPerfTest extends TestCase
{
    /* ─────────────────────────────────────────────────────── B ── */

    public function test_my_students_controller_scopes_by_owner(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorDashboardController.php')
        );

        // findOwnedStudentOrFail helper must exist.
        $this->assertStringContainsString(
            'function findOwnedStudentOrFail',
            $src,
            'InstructorDashboardController must have findOwnedStudentOrFail() — pre-fix, editStudents/updateStudents/destroy did raw User::find($id) and let any coach modify any user'
        );

        // Helper must scope by both added_by AND coach_id wrapped in a closure
        // (not a sibling ->whereOr chain that would leak past the id constraint).
        $this->assertMatchesRegularExpression(
            '/->where\(\s*function\s*\(\$q\)\s*use\s*\(\$coachId\)\s*\{\s*\$q->where\([\'"]added_by[\'"]/',
            $src,
            'findOwnedStudentOrFail must wrap the added_by/coach_id OR clause in an inner closure — sibling ->whereOr chains leak past the id constraint'
        );

        // The three previously-vulnerable methods must call the helper.
        foreach (['editStudents', 'updateStudents', 'destroy'] as $method) {
            $offset = strpos($src, "function {$method}");
            $this->assertNotFalse($offset, "{$method} method missing");
            $body = substr($src, $offset, 1500);
            $this->assertStringContainsString(
                'findOwnedStudentOrFail',
                $body,
                "InstructorDashboardController::{$method}() must call findOwnedStudentOrFail() — raw User::find() is an IDOR vector"
            );
        }

        // updateStudents must also validate request input — pre-fix it took
        // raw $request->name / email / status with no validation, so a coach
        // could pass `status=banned` and quietly demote a user.
        $offset = strpos($src, 'function updateStudents');
        $body = substr($src, $offset, 1500);
        $this->assertStringContainsString(
            "\$request->validate(",
            $body,
            'updateStudents() must validate request input — pre-fix it accepted any value for status without an enum check'
        );
    }

    public function test_coach_staff_role_index_scopes_by_added_by(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/CoachStaffRoleController.php')
        );

        $offset = strpos($src, 'function index');
        $this->assertNotFalse($offset);
        $body = substr($src, $offset, 1500);

        $this->assertMatchesRegularExpression(
            '/(?:->|::)where\([\'"]added_by[\'"]\s*,\s*\$coachId\)/',
            $body,
            "CoachStaffRoleController::index() must filter by added_by=\$coachId — pre-fix it paginated unscoped and leaked every coach's roles to every other coach"
        );
    }

    public function test_coach_staff_controller_hashes_verification_token(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/CoachStaffController.php')
        );

        // Pre-fix: $user->verification_token = Str::random(100);
        // Post-fix: hash('sha256', bin2hex(random_bytes(32)))
        $this->assertDoesNotMatchRegularExpression(
            '/->verification_token\s*=\s*Str::random\(/',
            $src,
            "CoachStaffController must not assign Str::random() directly to verification_token — same plaintext-storage vector as the C2 password-reset issue"
        );
        $this->assertMatchesRegularExpression(
            '/->verification_token\s*=\s*hash\(\s*[\'"]sha256[\'"]/',
            $src,
            'CoachStaffController must hash the verification_token via sha256 — defense-in-depth against DB-read leaks'
        );
    }

    /* ─────────────────────────────────────────────────────── F ── */

    public function test_dashboard_chart_query_is_cached(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Admin/DashboardController.php')
        );

        $offset = strpos($src, 'function dashboard');
        $body = substr($src, $offset, 15000);

        // The monthly-chart aggregate must be inside a Cache::remember.
        // The cache key must vary by the filter window so a different
        // year/month doesn't return stale data.
        $this->assertMatchesRegularExpression(
            '/Cache::remember\(\s*\$cacheKey\s*,\s*60\s*,\s*function/',
            $body,
            "Monthly-chart aggregate must be wrapped in Cache::remember(\$cacheKey, 60, …) — pre-fix it ran on every dashboard load"
        );
        $this->assertStringContainsString(
            'admin.dashboard.chart:',
            $body,
            "Chart cache key must be namespaced under admin.dashboard.chart so it doesn't collide with other cache entries"
        );
    }

    public function test_dashboard_year_range_uses_min_max_not_order_by(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Admin/DashboardController.php')
        );

        // Pre-fix: Order::orderBy('created_at', 'asc')->first()?->created_at
        // Post-fix: Order::min('created_at')
        // The orderBy()->first() pattern adds a LIMIT 1 + ORDER BY plan;
        // MIN/MAX with an index is a single index seek.
        $this->assertDoesNotMatchRegularExpression(
            "/Order::orderBy\(\s*'created_at'\s*,\s*'asc'\s*\)->first\(\)/",
            $src,
            "DashboardController must not use Order::orderBy('created_at','asc')->first() for oldest year — use Order::min('created_at') instead and cache the result"
        );
        $this->assertStringContainsString(
            "Order::min('created_at')",
            $src,
            'DashboardController must use Order::min() for the oldest-year query'
        );
        $this->assertStringContainsString(
            "Order::max('created_at')",
            $src,
            'DashboardController must use Order::max() for the latest-year query'
        );

        // Year-range result must be cached (it changes only when a new
        // year's first order arrives — once per year for most projects).
        $this->assertStringContainsString(
            "'admin.dashboard.year-range'",
            $src,
            'Year-range query must be cached under admin.dashboard.year-range'
        );
    }

    /**
     * Regression: /instructor/dashboard 500ed on 2026-05-19 because the
     * Today's-pulse aggregator joined order_items + orders and
     * referenced commission_rate (and three sibling columns) without a
     * table prefix. Both tables carry these columns, so MySQL threw
     * SQLSTATE[23000] 1052 "Column 'commission_rate' is ambiguous".
     *
     * We replicate the EXACT SQL the controller issues and assert it
     * runs without throwing. The HTTP-level assertion is brittle in
     * mbs_test because the global settings cache may be missing the
     * keys the master layout reads (logo, maintenance_mode); that's
     * a separate seeding concern.
     *
     * If the pulse SQL ever loses its `o.` prefix again, this fails
     * before prod.
     */
    public function test_instructor_pulse_sql_does_not_have_ambiguous_columns(): void
    {
        $instructor = \App\Models\User::factory()->create(['role' => 'instructor']);

        $courseId = \DB::table('courses')->insertGetId([
            'title'         => 'Pulse smoke '.uniqid(),
            'slug'          => 'pulse-smoke-'.uniqid(),
            'instructor_id' => $instructor->id,
            'added_by'      => $instructor->id,
            'is_approved'   => 'approved',
            'status'        => 'active',
            'price'         => 49.99, 'discount' => 0,
            'created_at'    => now(), 'updated_at' => now(),
        ]);

        $student = \App\Models\User::factory()->create(['role' => 'student']);

        $order = \Modules\Order\app\Models\Order::create([
            'invoice_id'              => 'INV-'.uniqid('p'),
            'transaction_id'          => 'TRX-'.uniqid('p'),
            'buyer_id'                => $student->id,
            'has_coupon'              => 0,
            'coupon_code'             => '',
            'coupon_discount_percent' => '',
            'coupon_discount_amount'  => 0,
            'payment_method'          => 'test',
            'payment_status'          => 'paid',
            'payable_amount'          => 49.99,
            'gateway_charge'          => 0,
            'payable_with_charge'     => 49.99,
            'paid_amount'             => 49.99,
            'payable_currency'        => 'INR',
            'conversion_rate'         => 1,
            'commission_rate'         => 10,
            'order_type'              => 'course',
        ]);

        \DB::table('order_items')->insert([
            'order_id'  => $order->id,
            'course_id' => $courseId,
            'price'     => 49.99,
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        try {
            // 1. Pulse SQL — exactly what the controller issues.
            //    Pre-fix this threw SQLSTATE 1052 ambiguous column.
            $earningsExpr = '((o.payable_amount + o.gateway_charge) - o.coupon_discount_amount) * (o.commission_rate/100)';
            $sumToday = (float) \DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->whereIn('oi.course_id', [$courseId])
                ->where('o.payment_status', 'paid')
                ->whereDate('o.created_at', now()->toDateString())
                ->selectRaw("SUM(o.paid_amount - ($earningsExpr)) s")
                ->value('s') ?? 0.0;

            $this->assertGreaterThanOrEqual(0.0, $sumToday,
                'pulse SQL must return a numeric result, not throw');

            // 2. Same query without any column prefix would 1052 —
            //    assert the prefixed expression is the one in the
            //    controller source so this protection can't be silently
            //    reverted.
            $src = (string) file_get_contents(
                app_path('Http/Controllers/Frontend/InstructorDashboardController.php')
            );
            $this->assertStringContainsString(
                'o.commission_rate',
                $src,
                'InstructorDashboardController pulse SQL must reference o.commission_rate (table-prefixed). '.
                'Pre-fix the unprefixed column was ambiguous with order_items.commission_rate and threw SQLSTATE 1052.'
            );
            $this->assertStringContainsString(
                'o.payable_amount',
                $src,
                'pulse SQL must use o.-prefixed columns throughout'
            );
        } finally {
            \DB::table('order_items')->where('order_id', $order->id)->delete();
            \DB::table('orders')->where('id', $order->id)->delete();
            \DB::table('courses')->where('id', $courseId)->delete();
            \DB::table('users')->whereIn('id', [$instructor->id, $student->id])->delete();
        }
    }
}
