<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feature: attendance status codes.
 *
 * The attendance `status` column moves from the free-form set
 * (Present / Absent / HalfDay / Leave / Holiday / WFH) to the two-half codes
 * PP / AP / PA / AA. A companion nullable `day_type` column carries the pay
 * treatment / reason (wfh, holiday, paid_leave, unpaid_leave) that the four
 * codes alone can't express, so paid leave and holidays still score zero
 * loss-of-pay.
 *
 * Existing rows are backfilled:
 *   Present → PP                Absent  → AA
 *   HalfDay → PA                Holiday → AA + day_type=holiday
 *   WFH     → PP + day_type=wfh Leave   → AA + day_type=paid_leave
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendances', 'day_type')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->string('day_type', 20)->nullable()->after('status');
                $table->index('day_type');
            });
        }

        // Order matters: tag the reason rows off their old status value first,
        // then collapse the plain present/absent/half-day rows.
        DB::table('attendances')->where('status', 'WFH')->update(['day_type' => 'wfh', 'status' => 'PP']);
        DB::table('attendances')->where('status', 'Holiday')->update(['day_type' => 'holiday', 'status' => 'AA']);
        DB::table('attendances')->where('status', 'Leave')->update(['day_type' => 'paid_leave', 'status' => 'AA']);
        DB::table('attendances')->where('status', 'HalfDay')->update(['status' => 'PA']);
        DB::table('attendances')->where('status', 'Absent')->update(['status' => 'AA']);
        DB::table('attendances')->where('status', 'Present')->update(['status' => 'PP']);

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('status', 20)->default('PP')->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('status', 20)->default('Present')->change();
        });

        // Best-effort reverse: day_type wins where it was set.
        DB::table('attendances')->where('day_type', 'wfh')->update(['status' => 'WFH']);
        DB::table('attendances')->where('day_type', 'holiday')->update(['status' => 'Holiday']);
        DB::table('attendances')->where('day_type', 'paid_leave')->update(['status' => 'Leave']);
        DB::table('attendances')->where('day_type', 'unpaid_leave')->update(['status' => 'Absent']);
        DB::table('attendances')->whereNull('day_type')->where('status', 'PP')->update(['status' => 'Present']);
        DB::table('attendances')->whereNull('day_type')->whereIn('status', ['AP', 'PA'])->update(['status' => 'HalfDay']);
        DB::table('attendances')->whereNull('day_type')->where('status', 'AA')->update(['status' => 'Absent']);

        if (Schema::hasColumn('attendances', 'day_type')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropIndex(['day_type']);
                $table->dropColumn('day_type');
            });
        }
    }
};
