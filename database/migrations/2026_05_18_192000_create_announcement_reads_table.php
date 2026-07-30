<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 phase 3 — read-receipt log.
 *
 * One row per (announcement, user) pair the first time the student
 * opens the announcement. Subsequent views are no-ops (UNIQUE
 * (announcement_id, user_id)).
 *
 * Coaches see a "X / Y read" badge on their announcement list and
 * can drill into a per-student read-status list.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcement_reads')) {
            return;
        }

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id'], 'ann_reads_unique');
            $table->index('user_id', 'ann_reads_user_idx');

            $table->foreign('announcement_id')
                ->references('id')->on('announcements')
                ->onDelete('cascade');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
    }
};
