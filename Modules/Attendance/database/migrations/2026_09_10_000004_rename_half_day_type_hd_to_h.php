<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The half-day `attendances.day_type` tag is denoted "H", not "HD".
 *
 * "HD" is now reserved for the Holiday marker on the Attendance Register, so
 * any half-day rows written while the tag was briefly "HD" are renamed. Pay
 * behaviour is unchanged — a half day is still 0.5 loss of pay.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendances')->where('day_type', 'HD')->update(['day_type' => 'H']);
    }

    public function down(): void
    {
        DB::table('attendances')->where('day_type', 'H')->update(['day_type' => 'HD']);
    }
};
