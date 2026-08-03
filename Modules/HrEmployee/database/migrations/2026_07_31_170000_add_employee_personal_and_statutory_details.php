<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('status');
            $table->string('father_or_spouse_name')->nullable()->after('photo_path');
            $table->date('date_of_birth')->nullable()->after('father_or_spouse_name');
            $table->string('phone', 30)->nullable()->after('date_of_birth');
            $table->string('personal_email')->nullable()->after('phone');
            $table->text('current_address')->nullable()->after('personal_email');
            $table->text('permanent_address')->nullable()->after('current_address');
            $table->string('bank_name')->nullable()->after('permanent_address');
            $table->text('bank_account_number')->nullable()->after('bank_name');
            $table->string('bank_ifsc_code', 30)->nullable()->after('bank_account_number');
            $table->text('pf_number')->nullable()->after('bank_ifsc_code');
            $table->text('uan_number')->nullable()->after('pf_number');
            $table->text('esi_number')->nullable()->after('uan_number');
            $table->text('pan_number')->nullable()->after('esi_number');
            $table->text('aadhaar_number')->nullable()->after('pan_number');
            $table->string('emergency_contact_name')->nullable()->after('aadhaar_number');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'photo_path', 'father_or_spouse_name', 'date_of_birth', 'phone', 'personal_email',
                'current_address', 'permanent_address', 'bank_name', 'bank_account_number',
                'bank_ifsc_code', 'pf_number', 'uan_number', 'esi_number', 'pan_number',
                'aadhaar_number', 'emergency_contact_name', 'emergency_contact_phone',
            ]);
        });
    }
};
