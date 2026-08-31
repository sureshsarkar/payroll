<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * LMS removal phase 2 (2026-08-31) — final step. Drops every table that
 * belonged to the deleted LMS/coach-business code (routes, controllers,
 * models, views, modules were removed in earlier Phase 2 commits on this
 * branch; this migration removes the schema they left behind).
 *
 * Safety process this migration went through before being written:
 *   1. Every one of the 203 tables in the schema was checked against the
 *      SURVIVING codebase (app/, Modules/{Attendance,Company,Currency,
 *      GlobalSetting,HrEmployee,Language,Leave,Location,Payroll}/, config/,
 *      routes/, database/seeders/, database/factories/) for a genuine
 *      functional reference — comments stripped, and database/migrations/
 *      excluded from that scan (every table's own historical CREATE
 *      migration always "mentions" it, which is not evidence of current use).
 *   2. Every surviving Eloquent model's implicit (naming-convention) table
 *      was checked by hand, since a model with no explicit $table property
 *      never appears in a literal-string scan.
 *   3. Every apparent "still referenced" hit was manually opened and read —
 *      several turned out to be dead code this session had not yet cleaned
 *      up (NotificationEmailTemplates' 32-entry LMS template registry,
 *      PermissionsTrait's ~25 LMS permission groups, SecretSettings'
 *      payment-gateway secret-key registry, two orphaned FormRequest
 *      classes, and two stale route-name label maps) — all trimmed in this
 *      same session, which is what let their tables move from "referenced"
 *      to "safe to drop". Two matches were false positives from generic
 *      English words unrelated to any table (`sections` matched a Blade
 *      view-path segment; `badges` matched Bootstrap's `.badge` CSS class)
 *      and were force-corrected by hand.
 *   4. Cross-checked every live foreign-key constraint in the database
 *      (information_schema.KEY_COLUMN_USAGE) against the keep-list: zero
 *      kept table has a foreign key pointing at any table this migration
 *      drops. HR/Payroll's isolation from the LMS schema is real, not just
 *      code-level.
 *
 * The 50 kept tables: the five HR modules' own tables, the shared
 * infrastructure modules (Language, Currency, Location, GlobalSetting),
 * Laravel/package framework tables (cache, sessions, jobs, notifications,
 * password_reset_tokens, personal_access_tokens, migrations), Spatie
 * permission tables, and the two non-LMS auth pieces kept deliberately in
 * the previous commit (socialite_credentials for Google login,
 * activity_logs for the audit trail).
 *
 * Run and verified on mbs_test before being run anywhere else. Back up the
 * target database before running this on it — see the Verification section
 * of the PR / commit message for the exact mysqldump command used.
 */
return new class extends Migration
{
    /** Alphabetical; grouped by former feature area only for readability. */
    private const LMS_TABLES = [
        // Announcements (course/batch)
        'announcement_attachments', 'announcement_batches', 'announcement_reads', 'announcements',
        // Badges / certificates
        'badges', 'certificate_builder_items', 'certificate_builders', 'certificate_credentials',
        // Payment gateways (BasicPayment, BkashPG, CryptoPayment, MercadoPagoPG)
        'banned_histories', 'basic_payments', 'bkash_p_g_models', 'crypto_p_g', 'mercadopagopg',
        'payment_gateways',
        // Blog
        'blog_categories', 'blog_category_translations', 'blog_comments', 'blog_translations', 'blogs',
        // Brand / white-label
        'brands',
        // Cart / checkout
        'carts', 'favorite_course_user',
        // Coach business (staff, sites, billing, trials, domains)
        'coach_active_meetings', 'coach_blogs', 'coach_brand_settings', 'coach_domain_events',
        'coach_domains', 'coach_email_templates', 'coach_landing_pages', 'coach_menu_items',
        'coach_menus', 'coach_page_versions', 'coach_pages', 'coach_payment_gateways',
        'coach_pricing_enquiries', 'coach_pricing_payments', 'coach_site_footers',
        'coach_site_page_views', 'coach_site_settings', 'coach_staff_permissions',
        'coach_staff_roles', 'coach_student_links', 'coach_theme_settings', 'coach_trainers',
        'coach_trial_enquiries', 'coach_trial_payments', 'coach_trial_settings', 'coach_trial_slots',
        'roles_permissions', 'users_permissions', 'users_roles', // coach-staff RBAC pivots
        // Contact / messaging
        'contact_messages', 'contact_sections', 'direct_messages',
        // Coupons
        'coupon_histories', 'coupon_uses', 'coupons',
        // Courses + taxonomy + content
        'course_batches', 'course_categories', 'course_category_translations',
        'course_chapter_items', 'course_chapter_lessons', 'course_chapters',
        'course_delete_requests', 'course_languages', 'course_level_translations',
        'course_levels', 'course_live_classes', 'course_partner_instructors',
        'course_progress', 'course_reviews', 'course_selected_filter_options',
        'course_selected_languages', 'course_selected_levels', 'courses',
        // Custom pages / page builder / page-template builder
        'custom_page_translations', 'custom_pages', 'page_template_builders',
        'page_template_categories',
        // Email sends
        'email_sends',
        // Orders / commerce
        'enrollments', 'order_items', 'orders',
        // FAQ
        'faq_translations', 'faqs',
        // Featured instructor sections (homepage builder)
        'featured_course_sections', 'featured_instructor_translations', 'featured_instructors',
        // Fees
        'fee_demands', 'fee_payments', 'offline_payments',
        // Footer settings
        'footer_settings',
        // Homepage section builder (Frontend module)
        'homes', 'landing_sections', 'section_settings', 'section_translations', 'sections',
        // Instructor requests
        'instructor_request_setting_translations', 'instructor_request_settings',
        'instructor_requests',
        // Landing pages / CRM
        'landing_page_enquiries', 'lead_audit_logs', 'lead_notes',
        // Lessons / Q&A
        'lesson_notes', 'lesson_questions', 'lesson_replies',
        // Live classes
        'instant_meetings', 'live_class_attendances', 'live_class_recordings',
        'live_class_reminders_sent', 'live_class_start_notifications',
        // Marquee
        'marquees',
        // Membership / subscription
        'membership_plans', 'subscription_histories', 'subscription_plans', 'user_memberships',
        // Menu builder
        'menu_item_translations', 'menu_items', 'menu_translations', 'menus',
        // Newsletter
        'news_letters',
        // Push devices (mobile app — LMS API deleted)
        'push_devices',
        // Quizzes
        'quiz_question_answers', 'quiz_questions', 'quiz_results', 'quizzes',
        // Referrals
        'referral_commissions', 'referral_settings', 'referral_wallet_transactions', 'referrals',
        // Social links
        'social_links',
        // Staff permission overrides (coach staff)
        'staff_permission_overrides',
        // Student batch assignment
        'student_batch_assignments', 'student_temporary_slots', 'teacher_batch_assignments',
        // Tax
        'tax_profiles', 'tax_rates',
        // Testimonials
        'testimonial_translations', 'testimonials',
        // Theme studio
        'theme_applications', 'theme_audit_log', 'theme_categories', 'theme_category_pivot',
        'theme_pages', 'theme_sections', 'themes',
        // Trainer packages
        'trainer_session_packages',
        // Withdrawals / payouts
        'withdraw_methods', 'withdraw_requests',
        // Skill topics (student profile — LMS-only field)
        'user_skill_topics',
        // Website builder templates
        'website_builder_templates',
        // Zoom / YouTube live-class credentials
        'youtube_credentials', 'zoom_credentials',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        $dropped = [];
        $missing = [];
        try {
            foreach (self::LMS_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    Schema::drop($table);
                    $dropped[] = $table;
                } else {
                    $missing[] = $table;
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        Log::info('lms-tables-dropped', [
            'dropped_count' => count($dropped),
            'already_missing_count' => count($missing),
            'already_missing' => $missing,
        ]);
    }

    /**
     * One-way by design — see the file docblock's mysqldump note. Recreating
     * ~150 tables' exact historical schema (columns, indexes, FKs, enum
     * values accumulated across dozens of later migrations) in code here
     * would be a second, much larger source of error than just restoring
     * the pre-migration dump.
     */
    public function down(): void
    {
        Log::warning('lms-tables-drop-migration-rolled-back-noop', [
            'message' => 'This migration does not recreate the dropped tables. '
                . 'Restore from the mysqldump backup taken before it ran.',
        ]);
    }
};
