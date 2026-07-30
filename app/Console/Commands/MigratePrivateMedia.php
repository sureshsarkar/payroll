<?php

namespace App\Console\Commands;

use App\Support\PrivateMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * media:migrate-private — V1 fix (2026-06-16).
 *
 * COPIES existing private files (announcement attachments + offline payment
 * receipts) from their legacy public locations to the non-web `private` disk,
 * so they can be served exclusively through the gated controllers.
 *
 * SAFETY (matches the approved plan):
 *   - Non-destructive: COPIES only; never deletes the legacy originals.
 *   - Idempotent: skips files already present on the private disk.
 *   - Logged: every copy (and every miss) is logged + summarized.
 *   - Graceful: missing/unreadable legacy files are reported, never fatal.
 *   - --dry-run: report what WOULD happen, change nothing.
 *
 * After running + verifying, the legacy public copies (and the now-orphaned
 * receipt files) may be removed in a SEPARATE approved cleanup step.
 *
 * For announcement attachments the relative path is unchanged (works on both
 * disks) → no DB write. For offline receipts the DB `payment_details` is
 * repointed to the new private relative path (kept safe: only when the copy
 * succeeded), while the legacy file is left in place for the cleanup step.
 */
class MigratePrivateMedia extends Command
{
    protected $signature = 'media:migrate-private {--dry-run : Report only; copy nothing and write no DB changes}';
    protected $description = 'Copy legacy private media (announcement attachments, offline payment receipts) to the private disk';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info(($dry ? '[DRY-RUN] ' : '') . 'Migrating private media to the private disk…');

        $copied = $skipped = $missing = $repointed = 0;

        // ── Announcement attachments (path unchanged across disks) ─────────────
        if (DB::getSchemaBuilder()->hasTable('announcement_attachments')) {
            foreach (DB::table('announcement_attachments')->select('id', 'path')->cursor() as $row) {
                $path = (string) $row->path;
                if ($path === '') { continue; }

                if (PrivateMedia::exists($path)) { $skipped++; continue; }

                // legacy copy lives on the public disk (public/uploads/store/<path>)
                if (! Storage::disk('public')->exists($path)) {
                    $missing++;
                    Log::warning('migrate-private: announcement file missing', ['attachment_id' => $row->id, 'path' => $path]);
                    continue;
                }

                if ($dry) { $this->line("  would copy announcement: {$path}"); $copied++; continue; }

                Storage::disk(PrivateMedia::DISK)->put($path, Storage::disk('public')->get($path));
                Log::info('migrate-private: announcement copied', ['attachment_id' => $row->id, 'path' => $path]);
                $copied++;
            }
        }

        // ── Offline payment receipts (legacy file_upload path → private folder) ─
        if (DB::getSchemaBuilder()->hasTable('orders')) {
            foreach (DB::table('orders')
                ->select('id', 'payment_details', 'payment_method')
                ->whereNotNull('payment_details')->where('payment_details', '<>', '')->cursor() as $order) {

                $pd = (string) $order->payment_details;
                // only legacy file paths (skip JSON gateway blobs + already-private)
                if ($pd === '' || str_starts_with($pd, '{') || str_starts_with($pd, 'payment-receipts/')) { $skipped++; continue; }
                if (stripos((string) $order->payment_method, 'offline') === false) { $skipped++; continue; }

                $abs = public_path($pd);
                if (! is_file($abs)) { $missing++; Log::warning('migrate-private: receipt missing', ['order_id' => $order->id, 'path' => $pd]); continue; }

                $newRel = 'payment-receipts/' . basename($pd);
                if ($dry) { $this->line("  would copy receipt: {$pd} -> {$newRel} (+repoint order #{$order->id})"); $copied++; $repointed++; continue; }

                Storage::disk(PrivateMedia::DISK)->put($newRel, file_get_contents($abs));
                DB::table('orders')->where('id', $order->id)->update(['payment_details' => $newRel]);
                Log::info('migrate-private: receipt copied + repointed', ['order_id' => $order->id, 'from' => $pd, 'to' => $newRel]);
                $copied++; $repointed++;
            }
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Done. copied={$copied} skipped={$skipped} missing={$missing} receipts_repointed={$repointed}");
        $this->warn('Legacy public copies were NOT deleted. Remove them in a separate approved cleanup step after verification.');

        return self::SUCCESS;
    }
}
