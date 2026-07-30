<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-22 — delivery observability. Records every mail-channel notification
 * send (sent/failed) with its coach context, so admins/coaches get visibility
 * the platform previously lacked (the audit's "no notification-level email
 * log" gap). Written by the LogNotificationEmail listener on the framework's
 * NotificationSent / NotificationFailed events.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_email_logs')) {
            return;
        }

        Schema::create('notification_email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id')->nullable()->index();
            $table->string('notifiable_type')->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->string('recipient_email')->nullable()->index();
            $table->string('notification_class')->index();
            $table->string('title')->nullable();
            $table->string('status', 20)->default('sent')->index();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_email_logs');
    }
};
