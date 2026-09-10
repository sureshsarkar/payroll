<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the employee's nominee to the HR profile: the nominee's name and their
 * relationship to the employee. Both are optional free-text fields shown on the
 * employee edit form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('nominee_name')->nullable()->after('emergency_contact_phone');
            $table->string('nominee_relation', 100)->nullable()->after('nominee_name');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn(['nominee_name', 'nominee_relation']);
        });
    }
};
