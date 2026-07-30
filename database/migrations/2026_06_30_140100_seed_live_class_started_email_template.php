<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the default platform template for the "live class started" email
 * (2026-06-30). notif_* rows auto-appear in Email Settings → Notification
 * templates and are per-coach editable (coach_email_templates). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }
        if (DB::table('email_templates')->where('name', 'notif_live_class_started')->exists()) {
            return;
        }

        $message = <<<HTML
<p>Hello {{user_name}},</p>
<p>Your live class has <strong>started</strong>. Please join now.</p>
<table style="border-collapse:collapse;width:100%;max-width:520px;margin:14px 0;font-size:14px;">
  <tr><td style="padding:6px 10px;color:#6b7280;">Class Title</td><td style="padding:6px 10px;font-weight:600;">{{class_title}}</td></tr>
  <tr><td style="padding:6px 10px;color:#6b7280;">Course</td><td style="padding:6px 10px;">{{course_title}}</td></tr>
  <tr><td style="padding:6px 10px;color:#6b7280;">Batch</td><td style="padding:6px 10px;">{{batch_name}}</td></tr>
  <tr><td style="padding:6px 10px;color:#6b7280;">Teacher / Coach</td><td style="padding:6px 10px;">{{coach_name}}</td></tr>
  <tr><td style="padding:6px 10px;color:#6b7280;">Start Time</td><td style="padding:6px 10px;">{{start_time}}</td></tr>
</table>
<p>Please join the class immediately using the button below.</p>
HTML;

        DB::table('email_templates')->insert([
            'name'       => 'notif_live_class_started',
            'subject'    => 'Your Live Class Has Started – Join Now',
            'message'    => $message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->where('name', 'notif_live_class_started')->delete();
        }
    }
};
