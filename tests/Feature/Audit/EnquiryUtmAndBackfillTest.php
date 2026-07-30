<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — Phase 6 of the multi-template business website
 * system (2026-05-12). Two related contracts locked down:
 *
 *   1. UTM tags (utm_source / utm_medium / utm_campaign) exist as columns
 *      AND are populated by both public form-handlers at submission time.
 *      Without this, every campaign-driven lead lands in the CRM with no
 *      attribution and the coach can't tell which ad converted.
 *   2. The `enquiries:backfill-mapping` artisan command exists. It's the
 *      one-shot tool that retroactively maps pre-Phase-3 enquiries back
 *      to their landing page — without it, the admin oversight page
 *      reads "0 enquiries" against pages that actually have history.
 */
class EnquiryUtmAndBackfillTest extends TestCase
{
    public function test_utm_columns_exist(): void
    {
        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('landing_page_enquiries', $col),
                "landing_page_enquiries.{$col} missing — Phase 6 migration didn't run"
            );
        }
    }

    public function test_utm_parser_helper_exists_with_length_cap(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageController.php')
        );

        $this->assertStringContainsString(
            'function parseUtmTags',
            $src,
            'parseUtmTags() helper missing — controller cannot extract campaign attribution from the referrer'
        );

        // Length cap on each UTM value — pathological referers should not
        // overflow the column. 100 chars matches the column width.
        $this->assertMatchesRegularExpression(
            '/substr\(\s*\$v\s*,\s*0\s*,\s*100\s*\)/',
            $src,
            'parseUtmTags() must cap each UTM value at 100 chars — column overflow on a long referer would fail the create()'
        );

        // Defensive against malformed referers — query string can be empty.
        $this->assertMatchesRegularExpression(
            '/parse_url\([^)]*PHP_URL_QUERY\)/',
            $src,
            'parseUtmTags() must isolate the query string via parse_url() — passing the full referer to parse_str picks up garbage'
        );
    }

    public function test_both_form_handlers_persist_utm_columns(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageController.php')
        );

        foreach (['submit_landing_page', 'submit_service_page'] as $method) {
            $offset = strpos($src, "function {$method}");
            $this->assertNotFalse($offset, "{$method} method missing");
            $body = substr($src, $offset, 6000);

            foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $col) {
                $this->assertStringContainsString(
                    "'{$col}'",
                    $body,
                    "{$method} must persist {$col} on the enquiry create — campaign attribution would otherwise be lost"
                );
            }
        }
    }

    public function test_backfill_command_is_registered_and_idempotent(): void
    {
        // The Artisan command must be discoverable by name — otherwise the
        // ops runbook ("run enquiries:backfill-mapping after upgrade") fails.
        $commands = \Illuminate\Support\Facades\Artisan::all();
        $this->assertArrayHasKey(
            'enquiries:backfill-mapping',
            $commands,
            'enquiries:backfill-mapping command not registered — Phase 6 backfill tool unreachable'
        );

        $src = (string) file_get_contents(
            app_path('Console/Commands/BackfillEnquiryMapping.php')
        );

        // Idempotency: command must only update rows that lack a mapping.
        // A regression that broadens the WHERE could overwrite already-mapped
        // rows from a later, correct attribution.
        $this->assertMatchesRegularExpression(
            '/whereNull\([\'"]landing_page_id[\'"]\)/',
            $src,
            'Backfill must WHERE landing_page_id IS NULL — without this, re-runs overwrite correct mappings'
        );

        // Dry-run option exists so ops can preview without writes.
        $this->assertStringContainsString(
            '--dry-run',
            $src,
            'Backfill command must support --dry-run so ops can preview the diff before writing'
        );
    }
}
