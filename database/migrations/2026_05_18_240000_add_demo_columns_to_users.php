<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 Req 2 — demo-user expiry.
 *
 * Adds:
 *   - users.is_demo            (bool, default 0)
 *   - users.demo_expires_at    (datetime, nullable)
 *
 * When an admin marks a user as demo, demo_expires_at is set to
 * created_at + 15 days (or admin-chosen date). After that timestamp,
 * the EnsureDemoUserStillActive middleware blocks access to paid
 * features (course consumption + live class join).
 *
 * Default behaviour: is_demo=0 → unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) return;
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status');
                $table->index('is_demo', 'users_is_demo_idx');
            }
            if (!Schema::hasColumn('users', 'demo_expires_at')) {
                $table->timestamp('demo_expires_at')->nullable()->after('is_demo');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) return;
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'demo_expires_at')) {
                $table->dropColumn('demo_expires_at');
            }
            if (Schema::hasColumn('users', 'is_demo')) {
                $table->dropIndex('users_is_demo_idx');
                $table->dropColumn('is_demo');
            }
        });
    }
};
