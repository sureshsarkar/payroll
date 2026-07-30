<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the `configurations` table.
 *
 * The Skillgro Installer module that owned this table was retired on
 * 2026-05-07. The table only ever held the `setup_complete` flag the
 * installer used to gate its wizard.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('configurations');
    }

    public function down(): void
    {
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->string('config');
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
};
