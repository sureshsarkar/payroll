<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit fix C2 (2026-05-12) — defense-in-depth for the admin password-reset
 * flow. Two changes work together:
 *
 *   1. Token is hashed (sha256) before storage; the raw token only ever
 *      lives in the email URL. A DB read leak no longer leaks usable
 *      reset links. (Logic change is in PasswordResetLinkController +
 *      NewPasswordController; this migration only adds the expiry column.)
 *
 *   2. Tokens auto-expire after 1 hour. Previously, an unused token would
 *      sit valid forever — an attacker who recovered an old backup could
 *      still use it months later. Mirrors the equivalent column on the
 *      users table that AuditSmoke already enforces.
 *
 * Column is nullable so existing rows (and tokens) continue to work.
 * Idempotent: checked with hasColumn() before adding.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('admins')) {
            return;
        }
        if (!Schema::hasColumn('admins', 'forget_password_token_expires_at')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->timestamp('forget_password_token_expires_at')
                    ->nullable()
                    ->after('forget_password_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('admins', 'forget_password_token_expires_at')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('forget_password_token_expires_at');
            });
        }
    }
};
