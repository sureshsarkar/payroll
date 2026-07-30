<?php

namespace App\Console\Commands;

use App\Models\CoachLandingPage;
use App\Models\LandingPageEnquiry;
use Illuminate\Console\Command;

/**
 * Phase 6 of the multi-template business website system (2026-05-12) —
 * retroactively populate landing_page_id + template_id + business_category
 * for enquiry rows that pre-date Phase 3.
 *
 * Without this backfill, the admin oversight page shows accurate global
 * totals but per-page enquiry counts read zero — making it look as if
 * the landing pages aren't converting at all. Coach-level CRM also
 * shows blank Origin panels for legacy leads.
 *
 * Strategy:
 *   - For each enquiry with NULL landing_page_id and non-NULL coach_id:
 *   - Find that coach's landing pages (published preferred over drafts).
 *   - If exactly one match: assign it confidently.
 *   - If multiple: assign the OLDEST (the one most likely to have been
 *     live when the enquiry came in — a coach's first page predates the
 *     enquiries that came in via that page).
 *   - If none: skip — likely a manual/imported lead that never had a page.
 *
 * Idempotent: re-runs cost a few queries but write nothing for rows that
 * are already mapped.
 *
 * Run with --dry-run to preview without writing.
 */
class BackfillEnquiryMapping extends Command
{
    protected $signature = 'enquiries:backfill-mapping {--dry-run : Show what would change without writing}';

    protected $description = 'Retroactively map pre-Phase-3 enquiries back to their landing page + template + business category';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // Pull only the rows that actually need backfilling — avoids rewriting
        // anything Phase 3 captured correctly on intake.
        $unmapped = LandingPageEnquiry::whereNull('landing_page_id')
            ->whereNotNull('coach_id')
            ->orderBy('id')
            ->get(['id', 'coach_id']);

        if ($unmapped->isEmpty()) {
            $this->info('Nothing to backfill — every enquiry with a coach already has a landing_page_id.');
            return self::SUCCESS;
        }

        $this->line("Found {$unmapped->count()} unmapped enquiries. Scanning…");

        // Build a coach_id → best-guess landing page map in one query so
        // we don't N+1 across 10k enquiries.
        $coachIds = $unmapped->pluck('coach_id')->unique()->all();
        $allPages = CoachLandingPage::with(['getTemplate.categoryname'])
            ->whereIn('added_by', $coachIds)
            ->orderBy('is_published', 'desc')  // published first
            ->orderBy('id', 'asc')             // then oldest first within each tier
            ->get();

        $pickByCoach = [];
        foreach ($allPages as $page) {
            // First page seen per coach wins (orderBy above ensures it's
            // published-then-oldest).
            $pickByCoach[(int) $page->added_by] ??= $page;
        }

        $assigned = 0;
        $skipped  = 0;
        $bar = $this->output->createProgressBar($unmapped->count());
        $bar->start();

        foreach ($unmapped as $enq) {
            $page = $pickByCoach[(int) $enq->coach_id] ?? null;
            $bar->advance();

            if (!$page) {
                $skipped++;
                continue;
            }

            $tplId = $page->template_id;
            $cat   = $page->getTemplate?->categoryname?->name;

            if (!$dry) {
                LandingPageEnquiry::where('id', $enq->id)->update([
                    'landing_page_id'   => $page->id,
                    'template_id'       => $tplId,
                    'business_category' => $cat,
                ]);
            }
            $assigned++;
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Outcome', 'Count'],
            [
                ['Assigned', $assigned],
                ['Skipped (coach has no landing page)', $skipped],
            ]
        );

        if ($dry) {
            $this->warn('Dry-run mode — no rows were updated.');
        } else {
            $this->info('Backfill complete.');
        }

        return self::SUCCESS;
    }
}
