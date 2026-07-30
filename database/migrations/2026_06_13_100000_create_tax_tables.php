<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-13 — Flexible per-coach tax engine (Phase 1).
 *
 * Tax is OPT-IN and tenant-isolated: a coach configures their own profile +
 * rates; nothing applies until they enable it, so coaches who never touch this
 * are completely unaffected. Both tables are keyed by coach_id (a top-level
 * instructor = users.id) so one coach's tax setup can never bleed into another.
 *
 * Guarded/idempotent (hasTable checks) so it is safe to (re)run on prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_profiles')) {
            Schema::create('tax_profiles', function (Blueprint $table) {
                $table->id();
                // The owning coach (top-level instructor). One profile per coach.
                $table->unsignedBigInteger('coach_id')->unique();
                // Tax stays OFF until the coach explicitly turns it on.
                $table->boolean('is_enabled')->default(false);
                // Coach chooses how their listed prices relate to tax.
                $table->enum('mode', ['exclusive', 'inclusive'])->default('exclusive');
                // Compliance identity shown on the invoice.
                $table->string('legal_name')->nullable();
                $table->string('registration_label')->default('GSTIN'); // e.g. GSTIN / VAT No. / Tax ID
                $table->string('registration_number')->nullable();
                $table->string('country')->nullable();
                $table->string('state')->nullable();
                $table->string('invoice_note')->nullable(); // e.g. "Prices are inclusive of tax"
                $table->timestamps();

                $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('tax_rates')) {
            Schema::create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');
                $table->string('name');                 // e.g. "GST 18%"
                $table->decimal('rate', 6, 3)->default(0); // percent, e.g. 18.000
                $table->boolean('is_default')->default(false);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();

                $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index(['coach_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_profiles');
    }
};
