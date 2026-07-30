<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — Phase 3 of the multi-template business website system
 * (2026-05-12). Locks down two contracts:
 *
 *   1. The schema carries the four mapping columns + indexes that let the CRM
 *      tie a lead back to its originating landing page, template, and
 *      business vertical without an N+1 join.
 *   2. The two public form-handlers (submit_landing_page, submit_service_page)
 *      populate those columns at write time — otherwise the columns exist
 *      but every new row stores NULL and the CRM shows "—" forever.
 *
 * A future refactor that drops the columns OR strips the controller wiring
 * would silently break attribution without breaking any other test.
 */
class EnquiryTemplateMappingTest extends TestCase
{
    public function test_mapping_columns_exist_on_enquiries_table(): void
    {
        foreach (['landing_page_id', 'template_id', 'business_category', 'source_url'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('landing_page_enquiries', $col),
                "landing_page_enquiries.{$col} missing — Phase 3 migration didn't run"
            );
        }
    }

    public function test_indexes_for_crm_filters_exist(): void
    {
        // The two indexes the CRM uses for "Yoga leads" / "best-performing
        // template" reports. Without them, those filters table-scan once the
        // enquiry count grows past a few thousand rows.
        foreach (['lpe_coach_category_idx', 'lpe_coach_template_idx'] as $idx) {
            $rows = \Illuminate\Support\Facades\DB::select(
                "SHOW INDEX FROM landing_page_enquiries WHERE Key_name = ?",
                [$idx]
            );
            $this->assertNotEmpty(
                $rows,
                "Index {$idx} missing — CRM template/category filters will table-scan"
            );
        }
    }

    public function test_submit_landing_page_writes_template_mapping(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageController.php')
        );

        // The controller must look up the originating landing page (covered
        // by either the subdomain lookup or the /coach/{slug} fallback) and
        // write the four mapping fields into the enquiry row.
        $this->assertStringContainsString(
            "->where('subdomain', \$requestUrl)",
            $src,
            'submit_landing_page must resolve the page by subdomain before saving — without it the CRM cannot attribute leads to the right coach'
        );
        $this->assertMatchesRegularExpression(
            '#preg_match\(\s*[\'"]\#?/coach/\(\[a-z0-9\\\\\-\]\+\)#',
            $src,
            'submit_landing_page must have a /coach/{slug} fallback resolver — localhost has no wildcard DNS, so subdomain lookup fails there'
        );
        foreach (['landing_page_id', 'template_id', 'business_category', 'source_url'] as $col) {
            $this->assertStringContainsString(
                "'{$col}'",
                $src,
                "submit_landing_page must populate '{$col}' on the enquiry create — column would otherwise stay NULL forever"
            );
        }
        // Source bucket must be the structured enum, not a free-text URL.
        $this->assertStringContainsString(
            "'source'             => 'landing_page'",
            $src,
            "submit_landing_page must write source='landing_page' (the structured enum) — legacy free-text URLs break the source filter"
        );
    }

    public function test_submit_service_page_writes_template_mapping(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageController.php')
        );

        // submit_service_page captures enquiries from the product cards on a
        // landing page — same attribution rules apply.
        $offset = strpos($src, 'function submit_service_page');
        $this->assertNotFalse($offset, 'submit_service_page method missing');
        $body = substr($src, $offset, 4000);

        $this->assertStringContainsString(
            "'source'            => 'service_form'",
            $body,
            "submit_service_page must write source='service_form' — distinguishes product enquiries from contact enquiries"
        );
        foreach (['landing_page_id', 'template_id', 'business_category'] as $col) {
            $this->assertStringContainsString(
                "'{$col}'",
                $body,
                "submit_service_page must populate '{$col}' so service-form leads carry origin context too"
            );
        }
    }

    public function test_enquiry_model_exposes_relationships(): void
    {
        $src = (string) file_get_contents(app_path('Models/LandingPageEnquiry.php'));

        // The show.blade.php detail panel relies on $enquiry->template and
        // $enquiry->landingPage — these must exist on the model or the panel
        // silently renders blank.
        $this->assertStringContainsString(
            'function landingPage',
            $src,
            'LandingPageEnquiry::landingPage() relation missing — CRM detail panel cannot link back to the landing page'
        );
        $this->assertStringContainsString(
            'function template',
            $src,
            'LandingPageEnquiry::template() relation missing — CRM detail panel cannot show the originating template'
        );
        $this->assertStringContainsString(
            'function sourceLabel',
            $src,
            'LandingPageEnquiry::sourceLabel() helper missing — legacy free-text "source" values render confusingly without the label mapper'
        );
    }
}
