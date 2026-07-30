<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier-3 CRM extension (2026-05-12).
 *
 * Two new tables backing the enterprise-ish features in Tier 3:
 *
 *   - lead_audit_logs : append-only event stream for compliance &
 *                       accountability. Records who changed what on a
 *                       lead, when. Status changes are the primary
 *                       trigger; assignment changes secondary; deletes
 *                       are NOT logged here (the row is gone) but the
 *                       intent is captured at the moment of delete.
 *
 *   - email_sends     : one row per outbound email triggered from a
 *                       lead's detail page. Stores the rendered subject
 *                       and body so a coach can re-read what they sent
 *                       even if the template is later edited.
 *
 * Idempotent.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('lead_audit_logs')) {
            Schema::create('lead_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('enquiry_id');
                $table->unsignedBigInteger('actor_id');         // users.id of who did it
                $table->string('event', 60);                    // 'status_changed', 'assigned', 'note_added', 'email_sent'
                $table->string('from_value', 120)->nullable(); // previous state, for changes
                $table->string('to_value',   120)->nullable(); // new state
                $table->json('meta')->nullable();               // optional extra context
                $table->timestamp('created_at')->useCurrent();

                $table->index(['enquiry_id', 'created_at']);
                $table->index('event');
                $table->index('actor_id');
            });
        }

        if (!Schema::hasTable('email_sends')) {
            Schema::create('email_sends', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('enquiry_id');
                $table->unsignedBigInteger('sender_id');        // users.id of who sent it
                $table->string('to_email', 191);
                $table->string('subject', 255);
                $table->text('body');                            // rendered body, post-template-merge
                $table->string('status', 30)->default('queued'); // queued | sent | failed
                $table->text('error')->nullable();
                $table->timestamps();

                $table->index(['enquiry_id', 'created_at']);
                $table->index('sender_id');
                $table->index('to_email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_sends');
        Schema::dropIfExists('lead_audit_logs');
    }
};
