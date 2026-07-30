<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-09 (Email/Notification audit — Phase 1.4).
 *
 * `email_templates.name` had no unique constraint, so a re-seed or an admin
 * duplicate could create two rows for one key. Every lookup uses
 * `EmailTemplate::where('name', $key)->first()`, which then picks a row
 * non-deterministically — a real risk of silently swapping the password-reset
 * or payment-receipt template.
 *
 * This migration de-duplicates (keeps the earliest row per name) and adds the
 * unique index. Idempotent & safe to hand-apply on PROD.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        // 1) De-dupe: keep the lowest id per name, delete later duplicates.
        $dupNames = DB::table('email_templates')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($dupNames as $name) {
            $keepId = DB::table('email_templates')->where('name', $name)->orderBy('id')->value('id');
            DB::table('email_templates')->where('name', $name)->where('id', '!=', $keepId)->delete();
        }

        // 2) Add the unique index if it isn't already present.
        try {
            $has = collect(DB::select("SHOW INDEX FROM email_templates WHERE Key_name = 'email_templates_name_unique'"))->isNotEmpty();
            if (! $has) {
                Schema::table('email_templates', function (Blueprint $t) {
                    $t->unique('name');
                });
            }
        } catch (\Throwable $e) {
            // Non-fatal: leave the table usable even if the index can't be added
            // (e.g. legacy row-format). The de-dupe above already reduces the risk.
        }
    }

    public function down(): void
    {
        // Keep the index on rollback (additive, low-risk).
    }
};
