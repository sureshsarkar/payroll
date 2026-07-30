<?php

namespace Tests\Feature\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PaymentFulfilmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Tests\TestCase;

/**
 * Enterprise H-A — activity / audit log.
 */
class ActivityLogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_table_and_permission_exist(): void
    {
        $this->assertTrue(Schema::hasTable('activity_logs'), 'activity_logs table must exist');
        foreach (['actor_id', 'actor_name', 'actor_role', 'action', 'module', 'subject_type',
                  'subject_id', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at'] as $col) {
            $this->assertTrue(Schema::hasColumn('activity_logs', $col), "activity_logs.$col missing");
        }
        $this->assertTrue(
            DB::table('permissions')->where('name', 'activity-log.view')->where('guard_name', 'admin')->exists(),
            'activity-log.view permission must be seeded'
        );
    }

    public function test_logger_writes_row_and_strips_secrets(): void
    {
        $before = ActivityLog::count();

        ActivityLogger::log(
            ActivityLog::UPDATED,
            'user',
            null,
            ['name' => 'Old', 'password' => 'secret-should-not-log'],
            ['name' => 'New', 'password' => 'new-secret', 'remember_token' => 'tok'],
            'unit test'
        );

        $this->assertSame($before + 1, ActivityLog::count());
        $row = ActivityLog::latest('id')->first();
        $this->assertSame('updated', $row->action);
        $this->assertSame('user', $row->module);
        $this->assertSame('unit test', $row->description);
        // Secrets stripped from both diffs.
        $this->assertArrayNotHasKey('password', $row->old_values);
        $this->assertArrayNotHasKey('password', $row->new_values);
        $this->assertArrayNotHasKey('remember_token', $row->new_values);
        $this->assertSame('New', $row->new_values['name']);
    }

    public function test_logger_never_throws_on_bad_input(): void
    {
        // Even if something is off, logging must never bubble an exception.
        ActivityLogger::log('weird_action_' . str_repeat('x', 200), null, null, null, null, null);
        $this->assertTrue(true);
    }

    public function test_markpaid_writes_payment_audit_log(): void
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null, 'wallet_balance' => 0]);
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Audit course ' . uniqid(), 'slug' => 'al-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'course', 'price' => 100, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $order = Order::create([
            'invoice_id' => 'AL-' . uniqid(), 'buyer_id' => $student->id,
            'status' => 'pending', 'payment_status' => 'pending', 'payment_method' => 'test',
            'payable_amount' => 100, 'paid_amount' => 100, 'commission_rate' => 10,
        ]);
        OrderItem::create(['order_id' => $order->id, 'course_id' => $courseId, 'price' => 100, 'commission_rate' => 10]);

        app(PaymentFulfilmentService::class)->markPaid($order, 'txn_' . uniqid(), 'details');

        $log = ActivityLog::where('action', ActivityLog::PAYMENT_STATUS_CHANGED)
            ->where('subject_id', $order->id)
            ->where('module', 'order')
            ->latest('id')->first();

        $this->assertNotNull($log, 'markPaid must write a payment audit log');
        $this->assertSame('pending', $log->old_values['payment_status']);
        $this->assertSame('paid', $log->new_values['payment_status']);
    }
}
