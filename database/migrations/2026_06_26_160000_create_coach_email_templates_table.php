<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-26 — per-coach (tenant-specific) email template OVERRIDES. A coach can
 * customise the subject/body of a notification email for their own white-label
 * site. The platform `email_templates` rows stay the DEFAULT: when a coach has
 * no override for a given template key, the platform template is used. Strict
 * tenant isolation — every row is keyed to one coach. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_email_templates')) {
            return;
        }
        Schema::create('coach_email_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id')->index();
            $table->string('name', 120);          // template key, e.g. notif_student_welcome
            $table->string('subject', 255);
            $table->longText('message');
            $table->timestamps();

            // One override per (coach, template).
            $table->unique(['coach_id', 'name'], 'coach_email_tpl_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_email_templates');
    }
};
