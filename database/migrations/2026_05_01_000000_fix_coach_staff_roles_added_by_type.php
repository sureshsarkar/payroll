<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix coach_staff_roles.added_by type: it's varchar(255) but stores integer user IDs.
 * Convert to UNSIGNED BIGINT (matching users.id type) so foreign-key joins work
 * without implicit casting and so the column reflects its actual semantics.
 *
 * Safe because the column already contains only numeric strings (verified before deploy).
 * If any non-numeric values exist, the cast on the way down will set them to NULL.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('coach_staff_roles', function (Blueprint $table) {
            // Drop the column and re-add with the correct type. Using DBAL change()
            // would also work, but a re-add is more portable across MySQL versions.
            $table->renameColumn('added_by', 'added_by_old');
        });

        Schema::table('coach_staff_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('added_by')->nullable()->after('role_slug');
        });

        \DB::statement('UPDATE coach_staff_roles SET added_by = CAST(added_by_old AS UNSIGNED) WHERE added_by_old REGEXP \'^[0-9]+$\'');

        Schema::table('coach_staff_roles', function (Blueprint $table) {
            $table->dropColumn('added_by_old');
        });
    }

    public function down(): void
    {
        Schema::table('coach_staff_roles', function (Blueprint $table) {
            $table->renameColumn('added_by', 'added_by_old');
        });

        Schema::table('coach_staff_roles', function (Blueprint $table) {
            $table->string('added_by', 255)->nullable()->after('role_slug');
        });

        \DB::statement('UPDATE coach_staff_roles SET added_by = CAST(added_by_old AS CHAR) WHERE added_by_old IS NOT NULL');

        Schema::table('coach_staff_roles', function (Blueprint $table) {
            $table->dropColumn('added_by_old');
        });
    }
};
