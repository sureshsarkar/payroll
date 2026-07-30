<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-08 — Enterprise certificate.
 *   1. certificate_builders.certificate_style — 'enterprise' (new default,
 *      framed + sealed + verifiable) or 'classic' (the old drag/background
 *      template, kept for backward compatibility).
 *   2. certificate_credentials — one verifiable record per (student, course).
 *      Its uid backs the QR + the public /verify-certificate/{uid} page.
 * Idempotent so it can be applied by hand on PROD (which does not auto-migrate).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('certificate_builders') && ! Schema::hasColumn('certificate_builders', 'certificate_style')) {
            Schema::table('certificate_builders', function (Blueprint $t) {
                $t->string('certificate_style', 20)->default('enterprise')->after('coach_id');
            });
        }

        if (! Schema::hasTable('certificate_credentials')) {
            Schema::create('certificate_credentials', function (Blueprint $t) {
                $t->id();
                $t->string('uid', 32)->unique();              // public credential code (in the QR + verify URL)
                $t->unsignedBigInteger('user_id')->index();
                $t->unsignedBigInteger('course_id')->index();
                $t->unsignedBigInteger('coach_id')->nullable()->index();
                $t->string('student_name')->nullable();
                $t->string('course_title')->nullable();
                $t->string('coach_name')->nullable();
                $t->date('issued_on')->nullable();
                $t->timestamps();
                $t->unique(['user_id', 'course_id']);          // one credential per student per course
            });
        }
    }

    public function down(): void
    {
        // Additive; leave data in place on rollback.
    }
};
