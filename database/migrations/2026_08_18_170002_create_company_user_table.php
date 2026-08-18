<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenant conversion — Phase 1 (additive).
 *
 * Membership pivot: which users can act for which company. Built now (even
 * though phase 1 only ever populates the 'owner' row) so a company can gain
 * extra HR staff later without a breaking migration. Every "does this HR have
 * access to this company" check goes through this table, not
 * companies.owner_user_id directly.
 *
 * FKs are indexed columns without a DB-level constraint, matching the HR-domain
 * convention (legacy users-table collation safety; integrity enforced in app).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 20)->default('owner')->comment('owner|hr_staff (only owner used in phase 1)');
            $table->string('status', 20)->default('active')->comment('active|suspended');
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
