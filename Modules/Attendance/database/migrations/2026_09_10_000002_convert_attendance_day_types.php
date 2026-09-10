<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Feature: attendance Day Type options.
 *
 * The nullable `attendances.day_type` column moves from the pay-treatment set
 * (wfh / holiday / paid_leave / unpaid_leave) to the leave-head set
 * EL / CL / H / WO / OD / SL. Pay behaviour is preserved:
 *   wfh          → NULL  (status is already PP — a present, paid day)
 *   holiday      → WO    (a paid day off)
 *   paid_leave   → CL    (paid leave; still zero loss of pay)
 *   unpaid_leave → NULL  (status AA + no tag ⇒ full-day loss of pay, as before)
 *
 * The column type (string 20, nullable) is unchanged, so this is data-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendances')->where('day_type', 'holiday')->update(['day_type' => 'WO']);
        DB::table('attendances')->where('day_type', 'paid_leave')->update(['day_type' => 'CL']);
        DB::table('attendances')->whereIn('day_type', ['wfh', 'unpaid_leave'])->update(['day_type' => null]);
    }

    public function down(): void
    {
        // Best-effort reverse — 'wfh' and plain unpaid absences can't be told
        // apart after the fact, so the NULLs are left as-is.
        DB::table('attendances')->where('day_type', 'WO')->update(['day_type' => 'holiday']);
        DB::table('attendances')->where('day_type', 'CL')->update(['day_type' => 'paid_leave']);
    }
};
