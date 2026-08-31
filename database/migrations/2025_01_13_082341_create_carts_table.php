<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        // LMS removal phase 2 (2026-08-27) — was foreignIdFor(App\Models\Course),
        // which no longer exists. Rewritten to the literal FK it produced
        // (course_id -> courses.id) so this historical migration keeps working
        // on a fresh install; the table itself is the LMS shopping cart and is
        // unused now that there is nothing to buy.
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->integer('qty')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('carts');
    }
};
