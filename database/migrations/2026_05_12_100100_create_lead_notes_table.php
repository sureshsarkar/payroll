<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-lead notes timeline. Append-only — coaches and staff add notes
 * recording "called Tuesday no answer", "demo scheduled for Friday",
 * etc. Notes are immutable from the UI; if a coach needs to correct
 * one they post a new note. (Hard-delete via DB only — keeps the
 * record honest for audit purposes.)
 *
 * Idempotent.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('lead_notes')) {
            return;
        }
        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enquiry_id');
            $table->unsignedBigInteger('author_id');         // users.id of who wrote it
            $table->text('body');                             // the note text
            $table->timestamps();

            $table->index(['enquiry_id', 'created_at']);     // for chronological fetch per lead
            $table->index('author_id');                       // for "notes by this staff member"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_notes');
    }
};
