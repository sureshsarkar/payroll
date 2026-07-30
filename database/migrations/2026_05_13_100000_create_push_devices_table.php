<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1A of the Student mobile-app build (2026-05-13) — per-user push
 * notification token registry. The mobile app registers a token on login
 * (FCM for Android / APNs token bridged via Firebase for iOS) so the
 * existing notification pipeline can fan-out to it.
 *
 * Why a dedicated table rather than reusing the existing webpush
 * subscription model: webpush is browser-PUSH bound to a subscription
 * object (endpoint + keys), while FCM/APNs are bare device-token strings.
 * The two don't share a schema cleanly.
 *
 * Indexed for the two access patterns we have:
 *   - "all devices for this user" (broadcast)
 *   - "find device by token" (de-dup + unregister on logout)
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('push_devices')) {
            return;
        }
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token', 500);
            $table->enum('platform', ['fcm', 'apns', 'web']);
            $table->string('device_name', 120)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        // First 191 chars of the token are unique — MySQL key-length limit
        // on utf8mb4 (191 × 4 = 764 bytes < 1000-byte index limit on older
        // MyISAM defaults). Real tokens are well within that prefix.
        $exists = collect(DB::select("SHOW INDEX FROM push_devices WHERE Key_name = 'push_devices_token_unique'"))->isNotEmpty();
        if (!$exists) {
            DB::statement('CREATE UNIQUE INDEX push_devices_token_unique ON push_devices (token(191))');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('push_devices')) {
            $exists = collect(DB::select("SHOW INDEX FROM push_devices WHERE Key_name = 'push_devices_token_unique'"))->isNotEmpty();
            if ($exists) {
                DB::statement('DROP INDEX push_devices_token_unique ON push_devices');
            }
            Schema::drop('push_devices');
        }
    }
};
