<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * audit:refund-integrity — operator monitoring tool.
 *
 * Audit 2026-05-22 introduced wallet-reversal on refunded orders (via
 * InstructorDashboardController::mySellsupdate). This command checks
 * that every refunded order has BOTH:
 *
 *   1. A 'wallet_reversed' marker in payment_details (so we know the
 *      decrement already ran — idempotent).
 *   2. A reasonable wallet_reversed_amount that matches the original
 *      (paid_amount × (1 − commission_rate/100)).
 *
 * Run after each refund cycle, or via cron daily:
 *   php artisan audit:refund-integrity
 *
 * Exit code is 0 when everything is consistent, 1 when discrepancies
 * exist — making this safe to wire into CI / monitoring.
 */
class AuditRefundIntegrity extends Command
{
    protected $signature = 'audit:refund-integrity {--since=30 : Look at refunds from the last N days}';
    protected $description = 'Verify every refunded order has a matching wallet reversal in payment_details';

    public function handle(): int
    {
        $since = (int) $this->option('since');
        $start = now()->subDays($since)->toDateTimeString();

        // Use raw query — orders.payment_details is JSON-as-string in some
        // installs, so we can't rely on JSON_EXTRACT being available.
        $refunds = DB::table('orders')
            ->where('payment_status', 'refunded')
            ->where('updated_at', '>=', $start)
            ->orderBy('id', 'desc')
            ->get(['id', 'buyer_id', 'paid_amount', 'commission_rate', 'payment_details', 'updated_at']);

        if ($refunds->isEmpty()) {
            $this->info("No refunded orders in the last {$since} days. Nothing to verify.");
            return self::SUCCESS;
        }

        $this->info("Checking {$refunds->count()} refunded order(s) from the last {$since} days…");
        $this->newLine();

        $bad = [];
        $headers = ['Order #', 'Refund date', 'Paid', 'Reversed?', 'Reversed amount', 'Expected', 'Δ'];
        $rows = [];

        foreach ($refunds as $o) {
            $details = json_decode((string) $o->payment_details, true) ?: [];
            $reversed = (bool) ($details['wallet_reversed'] ?? false);
            $reversedAmt = (float) ($details['wallet_reversed_amount'] ?? 0);

            $paid = (float) $o->paid_amount;
            $commission = $paid * ((float) $o->commission_rate / 100);
            $expected = round($paid - $commission, 2);
            $delta = round($reversedAmt - $expected, 2);

            $status = $reversed ? '<info>YES</info>' : '<error>NO</error>';
            $rows[] = [
                '#' . $o->id,
                substr((string) $o->updated_at, 0, 10),
                number_format($paid, 2),
                $status,
                $reversedAmt ? number_format($reversedAmt, 2) : '—',
                number_format($expected, 2),
                abs($delta) < 0.01 ? '<info>0</info>' : '<error>' . number_format($delta, 2) . '</error>',
            ];

            if (! $reversed || abs($delta) >= 0.01) {
                $bad[] = $o->id;
            }
        }

        $this->table($headers, $rows);

        if ($bad) {
            $this->newLine();
            $this->error('Discrepancies on order id(s): ' . implode(', ', $bad));
            $this->warn('These orders are refunded but the coach wallet was NOT decremented, or the decrement amount is off.');
            $this->warn('Investigate via: SELECT * FROM orders WHERE id IN (' . implode(',', $bad) . ');');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All refunded orders have consistent wallet reversals. ✓');
        return self::SUCCESS;
    }
}
