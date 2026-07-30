<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PrivateMedia — the single gateway for tenant-PRIVATE files (V1 fix, 2026-06-16).
 *
 * Private files (announcement attachments, offline payment receipts, and any
 * other login/enrollment-gated media) are stored on the `private` disk
 * (storage/app/private — OUTSIDE the web root, no public URL). They are served
 * ONLY through authenticated, ownership-checked controllers via download()
 * below. A direct HTTP URL to a private file is therefore impossible.
 *
 * Filename randomness is NOT the control here — the control is that the bytes
 * live outside the web root and every read goes through an authorized route.
 *
 * download()/delete() carry a 3-tier fallback so files written under the OLD
 * (public) scheme keep working during/after migration, in order:
 *   1. `private` disk            — the new, correct location
 *   2. `public` disk             — legacy files under public/uploads/store/<path>
 *   3. public_path(<path>)       — legacy file_upload() files (e.g. uploads/custom-images/...)
 * Once the migration command has run and the legacy public copies are cleaned
 * up (separate approved step), only tier 1 remains in play.
 */
class PrivateMedia
{
    public const DISK = 'private';

    /** Store an uploaded file under $folder on the private disk; returns the
     *  relative path (e.g. "announcements/12/ab12….png"). */
    public static function store(UploadedFile $file, string $folder): string
    {
        return $file->store(trim($folder, '/'), self::DISK);
    }

    public static function exists(?string $path): bool
    {
        return is_string($path) && $path !== '' && Storage::disk(self::DISK)->exists($path);
    }

    /** Authorized download response. Callers MUST have already validated auth +
     *  ownership/enrollment before calling this. 404s if the file is gone. */
    public static function download(?string $path, ?string $downloadName = null): StreamedResponse
    {
        if (! is_string($path) || $path === '') {
            abort(404);
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            return Storage::disk(self::DISK)->download($path, $downloadName);
        }
        // legacy tier 2 — public disk root = public/uploads/store
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path, $downloadName);
        }
        // legacy tier 3 — file_upload() helper paths relative to public/
        $abs = public_path($path);
        if (is_file($abs)) {
            return response()->download($abs, $downloadName ?: basename($abs));
        }

        abort(404);
    }

    /** Best-effort delete across all tiers. */
    public static function delete(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }
        try {
            if (Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
                return;
            }
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                return;
            }
            $abs = public_path($path);
            if (is_file($abs)) {
                @unlink($abs);
            }
        } catch (\Throwable $e) {
            // best-effort; never fail a request on cleanup
        }
    }
}
