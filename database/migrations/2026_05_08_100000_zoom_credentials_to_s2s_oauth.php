<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zoom_credentials', function (Blueprint $table) {
            // Server-to-Server OAuth needs an account_id alongside client_id/secret.
            // Encrypted at the model level (see ZoomCredential::$casts).
            if (!Schema::hasColumn('zoom_credentials', 'account_id')) {
                $table->text('account_id')->nullable()->after('instructor_id');
            }
        });

        Schema::table('zoom_credentials', function (Blueprint $table) {
            // S2S has no refresh token — tokens are minted on demand from
            // account_id + client_id + client_secret. Dropping the column
            // also removes a class of "refresh token went dead" failures
            // that motivated 2026-04 onward incident work.
            if (Schema::hasColumn('zoom_credentials', 'zoom_refresh_token')) {
                $table->dropColumn('zoom_refresh_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('zoom_credentials', function (Blueprint $table) {
            if (Schema::hasColumn('zoom_credentials', 'account_id')) {
                $table->dropColumn('account_id');
            }
        });

        Schema::table('zoom_credentials', function (Blueprint $table) {
            if (!Schema::hasColumn('zoom_credentials', 'zoom_refresh_token')) {
                $table->text('zoom_refresh_token')->nullable();
            }
        });
    }
};
