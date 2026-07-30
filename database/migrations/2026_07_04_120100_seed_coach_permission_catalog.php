<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (2026-07-04) — expand the GLOBAL coach permission catalog so every
 * Coach-Panel module and every Settings sub-page is assignable to staff roles.
 *
 * Convention (unchanged): a bare slug = "access the module"; `-show/-create/
 * -edit/-delete` group into the resource×action picker matrix; other actions
 * (-export/-manage/-publish) are assignable standalone capabilities.
 *
 * Idempotent: inserts ONLY slugs that don't already exist (slug is unique), so
 * the 31 pre-existing rows and all current role assignments are untouched. Adds
 * NOTHING to any role — new permissions start unassigned (deny by default).
 *
 * Also repairs a real gap: `coach-coupons`, `coach-orders`, `live-classes`,
 * `coach-staff`, `roles`, `teacher-batches` were ENFORCED in code but MISSING
 * from the catalog, so they could never be delegated to a staff member.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_staff_permissions')) {
            return;
        }

        // slug => human label
        $catalog = [
            // ── Overview ──
            'dashboard' => 'Dashboard',
            'analytics' => 'Analytics',

            // ── Content ──
            'courses' => 'Courses', 'courses-show' => 'View Course', 'courses-create' => 'Create Course', 'courses-edit' => 'Edit Course', 'courses-delete' => 'Delete Course', 'courses-export' => 'Export Courses',
            'course-batches' => 'Batches', 'course-batches-show' => 'View Batch', 'course-batches-create' => 'Create Batch', 'course-batches-edit' => 'Edit Batch', 'course-batches-delete' => 'Delete Batch',
            'course-bundle' => 'Course Bundle', 'course-bundle-create' => 'Create Bundle', 'course-bundle-edit' => 'Edit Bundle', 'course-bundle-delete' => 'Delete Bundle',
            'live-classes' => 'Live Classes', 'live-classes-create' => 'Create Live Class', 'live-classes-edit' => 'Edit Live Class', 'live-classes-delete' => 'Delete Live Class', 'live-classes-manage' => 'Host / Manage Live Class',
            'instant-meetings' => 'Instant Meeting', 'instant-meetings-create' => 'Start Instant Meeting',
            'announcements' => 'Announcements', 'announcements-create' => 'Create Announcement', 'announcements-edit' => 'Edit Announcement', 'announcements-delete' => 'Delete Announcement',
            'certificate' => 'Certificate', 'certificate-manage' => 'Design / Manage Certificate',
            'blogs' => 'Blog', 'blogs-create' => 'Create Blog', 'blogs-edit' => 'Edit Blog', 'blogs-delete' => 'Delete Blog', 'blogs-publish' => 'Publish Blog',

            // ── People ──
            'coach-students' => 'Students', 'coach-students-show' => 'View Student', 'coach-students-create' => 'Create Student', 'coach-students-edit' => 'Edit Student', 'coach-students-delete' => 'Delete Student', 'coach-students-export' => 'Export Students',
            'landing-page-enquiry' => 'Enquiries', 'landing-page-enquiry-create' => 'Create Enquiry', 'landing-page-enquiry-edit' => 'Edit Enquiry', 'landing-page-enquiry-delete' => 'Delete Enquiry', 'landing-page-enquiry-export' => 'Export Enquiries',
            'teacher-batches' => 'Teacher Batches', 'teacher-batches-edit' => 'Assign Teacher Batches',
            'coach-staff' => 'Staff', 'coach-staff-create' => 'Create Staff', 'coach-staff-edit' => 'Edit Staff', 'coach-staff-delete' => 'Delete Staff',
            'roles' => 'Roles', 'roles-create' => 'Create Role', 'roles-edit' => 'Edit Role', 'roles-delete' => 'Delete Role',

            // ── Commerce ──
            'coach-orders' => 'Orders', 'coach-orders-create' => 'Create Order', 'coach-orders-export' => 'Export Orders', 'coach-orders-edit' => 'Update Order',
            'offline-payments' => 'Offline Payments',
            'coach-coupons' => 'Coupons', 'coach-coupons-create' => 'Create Coupon', 'coach-coupons-edit' => 'Edit Coupon', 'coach-coupons-delete' => 'Delete Coupon',
            'fees' => 'Fees', 'fees-create' => 'Create Fee', 'fees-edit' => 'Edit Fee', 'fees-delete' => 'Delete Fee', 'fees-export' => 'Export Fees',
            'payout' => 'Request Payout', 'payout-create' => 'Request a Payout',
            'pricing-enquiries' => 'Pricing Enquiries', 'pricing-enquiries-export' => 'Export Pricing Enquiries',
            'trial-sessions' => 'Trial Sessions', 'trial-sessions-edit' => 'Configure Trial Sessions', 'trial-sessions-export' => 'Export Trial Enquiries',
            'payment-gateways' => 'Payment Gateway', 'payment-gateways-manage' => 'Manage Payment Gateway',

            // ── Website & content ──
            'website-builder' => 'Website Builder', 'website-builder-edit' => 'Edit Website', 'website-builder-publish' => 'Publish Website',
            'pages' => 'Pages', 'pages-create' => 'Create Page', 'pages-edit' => 'Edit Page', 'pages-delete' => 'Delete Page',
            'menus' => 'Menus', 'menus-create' => 'Create Menu', 'menus-edit' => 'Edit Menu', 'menus-delete' => 'Delete Menu',

            // ── Monetization / account ──
            'membership' => 'Membership',
            'referral' => 'Refer & Earn',

            // ── Settings hub (Step 8) — bare = open the page, -edit = update it ──
            'settings-general' => 'General Settings', 'settings-general-edit' => 'Edit General Settings',
            'settings-profile' => 'Profile Settings', 'settings-profile-edit' => 'Edit Profile Settings',
            'settings-zoom' => 'Zoom Live Settings', 'settings-zoom-edit' => 'Edit Zoom Settings',
            'settings-youtube' => 'YouTube Settings', 'settings-youtube-edit' => 'Edit YouTube Settings',
            'settings-brand' => 'Brand Settings', 'settings-brand-edit' => 'Edit Brand Settings',
            'settings-website' => 'Website Settings', 'settings-website-edit' => 'Edit Website Settings',
            'settings-email' => 'Email / Templates Settings', 'settings-email-edit' => 'Edit Email Templates',
            'settings-notification' => 'Notification Settings', 'settings-notification-edit' => 'Edit Notification Settings',
            'settings-sms-whatsapp' => 'SMS / WhatsApp Settings', 'settings-sms-whatsapp-edit' => 'Edit SMS / WhatsApp Settings',
            'settings-tax' => 'Tax Settings', 'settings-tax-edit' => 'Edit Tax Settings',
            'settings-theme' => 'Theme Settings', 'settings-theme-edit' => 'Edit Theme Settings',
            'settings-domain' => 'Domain Settings', 'settings-domain-edit' => 'Edit Domain Settings',
            'settings-security' => 'Security Settings', 'settings-security-edit' => 'Edit Security Settings',
            'settings-payment-gateway' => 'Payment Gateway Settings', 'settings-payment-gateway-edit' => 'Manage Payment Gateway Settings',
        ];

        $now = now();
        $existing = DB::table('coach_staff_permissions')->pluck('slug')->flip(); // slug => idx for O(1) lookup
        $rows = [];
        foreach ($catalog as $slug => $name) {
            if ($existing->has($slug)) {
                continue; // never touch a pre-existing slug (preserves assignments)
            }
            $rows[] = ['name' => $name, 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('coach_staff_permissions')->insert($chunk);
        }
    }

    public function down(): void
    {
        // Reversible only for the slugs this migration is responsible for AND that
        // no role currently uses — never orphan a live assignment.
        if (! Schema::hasTable('coach_staff_permissions')) {
            return;
        }
        // Intentionally a no-op: a catalog is append-only in practice. Removing
        // slugs could break role assignments made in the meantime.
    }
};
