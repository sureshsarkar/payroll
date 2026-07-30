<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit 2026-05-18 phase 3 — file attachments on announcements.
 */
class AnnouncementAttachment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function humanSize(): string
    {
        $b = (int) $this->size_bytes;
        if ($b < 1024)         return $b . ' B';
        if ($b < 1024 * 1024)  return round($b / 1024, 1) . ' KB';
        return round($b / (1024 * 1024), 2) . ' MB';
    }
}
