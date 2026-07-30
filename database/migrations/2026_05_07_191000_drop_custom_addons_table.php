<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the `custom_addons` table.
 *
 * Created by 2024_12_10_051251 to back the Skillgro addon-tracking
 * feature (admin.addons.sync route + AddonsController + CustomAddon
 * model). All three were removed when the rest of the Skillgro
 * licensing system was retired on 2026-05-07. The table was already
 * empty at removal time and no first-party code reads from it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('custom_addons');
    }

    public function down(): void
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
};
