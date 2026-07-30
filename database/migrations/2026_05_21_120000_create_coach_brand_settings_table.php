<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-coach white-label — Phase 1 (foundation).
 *
 * Today the platform has ONE settings table (`settings`) and every
 * coach's students see the same logo, the same brand name, the same
 * support email. To ship Path B (per-coach white-label inside one
 * install) we need a row PER COACH that overrides the platform brand
 * for that coach's students.
 *
 * Resolution order (handled by BrandResolver):
 *   1. coach_brand_settings.<field>   coach has explicitly set it
 *   2. settings.<field>               platform default
 *   3. hardcoded sane fallback        last-resort defaults
 *
 * That means a coach who hasn't customised anything still sees the
 * platform's branding (no breakage). A coach who has uploaded a
 * logo + set brand_name sees THEIR brand. A coach who sets some
 * fields but not others inherits the platform default for the rest.
 *
 * Backfill: one row per existing coach (role='instructor') with
 * NULLs everywhere — they fall back to platform defaults on read.
 * No one's experience changes until they explicitly customise.
 *
 * UNIQUE(coach_id) so each coach has at most one row. Path B does
 * NOT support multiple brand profiles per coach (KISS).
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_brand_settings')) {
            Schema::create('coach_brand_settings', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('coach_id');
                $table->foreign('coach_id')->references('id')->on('users')
                      ->onDelete('cascade');

                // Visual identity
                $table->string('brand_name', 80)->nullable();
                $table->string('logo_path', 500)->nullable();
                $table->string('favicon_path', 500)->nullable();
                $table->string('primary_color', 9)->nullable();   // hex incl alpha
                $table->string('accent_color', 9)->nullable();

                // Contact / legal
                $table->string('support_email', 120)->nullable();
                $table->string('support_phone', 40)->nullable();
                $table->string('terms_url', 500)->nullable();
                $table->string('privacy_url', 500)->nullable();

                // Tone of voice
                $table->string('footer_text', 255)->nullable();
                $table->text('email_signature')->nullable();

                $table->timestamps();

                // Each coach at most once.
                $table->unique('coach_id', 'cbs_unique_coach');
            });
        }

        $this->backfillExistingCoaches();
    }

    /**
     * Create a placeholder row for every existing coach. All fields
     * are NULL — read paths fall back to platform settings so behaviour
     * is unchanged until the coach customises.
     *
     * Idempotent via insertOrIgnore against the unique index.
     */
    protected function backfillExistingCoaches(): void
    {
        $now = now();
        $coachIds = DB::table('users')
            ->where('role', 'instructor')
            ->pluck('id');

        foreach ($coachIds as $cid) {
            DB::table('coach_brand_settings')->insertOrIgnore([
                'coach_id'   => $cid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_brand_settings');
    }
};
