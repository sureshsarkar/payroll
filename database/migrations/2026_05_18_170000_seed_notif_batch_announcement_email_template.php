<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 — seed email_templates row for the
 * NewBatchAnnouncement notification.
 *
 * Without this row the InAppNotification::toMail() fallback fires
 * a plain HTML email — functional but ugly + doesn't honor the
 * placeholder variables. Seeding gives admins a row they can edit
 * via the existing email-template editor.
 *
 * Idempotent — uses updateOrInsert keyed on `name`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        $subject = 'New announcement — {{course_title}}';
        $message = <<<HTML
<p>Hi {{user_name}},</p>

<p>{{coach_name}} posted a new announcement {{batch_line}}for <strong>{{course_title}}</strong>:</p>

<p><strong>{{title}}</strong></p>

<blockquote style="border-left:4px solid #5751e1; padding:8px 12px; margin:12px 0; color:#333;">
{{message}}
</blockquote>

<p>Open your dashboard to view the full announcement.</p>
HTML;

        DB::table('email_templates')->updateOrInsert(
            ['name' => 'notif_batch_announcement'],
            [
                'subject'    => $subject,
                'message'    => $message,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->where('name', 'notif_batch_announcement')->delete();
        }
    }
};
