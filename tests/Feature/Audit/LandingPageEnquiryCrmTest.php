<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression tripwire — Tier-1 CRM upgrade to the Landing-Page-Enquiry
 * page (2026-05-12). The page evolved from a paginated list into a real
 * CRM working surface with:
 *
 *   - Stats banner (total / new-this-week / win-rate / by-status pills)
 *   - Search box + status + date-range filters
 *   - Sortable headers (name / status / created_at)
 *   - In-line quick status change via AJAX (no edit-form round trip)
 *   - Click-to-call (tel:) and click-to-email (mailto:)
 *   - Message preview modal
 *   - Streamed CSV export of the filtered set
 *
 * If any of these get removed in a refactor, the page silently
 * regresses to "paginated list" and the coach experience suffers.
 */
class LandingPageEnquiryCrmTest extends TestCase
{
    use DatabaseTransactions;

    public function test_new_routes_are_registered(): void
    {
        $names = array_keys(Route::getRoutes()->getRoutesByName());

        $this->assertContains(
            'instructor.landing-page-enquiry.update-status',
            $names,
            'POST update-status route missing — in-line status change will 404'
        );
        $this->assertContains(
            'instructor.landing-page-enquiry.export',
            $names,
            'GET CSV export route missing — Export CSV button will 404'
        );
    }

    public function test_index_method_applies_filters_sort_and_stats(): void
    {
        // Static guard: index() must honour at least these query params.
        // If a refactor renames or drops any, the form fields stop having
        // effect and the user thinks the page is broken.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageEnquiryController.php')
        );
        $offset = strpos($src, 'public function index');
        // Window widened 2026-05-20 — the teacher-panel P3 gate added
        // ~30 lines at the top of index() (slug decomposition + an
        // applyTeacherGate closure), pushing the stats-banner keys
        // past the previous 4500-char window. 7000 gives headroom for
        // a couple more inserts before this needs revisiting.
        $body   = substr($src, $offset, 7000);

