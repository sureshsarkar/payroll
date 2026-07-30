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
        // F41 (audit 2026-06-26) — `contact_messages` is ALSO created by the
        // ContactMessage module (identical schema + timestamp). Without this
        // guard a fresh `migrate` aborts with "Base table already exists" (1050).
        // Skip when it exists; the ContactMessage migration is the owner.
        if (Schema::hasTable('contact_messages')) {
            return;
        }
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // F41 — do NOT drop a shared table owned by the ContactMessage module;
        // rolling back THIS module must not delete the other module's data.
    }
};
