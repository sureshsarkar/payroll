<?php

namespace App\Console\Commands;

use App\Services\LiveClassAttendanceVerifier;
use Illuminate\Console\Command;

/**
 * Audit 2026-05-18 phase 4 — verify attendance for classes whose
 * scheduled end (+ grace window) has passed and which haven't been
 * finalised yet.
 *
 * Scheduled every 5 minutes by Kernel::schedule().
 *
 * Usage:
 *   php artisan attendance:verify-ended-classes
 *   php artisan attendance:verify-ended-classes --grace=10
 */
class VerifyEndedClasses extends Command
{
    protected $signature = 'attendance:verify-ended-classes
                            {--grace=5 : Minutes to wait after expected end before verifying}';

    protected $description = 'Verify attendance for finished live classes (post-class workflow).';

    public function handle(LiveClassAttendanceVerifier $svc): int
    {
        $grace = max(0, min(120, (int) $this->option('grace')));

        $results = $svc->verifyPendingClasses($grace);

        if (empty($results)) {
            if ($this->getOutput()->isVerbose()) {
                $this->info('No classes pending verification.');
            }
            return self::SUCCESS;
        }

        $totalVerified = 0;
        $totalPartial = 0;
        foreach ($results as $r) {
            $this->line(sprintf(
                '  class #%d verified=%d partial=%d (threshold %dmin / expected %dmin)',
                $r['class_id'], $r['verified'], $r['partial'],
                $r['threshold_min'], $r['expected_minutes']
            ));
            $totalVerified += $r['verified'];
            $totalPartial  += $r['partial'];
        }

        $this->info(sprintf(
            'Done. classes=%d verified_students=%d partial_students=%d',
            count($results), $totalVerified, $totalPartial
        ));
        return self::SUCCESS;
    }
}
