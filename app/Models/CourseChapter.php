<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class CourseChapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'instructor_id',
        'title',
        'order',
        'status'
    ];

    /**
     * Parent course this chapter belongs to.
     *
     * Reported in bug-doc 2026-05-26 (C3): the step-3 lesson-creation
     * flow tried to read $chapter->course->title and crashed because
     * the relation didn't exist on the model. User fixed it on the
     * demo instance — this is the same fix ported into the repo.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function chapterItems(): HasMany
    {
        return $this->hasMany(CourseChapterItem::class, 'chapter_id', 'id')->orderBy('order');
    }
    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($courseChapter) {
            $courseChapter->chapterItems()->each(function ($chapterItem) {
                $chapterItem->delete();
            });
        });
    }
}
