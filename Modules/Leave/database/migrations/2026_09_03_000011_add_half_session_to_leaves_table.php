<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature: attendance status codes. A half-day leave now records which half is
 * taken ('first' or 'second') so the auto-written attendance row is scored
 * AP (first half off) or PA (second half off) rather than a generic half day.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leaves', 'half_session')) {
            Schema::table('leaves', function (Blueprint $table) {
                $table->string('half_session', 10)->nullable()->after('days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leaves', 'half_session')) {
            Schema::table('leaves', function (Blueprint $table) {
                $table->dropColumn('half_session');
            });
        }
    }
};
