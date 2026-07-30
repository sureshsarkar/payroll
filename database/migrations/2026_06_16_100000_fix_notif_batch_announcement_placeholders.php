<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-16 — the announcement email leaked raw Blade. The seeded template
 * used a Blade conditional around the batch line, but the notifier substitutes
 * only {{tokens}} (no Blade compile), so the directives appeared as literal text
 * in the email. Replace the conditional with a single pre-composed {{batch_line}}
 * token (NewBatchAnnouncement builds it — batch name + times, or empty for
 * course-wide).
 *
 * Only rewrites a row that still contains the broken `@if` marker, so a coach/
 * admin who already cleaned up their template by hand is left untouched.
 */
return new class extends Migration
{
    private function cleanMessage(): string
    {
        return <<<HTML
<p>Hi {{user_name}},</p>

<p>{{coach_name}} posted a new announcement {{batch_line}}for <strong>{{course_title}}</strong>:</p>

<p><strong>{{title}}</strong></p>

<blockquote style="border-left:4px solid #5751e1; padding:8px 12px; margin:12px 0; color:#333;">
{{message}}
</blockquote>

<p>Open your dashboard to view the full announcement.</p>
HTML;
    }

    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $row = DB::table('email_templates')->where('name', 'notif_batch_announcement')->first();
        if (! $row) {
            return; // the seeder migration will create it cleanly
        }

        // Fix only if it still has the broken Blade conditional.
        if (str_contains((string) $row->message, '@if') || str_contains((string) $row->message, '$placeholders')) {
            DB::table('email_templates')
                ->where('name', 'notif_batch_announcement')
                ->update(['message' => $this->cleanMessage(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // No-op: we never want the broken @if template back.
    }
};
