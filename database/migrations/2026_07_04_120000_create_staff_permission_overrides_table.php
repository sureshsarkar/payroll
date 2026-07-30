<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-staff permission OVERRIDES (2026-07-04) — the explicit diff from a staff
 * member's assigned role: `grant` adds a permission beyond the role, `revoke`
 * removes one the role would otherwise give. Kept SEPARATE from the role's own
 * permissions (roles_permissions) so a staff-level tweak never mutates the role.
 *
 * The effective set is resolved by CoachPermissionService as:
 *     role permissions  −  revokes  +  grants          (override > role > deny)
 * and materialised into the existing users_permissions gate, so enforcement is
 * unchanged and fully backward compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_permission_overrides')) {
            return;
        }
        Schema::create('staff_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');                     // the staff member (users.id)
            $table->unsignedBigInteger('coach_staff_permission_id');   // the catalog permission
            $table->enum('effect', ['grant', 'revoke']);
            $table->unsignedBigInteger('added_by')->nullable();        // the coach who set it (audit)
            $table->timestamps();

            $table->unique(['user_id', 'coach_staff_permission_id'], 'staff_perm_override_unique');
            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('coach_staff_permission_id')->references('id')->on('coach_staff_permissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_permission_overrides');
    }
};
