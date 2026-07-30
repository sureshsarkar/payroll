<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 phase 5 — data cleanup.
 *
 * Three production users had role='student' AND coach_id set. This is
 * a corrupt state — coach_id is meant for users who are STAFF acting
 * on behalf of a coach (custom role from CoachStaffRole). A regular
 * student should never have coach_id.
 *
 * Combined with the now-stricter InstructorMiddleware, those students
 * would no longer get illegitimate access; clearing the column also
 * stops anything else that branches on coach_id (e.g.
 * `userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id`
 * in InstructorAnnouncementController) from misbehaving.
 *
 * This migration logs every NULLed row so an operator can revert
 * manually if a row was legit (very unlikely).
 *
 * Idempotent — re-running this is a no-op if no offending rows exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) return;

        $affected = DB::table('users')
            ->where('role', 'student')
            ->whereNotNull('coach_id')
            ->get(['id', 'name', 'email', 'coach_id']);

        if ($affected->isEmpty()) {
            return;
        }

        foreach ($affected as $u) {
            Log::warning('phase 5 cleanup — nulled coach_id on student user', [
                'user_id'     => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'was_coach_id'=> $u->coach_id,
            ]);
        }

        DB::table('users')
            ->where('role', 'student')
            ->whereNotNull('coach_id')
            ->update(['coach_id' => null, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Not reversible — we don't know which rows we touched without
        // re-reading the log. Intentionally a no-op.
    }
};
