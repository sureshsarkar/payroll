<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds 2FA / TOTP columns to the admins table.
 *
 * - two_factor_secret: the 32-char shared secret used by the authenticator app.
 *                     Encrypted at rest via Laravel's encryption layer in the
 *                     Admin model's $casts.
 * - two_factor_recovery_codes: 8 one-time backup codes (encrypted JSON array).
 * - two_factor_enabled_at: NULL = not yet enrolled. Non-null = 2FA active.
 * - two_factor_confirmed_at: when the admin first verified a code from their app
 *                            (proves enrollment was completed, not abandoned).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_enabled_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_enabled_at',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
