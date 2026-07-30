<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a JSON column to `users` that stores per-event opt-out flags for
 * notifications. Shape after JSON-decode:
 *
 *   {
 *     "lesson_question_replied": { "database": true,  "mail": false, "broadcast": true },
 *     "order_status_changed":    { "database": true,  "mail": true,  "broadcast": true },
 *     ...
 *   }
 *
 * NULL or missing keys = "all channels enabled" (default for existing users).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('verification_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
