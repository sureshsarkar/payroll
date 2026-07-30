<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-25 (Super-admin Phase 7 — performance). Hot super-admin queries filter
 * `users` by role+status (dashboard coaches widget COUNTs, active/banned/
 * non-verified customer lists) and by created_at (new-coaches / signup trends).
 * `users.role` was indexed alone, but the composite + created_at were missing —
 * a full table scan at 100k+ users. Idempotent (SHOW INDEX guard) + additive.
 */
return new class extends Migration
{
    private function hasIndex(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }

    public function up(): void
    {
        if (! $this->hasIndex('users', 'users_role_status_idx')) {
            Schema::table('users', function (Blueprint $t) {
                $t->index(['role', 'status'], 'users_role_status_idx');
            });
        }
        if (! $this->hasIndex('users', 'users_created_at_idx')) {
            Schema::table('users', function (Blueprint $t) {
                $t->index('created_at', 'users_created_at_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['users_role_status_idx', 'users_created_at_idx'] as $idx) {
            if ($this->hasIndex('users', $idx)) {
                Schema::table('users', fn (Blueprint $t) => $t->dropIndex($idx));
            }
        }
    }
};
