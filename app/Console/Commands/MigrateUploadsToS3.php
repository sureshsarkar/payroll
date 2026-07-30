<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * One-shot migration: copy everything in public/uploads/ to the configured S3 disk.
 *
 * Usage:
 *   php artisan uploads:migrate-to-s3              # dry-run, shows what would copy
 *   php artisan uploads:migrate-to-s3 --execute    # actually copies
 *   php artisan uploads:migrate-to-s3 --execute --delete-local   # also rm local copies after upload (DANGEROUS)
 *
 * The DB rows store paths like "uploads/custom-images/wsus-img-...png". Those paths
 * are still valid on S3 — the Storage::disk('s3')->url($path) helper resolves them.
 * Display code that uses asset($path) keeps working as long as APP_URL points at
 * something Apache serves, but for S3-served assets you should switch to
 * Storage::disk(env('UPLOAD_DISK', 'public'))->url($path) — see docs/S3_MIGRATION.md.
 */
class MigrateUploadsToS3 extends Command
{
    protected $signature = 'uploads:migrate-to-s3
                            {--execute : Actually upload (default is dry-run)}
                            {--delete-local : After successful upload, delete the local copy}
                            {--prefix= : Only migrate paths starting with this prefix (e.g. course-thumbs/)}';

    protected $description = 'Copy public/uploads/* to the configured S3 disk';

    public function handle(): int
    {
        $execute = $this->option('execute');
        $deleteLocal = $this->option('delete-local');
        $prefix = $this->option('prefix');

        $base = public_path('uploads');
        if (! is_dir($base)) {
            $this->error("Local uploads directory not found: $base");
            return self::FAILURE;
        }

        // Sanity check: we can actually reach S3.
        try {
            $disk = Storage::disk('s3');
            // putString is cheap — write a heartbeat marker
            if ($execute) {
                $disk->put('.migration-heartbeat', date('c'));
            }
        } catch (\Throwable $e) {
            $this->error('S3 disk not reachable: ' . $e->getMessage());
            $this->line('Check AWS_* env vars and that the bucket exists + is accessible.');
            return self::FAILURE;
        }

        $files = $this->collectFiles($base, $prefix);
        $totalBytes = array_sum(array_column($files, 'size'));
        $this->info(sprintf(
            'Found %d files, %s MB total',
            count($files),
            number_format($totalBytes / 1048576, 1)
        ));

        if (! $execute) {
            $this->warn('Dry-run mode. Re-run with --execute to actually upload.');
            $this->line('Sample (first 10):');
            foreach (array_slice($files, 0, 10) as $f) {
                $this->line('  ' . $f['relative']);
            }
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($files));
        $bar->setFormat('verbose');
        $uploaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $f) {
            $relative = 'uploads/' . $f['relative'];
            // Skip if file already exists and is the same size on S3
            if ($disk->exists($relative) && $disk->size($relative) === $f['size']) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $stream = fopen($f['absolute'], 'rb');
            try {
                $ok = $disk->put($relative, $stream);
                if ($ok) {
                    $uploaded++;
                    if ($deleteLocal) {
                        File::delete($f['absolute']);
                    }
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn('Failed: ' . $relative . ' — ' . $e->getMessage());
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf(
            'Done: uploaded=%d, skipped=%d (already on S3), failed=%d',
            $uploaded, $skipped, $failed
        ));
        if ($failed > 0) {
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    /**
     * Walk public/uploads and return [relative, absolute, size] for each regular file.
     */
    private function collectFiles(string $base, ?string $prefix): array
    {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );
        $out = [];
        foreach ($iter as $info) {
            if (! $info->isFile()) {
                continue;
            }
            $abs = $info->getPathname();
            $rel = ltrim(str_replace($base, '', $abs), DIRECTORY_SEPARATOR);
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
            if ($prefix !== null && ! str_starts_with($rel, $prefix)) {
                continue;
            }
            $out[] = [
                'relative' => $rel,
                'absolute' => $abs,
                'size'     => $info->getSize(),
            ];
        }
        return $out;
    }
}
