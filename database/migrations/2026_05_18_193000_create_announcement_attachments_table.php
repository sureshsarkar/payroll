<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 phase 3 — file attachments on announcements.
 *
 * Coach can attach up to N (controller-enforced) files per announcement
 * — PDFs, images, docs. Stored under storage/app/public/announcements/.
 * Students download via a permission-gated route, NOT a direct URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcement_attachments')) {
            return;
        }
        Schema::create('announcement_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->string('filename');           // original
            $table->string('path');               // storage relative path
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();

            $table->index('announcement_id', 'ann_att_idx');
            $table->foreign('announcement_id')
                ->references('id')->on('announcements')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_attachments');
    }
};
