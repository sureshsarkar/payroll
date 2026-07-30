<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // F42 (audit 2026-06-26) — guard + default. Unguarded it fails with
        // "Duplicate column" (1060) on any DB where min_price was hot-fixed in,
        // and a NOT NULL column with no default breaks inserts that omit it.
        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'min_price')) {
                $table->decimal('min_price', 8, 2)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'min_price')) {
                $table->dropColumn('min_price');
            }
        });
    }
};