        foreach (["query('q'", "query('status'", "query('from'", "query('to'"] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $body,
                "index() must read \$request->{$needle}) — filter input is ignored otherwise"
            );
        }
        // SORTABLE allowlist must exist — without it, orderBy() runs on
        // user-supplied input (SQL injection vector).
        $this->assertStringContainsString(
            'private const SORTABLE',
            $src,
            'Controller must define a SORTABLE allowlist — building orderBy from raw request input is unsafe'
        );
        // Stats banner must compute these three numbers.
        foreach (['new_this_week', 'win_rate', 'by_status'] as $key) {
            $this->assertStringContainsString(
                "'$key'",
                $body,
                "index() must pass '$key' in \$stats — banner card will read undefined"
            );
        }
    }

    public function test_update_status_method_validates_against_enum_and_uses_idor_gate(): void
    {
        // Two non-negotiables on the AJAX endpoint:
        //   (1) it must validate the incoming status against the model's
        //       enum (Rule::in(validStatuses())) — without this, a coach
        //       can write arbitrary status strings to the DB.
        //   (2) it must use findOwnedEnquiryOrFail() — without this,
        //       a coach can mutate other coaches' enquiries by guessing IDs.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageEnquiryController.php')
        );
        $offset = strpos($src, 'public function updateStatus');
        $this->assertNotFalse($offset, 'updateStatus method missing');
        $body = substr($src, $offset, 1200);

        $this->assertStringContainsString(
            'Rule::in(LandingPageEnquiry::validStatuses())',
            $body,
            'updateStatus must validate against the LandingPageEnquiry status enum'
        );
        $this->assertStringContainsString(
            '$this->findOwnedEnquiryOrFail',
            $body,
            'updateStatus must run through findOwnedEnquiryOrFail() — without it, IDOR'
        );
    }

    public function test_export_streams_csv_and_reapplies_filters(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageEnquiryController.php')
        );
        $offset = strpos($src, 'public function export');
        $this->assertNotFalse($offset, 'export method missing');
        $body = substr($src, $offset, 3500);

        $this->assertStringContainsString(
            'streamDownload',
            $body,
            'export must use streamDownload — non-streamed export OOMs on large pipelines'
        );
        $this->assertStringContainsString(
            "'Content-Type'           => 'text/csv",
            $body,
            'export must set Content-Type: text/csv'
        );
        $this->assertStringContainsString(
            "\\xEF\\xBB\\xBF",
            $body,
            'export must prepend UTF-8 BOM so Excel renders non-ASCII names correctly'
        );
        // Filter parity — "what you see is what you export" — if the
        // export uses a different filter set than index(), users get
        // surprising CSV contents.
        foreach (["query('q'", "query('status'", "query('from'", "query('to'"] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $body,
                "export() must re-apply \$request->{$needle}) so the CSV matches what the user sees"
            );
        }
    }

    public function test_view_has_stats_banner_filters_sort_and_inline_status(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/landing-page-enquiry/index.blade.php')
        );

        // Stats banner — the four cards.
        $this->assertStringContainsString(
            'lpe-stat-card',
            $view,
            'Stats banner cards missing from index view'
        );
        // Search/filter form must have at least the four expected inputs.
        foreach (['name="q"', 'name="status"', 'name="from"', 'name="to"'] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $view,
                "Filter form missing $needle input"
            );
        }
        // Sortable column headers wired up.
        $this->assertStringContainsString(
            '$sortLink',
            $view,
            'Sortable headers helper missing — column header clicks will not re-sort'
        );
        // In-line status dropdown wired to the AJAX endpoint.
        $this->assertStringContainsString(
            'js-lpe-status',
            $view,
            'In-line status select missing — coaches will have to open the edit form for every status change'
        );
        $this->assertStringContainsString(
            "instructor.landing-page-enquiry.update-status",
            $view,
            'In-line status JS does not call the update-status route'
        );
    }

    public function test_view_makes_phone_and_email_actionable(): void
    {
        // Cheap polish that has outsized usefulness on mobile — the table
        // becomes a one-tap call/email surface instead of read-only.
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/landing-page-enquiry/index.blade.php')
        );
        $this->assertStringContainsString(
            'href="tel:',
            $view,
            'Phone cell missing tel: link — coaches lose one-tap dial'
        );
        $this->assertStringContainsString(
            'href="mailto:',
            $view,
            'Email cell missing mailto: link'
        );
    }

    public function test_view_renders_csv_export_link(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/landing-page-enquiry/index.blade.php')
        );
        $this->assertStringContainsString(
            "instructor.landing-page-enquiry.export",
            $view,
            'Export CSV link missing from the view — feature is unreachable from the UI'
        );
        // The link must carry the current filters through (so the CSV
        // matches what the user is looking at). request()->query() is
        // how we pass them.
        $this->assertStringContainsString(
            'request()->query()',
            $view,
            'Export CSV link does not propagate current filters — exported file will not match the filtered view'
        );
    }

    public function test_view_shows_truncated_message_with_modal_preview(): void
    {
        // The `message` field has always been captured by the public-side
        // form but never displayed. Surfacing it with a modal preview
        // (instead of a wall of text inline) was the F item of Tier 1.
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/landing-page-enquiry/index.blade.php')
        );
        $this->assertStringContainsString(
            'data-lpe-message',
            $view,
            'Truncated message preview missing data-lpe-message attribute — modal cannot read the full text'
        );
        $this->assertStringContainsString(
            'lpeMessageModal',
            $view,
            'Message preview modal markup missing from the view'
        );
    }

    // ============== TIER 2 ==============

    public function test_tier2_schema_columns_and_lead_notes_table_exist(): void
    {
        foreach (['source', 'follow_up_at', 'assigned_to', 'notes_count'] as $col) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasColumn('landing_page_enquiries', $col),
                "landing_page_enquiries.$col missing — Tier-2 features can't store their data"
            );
        }
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasTable('lead_notes'),
            'lead_notes table missing — notes timeline cannot store entries'
        );
        foreach (['enquiry_id', 'author_id', 'body', 'created_at'] as $col) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasColumn('lead_notes', $col),
                "lead_notes.$col missing"
            );
        }
    }

    public function test_tier2_routes_are_registered(): void
    {
        $names = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ([
            'instructor.landing-page-enquiry.kanban',
            'instructor.landing-page-enquiry.show',
            'instructor.landing-page-enquiry.notes.add',
            'instructor.landing-page-enquiry.follow-up',
            'instructor.landing-page-enquiry.bulk',
        ] as $name) {
            $this->assertContains(
                $name,
                $names,
                "Tier-2 route '$name' missing — feature is unreachable"
            );
        }
    }

    public function test_tier2_controller_methods_have_idor_gate(): void
    {
        // show / addNote / setFollowUp must run through findOwnedEnquiryOrFail();
        // bulk() must filter the WHERE clause by coach_id even though it
        // doesn't use the helper directly (so a forged ID list can't
        // mutate other coaches' rows).
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageEnquiryController.php')
        );

        foreach (['show', 'addNote', 'setFollowUp'] as $method) {
            $offset = strpos($src, "function $method");
            $this->assertNotFalse($offset, "method $method not located");
            $body = substr($src, $offset, 1200);
            $this->assertStringContainsString(
                '$this->findOwnedEnquiryOrFail',
                $body,
                "$method() missing findOwnedEnquiryOrFail() — IDOR exposure"
            );
        }

        $bulkOffset = strpos($src, 'function bulk');
        $this->assertNotFalse($bulkOffset, 'bulk() method not located');
        $bulkBody = substr($src, $bulkOffset, 1500);
        $this->assertStringContainsString(
            "where('coach_id', \$coachId)",
            $bulkBody,
            'bulk() must scope to caller\'s coach_id — forged ID lists could otherwise touch other coaches\' rows'
        );
        // Bulk action input must validate against an allowlist.
        $this->assertStringContainsString(
            "Rule::in(['status', 'delete'])",
            $bulkBody,
            'bulk() must validate `action` against the allowed verbs'
        );
    }

    public function test_notes_endpoint_validates_body_and_increments_counter(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageEnquiryController.php')
        );
        $offset = strpos($src, 'function addNote');
        $body   = substr($src, $offset, 1500);

        $this->assertStringContainsString(
            "'body' => ['required', 'string', 'max:5000']",
            $body,
            'addNote must validate body — empty/oversized notes shouldn\'t reach the DB'
        );
        $this->assertStringContainsString(
            "increment('notes_count')",
            $body,
            'addNote must increment the denormalized notes_count counter — index page badge depends on it'
        );
    }

    public function test_kanban_view_renders_columns_per_status(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/landing-page-enquiry/kanban.blade.php')
        );
        $this->assertStringContainsString(
            'lpe-kb-board',
            $view,
            'Kanban board layout class missing'
        );
        $this->assertStringContainsString(
            "@foreach (\$statusOptions as \$key => \$opt)",
            $view,
            'Kanban must iterate statusOptions to render columns — if hardcoded, new statuses won\'t appear'
        );
        // Linking each card to the detail page is the main navigation path.
        $this->assertStringContainsString(
            "instructor.landing-page-enquiry.show",
            $view,
            'Kanban cards must link to the detail page'
        );
    }

    public function test_show_view_has_notes_form_and_followup_form(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/landing-page-enquiry/show.blade.php')
        );
        $this->assertStringContainsString(
            "instructor.landing-page-enquiry.notes.add",
            $view,
            'Detail view missing the add-note form action — timeline can\'t be appended'
        );
        $this->assertStringContainsString(
            "instructor.landing-page-enquiry.follow-up",
            $view,
            'Detail view missing the follow-up form action'
        );
        $this->assertStringContainsString(
            'name="follow_up_at"',
            $view,
            'Detail view missing the follow_up_at input field'
        );
    }

    public function test_index_view_has_bulk_actions_and_kanban_toggle(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/landing-page-enquiry/index.blade.php')
        );
        // Kanban toggle link.
        $this->assertStringContainsString(
            "instructor.landing-page-enquiry.kanban",
            $view,
            'Index view missing Kanban toggle link'
        );
        // Bulk-action UI bits.
        $this->assertStringContainsString(
            'lpe-bulk-form',
            $view,
            'Bulk-action form wrapper id missing'
        );
        $this->assertStringContainsString(
            'name="ids[]"',
            $view,
            'Row checkboxes missing name="ids[]" — bulk endpoint won\'t receive a selection'
        );
        $this->assertStringContainsString(
            'instructor.landing-page-enquiry.bulk',
            $view,
            'Bulk-action form must POST to the bulk endpoint'
        );
        // Notes badge + follow-up column. New columns added in Tier 2.
        $this->assertStringContainsString(
            '💬',
            $view,
            'Notes badge missing from row — coaches won\'t see which leads have history'
        );
    }

    // ============== TIER 3 ==============

    public function test_tier3_schema_tables_exist(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasTable('lead_audit_logs'),
            'lead_audit_logs table missing — audit trail cannot record events'
        );
        foreach (['enquiry_id', 'actor_id', 'event', 'from_value', 'to_value', 'meta', 'created_at'] as $col) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasColumn('lead_audit_logs', $col),
                "lead_audit_logs.$col missing"
            );
        }
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasTable('email_sends'),
            'email_sends table missing — outbound emails can\'t be logged'
        );
        foreach (['enquiry_id', 'sender_id', 'to_email', 'subject', 'body', 'status'] as $col) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasColumn('email_sends', $col),
                "email_sends.$col missing"
            );
        }
    }

    public function test_tier3_routes_are_registered(): void
    {
        $names = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ([
            'instructor.landing-page-enquiry.assign',
            'instructor.landing-page-enquiry.email',
            'instructor.landing-page-enquiry.import',
            'instructor.landing-page-enquiry.import.preview',
            'instructor.landing-page-enquiry.import.commit',
        ] as $name) {
            $this->assertContains(
                $name,
                $names,
                "Tier-3 route '$name' missing"
            );
        }
    }

    public function test_update_status_writes_audit_row(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageEnquiryController.php')
        );
        $offset = strpos($src, 'function updateStatus');
        $body   = substr($src, $offset, 1800);

        $this->assertStringContainsString(
            'LeadAuditLog::create',
            $body,
            'updateStatus must write a LeadAuditLog row — audit trail will silently miss status changes'
        );
        $this->assertStringContainsString(
            "'event'      => 'status_changed'",
            $body,
            'Audit row for status change must use event=status_changed'
        );
        // No-op short-circuit: don't log when prev == new.
        $this->assertStringContainsString(
            '$previousStatus !== $newStatus',
            $body,
            'updateStatus must skip the audit write when status is unchanged (stutter-click defense)'
        );
    }

    public function test_assign_method_validates_target_against_coach_staff_scope(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageEnquiryController.php')
        );
        $offset = strpos($src, 'function assign');
        $this->assertNotFalse($offset, 'assign method missing');
        $body = substr($src, $offset, 2000);

        // Caller can only assign to themselves OR to one of their staff —
        // not to arbitrary user IDs (CRM data leak vector otherwise).
        $this->assertStringContainsString(
            "coach_staff",
            $body,
            'assign() must check the target against the caller\'s coach_staff records'
        );
        $this->assertStringContainsString(
            'abort_unless($isCoach || $isStaff',
            $body,
            'assign() must abort when target is neither the caller nor their staff'
        );
        // Must IDOR-gate the enquiry too.
        $this->assertStringContainsString(
            '$this->findOwnedEnquiryOrFail',
            $body,
            'assign() must run through findOwnedEnquiryOrFail()'
        );
        // Must write an audit row.
        $this->assertStringContainsString(
            "'event'      => 'assigned'",
            $body,
            'assign() must write an audit-log row with event=assigned'
        );
    }

    public function test_send_email_records_send_and_uses_template_variables(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageEnquiryController.php')
        );
        $offset = strpos($src, 'function sendEmail');
        // 2026-07-10 (#8.2) — window widened: sendEmail now resolves the
        // coach-SMTP-vs-platform source before creating the row, so the audit
        // hooks sit further into the method body.
        $body   = substr($src, $offset, 4600);

        // The {first_name} / {service} substitution uses strtr (safe vs.
        // regex-injection from user content) — guard the mechanism stays
        // strtr-based.
        $this->assertStringContainsString(
            'strtr',
            $body,
            'sendEmail must use strtr for variable substitution — regex-based replace is bug-prone with user content'
        );
        // Every send creates an email_sends row first (pre-create so we
        // log even when the mail driver throws).
        $this->assertStringContainsString(
            'EmailSend::create',
            $body,
            'sendEmail must create an email_sends row before attempting the send'
        );
        // Catch + record failure path.
        $this->assertStringContainsString(
            "'status' => 'failed'",
            $body,
            'sendEmail must mark the row failed on exception so the coach sees what went wrong'
        );
        // Audit hook on success.
        $this->assertStringContainsString(
            "'event'      => 'email_sent'",
            $body,
            'sendEmail success path must record event=email_sent in the audit log'
        );
    }

    public function test_csv_import_two_step_flow_and_skips_incomplete_rows(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageEnquiryController.php')
        );

        foreach (['function importForm', 'function importPreview', 'function importCommit'] as $method) {
            $this->assertStringContainsString(
                $method,
                $src,
                "$method missing — two-step CSV import flow is broken"
            );
        }
        // The preview step stores the upload to disk so commit can re-
        // read it without re-uploading. Verify the stash mechanism.
        $previewOffset = strpos($src, 'function importPreview');
        $previewBody   = substr($src, $previewOffset, 1200);
        $this->assertStringContainsString(
            "->store('lpe-import-tmp')",
            $previewBody,
            'importPreview must stash the upload to disk for commit to re-read'
        );

        $commitOffset = strpos($src, 'function importCommit');
        $commitBody   = substr($src, $commitOffset, 2500);
        $this->assertStringContainsString(
            "validate",
            $commitBody,
            'importCommit must validate the stash_path input'
        );
        // Quality filter: skip rows that don't have a name AND (email OR phone).
        $this->assertStringContainsString(
            "empty(\$r['email']) && empty(\$r['phone'])",
            $commitBody,
            'importCommit must skip rows missing both email and phone — junk-data guard'
        );
        // Every imported row writes an audit log entry.
        $this->assertStringContainsString(
            "'event'      => 'imported'",
            $commitBody,
            'importCommit must write event=imported audit rows for traceability'
        );
    }

    public function test_show_view_has_assignment_audit_log_and_email_sections(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/landing-page-enquiry/show.blade.php')
        );
        foreach ([
            'instructor.landing-page-enquiry.assign'  => 'Assignment form missing from show view',
            'instructor.landing-page-enquiry.email'   => 'Send-email form missing from show view',
            'LeadAuditLog'                            => 'Audit-log query missing from show view',
            'WhatsApp'                                => 'WhatsApp quick-action link missing from show view',
            'sms:'                                    => 'SMS quick-action link missing from show view',
        ] as $needle => $message) {
            $this->assertStringContainsString($needle, $view, $message);
        }
    }

    public function test_index_view_has_import_button_and_whatsapp_links(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/landing-page-enquiry/index.blade.php')
        );
        $this->assertStringContainsString(
            'instructor.landing-page-enquiry.import',
            $view,
            'Import CSV button missing from index view'
        );
        $this->assertStringContainsString(
            'https://wa.me/',
            $view,
            'WhatsApp link missing from contact cell — mobile coaches lose one-tap chat'
        );
    }
}
