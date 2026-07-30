<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coach-editable email template for the 1:1 Instant Meeting invite (2026-07-03).
 * `notif_` prefixed so coaches can override it per-site. Idempotent — email_templates.name
 * is NOT unique, so update-then-insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $body = <<<HTML
<p>Hi {{student_name}},</p>
<p><strong>{{coach_name}}</strong> is inviting you to a 1:1 live meeting right now.</p>
<p>{{topic}}</p>
<p>Click the button below to join — your coach is waiting.</p>
HTML;

        $name    = 'notif_instant_meeting_invite';
        $subject = '{{coach_name}} is inviting you to a meeting — Join now';

        DB::table('email_templates')->where('name', $name)->update([
            'subject' => $subject, 'message' => $body, 'updated_at' => now(),
        ]);
        $exists = DB::table('email_templates')->where('name', $name)->exists();
        if (! $exists) {
            DB::table('email_templates')->insert([
                'name' => $name, 'subject' => $subject, 'message' => $body,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->where('name', 'notif_instant_meeting_invite')->delete();
        }
    }
};
