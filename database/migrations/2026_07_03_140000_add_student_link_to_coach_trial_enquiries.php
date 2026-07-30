<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a trial enquiry to the student account provisioned after payment
 * (2026-07-03). `student_id` = the created/linked student; `student_was_new`
 * distinguishes a freshly-created account from an existing student that was
 * merely linked to this coach (so the coach panel can badge it correctly).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_trial_enquiries')) {
            return;
        }
        Schema::table('coach_trial_enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('coach_trial_enquiries', 'student_id')) {
                $table->unsignedBigInteger('student_id')->nullable()->after('coach_id')->index();
            }
            if (! Schema::hasColumn('coach_trial_enquiries', 'student_was_new')) {
                $table->boolean('student_was_new')->nullable()->after('student_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coach_trial_enquiries')) {
            return;
        }
        Schema::table('coach_trial_enquiries', function (Blueprint $table) {
            foreach (['student_was_new', 'student_id'] as $col) {
                if (Schema::hasColumn('coach_trial_enquiries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
