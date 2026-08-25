<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Letterhead fields printed on statutory payroll documents (Form XI pay slip,
 * Form IV wage register). Kept on `companies` so each tenant prints its own
 * employer identity instead of the install-wide PAYROLL_ESTABLISHMENT_* env.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('postal_code');
            $table->string('pf_number', 60)->nullable()->after('logo_path')->comment('establishment PF code');
            $table->string('esi_number', 60)->nullable()->after('pf_number')->comment('establishment ESI code');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'pf_number', 'esi_number']);
        });
    }
};
