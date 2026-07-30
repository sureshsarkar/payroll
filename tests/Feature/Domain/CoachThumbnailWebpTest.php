<?php

namespace Tests\Feature\Domain;

use Tests\TestCase;

/**
 * "Fix Coach Thumbnail Image Upload Validation" (2026-07-08). The coach picks a
 * course thumbnail through the Laravel File Manager (image category). WebP must
 * be accepted alongside JPG/JPEG/PNG/GIF, and an unsupported type must show a
 * user-friendly message. The file manager validates the upload against these
 * config arrays, so asserting them pins the behaviour.
 */
class CoachThumbnailWebpTest extends TestCase
{
    public function test_image_filemanager_accepts_webp(): void
    {
        $imageMimes = config('lfm.folder_categories.image.valid_mime');
        $this->assertContains('image/webp', $imageMimes, 'image picker must accept WebP');
        // The existing formats stay allowed.
        foreach (['image/jpeg', 'image/jpg', 'image/png', 'image/gif'] as $m) {
            $this->assertContains($m, $imageMimes);
        }
    }

    public function test_file_filemanager_and_thumbnailer_accept_webp(): void
    {
        $this->assertContains('image/webp', config('lfm.folder_categories.file.valid_mime'), 'file picker must accept WebP');
        $this->assertContains('image/webp', config('lfm.raster_mimetypes'), 'thumbnail generation must handle WebP');
    }

    public function test_webp_is_not_disallowed(): void
    {
        $this->assertNotContains('image/webp', (array) config('lfm.disallowed_mimetypes', []));
    }

    public function test_invalid_type_message_is_user_friendly(): void
    {
        $msg = trans('laravel-filemanager::lfm.error-mime');
        $this->assertStringNotContainsString('Unexpected MimeType', $msg, 'must not show the raw package default');
        $this->assertStringContainsString('WebP', $msg, 'friendly message should list the allowed formats');
    }
}
