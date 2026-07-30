<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveClassRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_live_class_id',
        'zoom_recording_id',
        'file_type',
        'file_extension',
        'play_url',
        'download_url',
        'file_size',
        'duration_seconds',
        'recording_start',
        'recording_end',
    ];

    protected $casts = [
        'recording_start' => 'datetime',
        'recording_end'   => 'datetime',
    ];

    public function liveClass(): BelongsTo
    {
        return $this->belongsTo(CourseLiveClass::class, 'course_live_class_id');
    }

    /**
     * Pretty file-size string for the lesson page card. Bytes →
     * "124 MB" / "2.3 GB". Caps at GB; Zoom recordings won't reach TB.
     */
    public function getFileSizeHumanAttribute(): ?string
    {
        if (!$this->file_size) return null;
        $bytes = (int) $this->file_size;
        if ($bytes >= 1024 ** 3) return number_format($bytes / (1024 ** 3), 1) . ' GB';
        if ($bytes >= 1024 ** 2) return number_format($bytes / (1024 ** 2), 0) . ' MB';
        if ($bytes >= 1024)      return number_format($bytes / 1024, 0) . ' KB';
        return $bytes . ' B';
    }
}
