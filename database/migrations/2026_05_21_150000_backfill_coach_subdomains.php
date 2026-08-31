<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Per-coach white-label — Phase 6 backfill (historical).
 *
 * LMS removal phase 2 (2026-08-27) — neutralized to a no-op. This was a
 * one-time DATA backfill (no schema change): it auto-assigned a subdomain to
 * every existing coach via App\Services\SubdomainAssigner, which read/wrote
 * coach_domains. Both the service and the white-label coach-site feature are
 * deleted, so this file is left in migration history (already-run on any
 * existing install, tracked in the migrations table) but does nothing on a
 * fresh install rather than fatal-erroring on the missing class.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No-op — see file docblock.
    }

    public function down(): void
    {
        // No-op — see file docblock.
    }
};
