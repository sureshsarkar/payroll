<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an expiry timestamp to password-reset tokens.
 *
 * Pre-audit `users.forget_password_token` was a long-lived random string
 * with no TTL — a leaked reset link from an old email or a stale shared
 * URL stayed valid forever. We now stamp an `expires_at` on issue and
 * gate `resetPassword` against it.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'forget_password_token_expires_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('forget_password_token_expires_at')
                    ->nullable()
                    ->after('forget_password_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'forget_password_token_expires_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('forget_password_token_expires_at');
            });
        }
    }
};
