<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional company-level contact details printed on the payslip letterhead
 * (Form IV / Form XI). Deliberately separate from the owner's personal login
 * email — a company's printed contact shouldn't default to a person's inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('esi_number');
            $table->string('email')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['phone', 'email']);
        });
    }
};
