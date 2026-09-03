<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature: soft delete for HR. HR can now delete a leave record; it is kept in
 * the table with a `deleted_at` timestamp and hidden from every normal
 * view/report, but stays recoverable and auditable in the DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leaves', 'deleted_at')) {
            Schema::table('leaves', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leaves', 'deleted_at')) {
            Schema::table('leaves', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
