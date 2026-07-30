<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 phase 3 — email template for low-attendance alerts to coaches.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) return;

        DB::table('email_templates')->updateOrInsert(
            ['name' => 'notif_low_attendance_alert'],
            [
                'subject'    => 'Low attendance alert — {{batch_title}}',
                'message'    => <<<HTML
<p>Hi {{user_name}},</p>

<p>Today's attendance for <strong>{{batch_title}}</strong> was below the
healthy threshold:</p>

<ul>
  <li><strong>{{attended}}</strong> of <strong>{{total_students}}</strong> students attended ({{percent}}%)</li>
</ul>

<p>You can review the per-student roster and follow up with absentees from the
batch attendance dashboard.</p>
HTML,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->where('name', 'notif_low_attendance_alert')->delete();
        }
    }
};
