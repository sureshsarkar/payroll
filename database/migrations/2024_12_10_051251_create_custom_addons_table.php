<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historical migration that originally backed the Skillgro addon-tracker
 * (admin.addons.sync + AddonsController + CustomAddon).
 *
 * The Skillgro plumbing was removed on 2026-05-07 (see
 * 2026_05_07_191000_drop_custom_addons_table). The post-create wsus.json
 * seeding loop that referenced App\Models\CustomAddon was stripped here
 * so a fresh `migrate` run on a clean DB still succeeds — the model no
 * longer exists. Net effect on a fresh DB: this migration creates the
 * table; the 2026_05_07 migration drops it again. If you're seeing
 * `custom_addons` in production, your migrations are out of date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_addons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('slug');
            $table->boolean('is_default')->default(false);
            $table->boolean('isPaid')->default(true);
            $table->text('description')->nullable();
            $table->json('author')->nullable();
            $table->json('options')->nullable();
            $table->string('icon')->nullable();
            $table->string('license')->nullable();
            $table->string('url')->nullable();
            $table->string('version')->nullable();
            $table->date('last_update')->nullable();
            $table->boolean('status')->default(false)->index('idx_custom_addons_status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_addons');
    }
};
