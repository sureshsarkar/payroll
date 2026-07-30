<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 2026-06-05 — Enrich the "live class scheduled" email so it carries the full
 * context the student needs: Course, BATCH, Live Class title, Date & Time and
 * Coach. (Previously it only showed course + lesson + start time, and was sent
 * course-wide; the recipient set is now batch-specific.)
 *
 * NOTE: we intentionally route the CTA to the student panel (handled by the
 * email layout's button) rather than embedding the raw Zoom join link, so the
 * "wait for the coach / countdown" gate cannot be bypassed from the email.
 *
 * Idempotent updateOrInsert on the admin-editable email_templates row, so
 * already-deployed installs pick up the new copy without losing the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->updateOrInsert(
            ['name' => 'notif_live_class_scheduled'],
            [
                'subject'    => 'New Live Class Scheduled: {{lesson_title}}',
                'message'    => '<p>Dear {{user_name}},</p>
<p>A new live class has been scheduled for your enrolled batch.</p>
<p><strong>Course:</strong> {{course_title}}<br>
<strong>Batch:</strong> {{batch_name}}<br>
<strong>Live Class:</strong> {{lesson_title}}<br>
<strong>Date &amp; Time:</strong> {{start_time}}<br>
<strong>Coach:</strong> {{coach_name}}</p>
<p>Please join the class on time from your student panel using the button below.</p>',
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // Revert to the pre-enrichment copy.
        DB::table('email_templates')->where('name', 'notif_live_class_scheduled')->update([
            'subject'    => 'New live class: {{course_title}}',
            'message'    => '<p>Hi {{user_name}},</p>
<p>A new live class has been scheduled in <strong>{{course_title}}</strong>.</p>
<p><strong>Lesson:</strong> {{lesson_title}}<br>
<strong>Starts:</strong> {{start_time}}</p>
<p>Add it to your calendar — we will remind you before it begins.</p>',
            'updated_at' => now(),
        ]);
    }
};
