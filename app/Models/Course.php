<?php

namespace App\Models;

use Modules\Order\app\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Modules\Order\app\Models\OrderItem;
use Modules\Order\app\Models\Enrollment;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Course\app\Models\CourseCategory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Course extends Model {
    use HasFactory, SoftDeletes;

    public function scopeActive() {
        return $this->where(['is_approved' => 'approved', 'status' => 'active']);
    }

    /**
     * The EFFECTIVE selling price = the single source of truth for "what the
     * student is charged for this course".
     *
     * In this schema `discount` is NOT a discount amount — it IS the discounted
     * sale price (e.g. price 6,666, discount 555 → the course sells for 555).
     * The storefront already shows `discount > 0 ? discount : price`, and
     * User::cart_total sums `IFNULL(NULLIF(discount,0), price)`. Every order /
     * invoice line item MUST use the same value, otherwise the invoice shows the
     * struck-through MRP (`price`) while the student was charged the sale price
     * — the reported "Course Price and Invoice Amount Mismatch" (2026-06-12).
     *
     * Use $course->effective_price everywhere an order line item is priced.
     * Global for every coach — derived purely from the course's own columns.
     */
    public function getEffectivePriceAttribute() {
        return ($this->discount > 0) ? $this->discount : $this->price;
    }
    public function getFavoriteByClientAttribute() {
        if (auth()->guard('web')->check()) {
            return $this->relationLoaded('favoriteBy') ? in_array(userAuth()->id, $this->favoriteBy->pluck('id')->toArray()) : false;
        }

        return false;
    }

    public function favoriteBy() {
        return $this->belongsToMany(User::class, 'favorite_course_user')->withTimestamps();
    }

    public function partnerInstructors(): HasMany {
        return $this->hasMany(CoursePartnerInstructor::class, 'course_id', 'id');
    }

    public function levels(): HasMany {
        return $this->hasMany(CourseSelectedLevel::class, 'course_id', 'id');
    }
    public function languages(): HasMany {
        return $this->hasMany(CourseSelectedLanguage::class, 'course_id', 'id');
    }

    public function filtersOptions(): HasMany {
        return $this->hasMany(CourseSelectedFilterOption::class, 'course_id', 'id');
    }

    public function category(): BelongsTo {
        return $this->belongsTo(CourseCategory::class, 'category_id', 'id')->withDefault();
    }

    public function subcategory(): BelongsTo {
        return $this->belongsTo(CourseCategory::class, 'sub_category_id', 'id')->withDefault();
    }
    
    public function instructor(): BelongsTo {
        return $this->belongsTo(User::class, 'instructor_id', 'id')->withDefault();
    }

     

    public function chapters(): HasMany {
        return $this->hasMany(CourseChapter::class, 'course_id', 'id');
    }
    
    public function chapterItems(): HasManyThrough {
        return $this->hasManyThrough(
            CourseChapterItem::class,
            CourseChapter::class,
            'course_id',
            'chapter_id',
            'id',
            'id'
        );
    }
    public function orders()
    {
        return $this->hasManyThrough(
            Order::class,
            OrderItem::class,
            'course_id',
            'id',
            'id',
            'order_id'
        );
    }
    public function progresses(): HasMany {
        return $this->hasMany(CourseProgress::class, 'course_id', 'id');
    }

    public function reviews(): HasMany {
        return $this->hasMany(CourseReview::class, 'course_id', 'id');
    }
    public function lessons(): HasMany {
        return $this->hasMany(CourseChapterLesson::class, 'course_id', 'id');
    }

    public function enrollments(): HasMany {
        return $this->hasMany(Enrollment::class, 'course_id', 'id');
    }
    public function quizzes(): HasMany {
        return $this->hasMany(Quiz::class, 'course_id', 'id');
    }
    public function carts() {
        return $this->hasMany(Cart::class);
    }

    
    public function batches(): HasMany {
        return $this->hasMany(CourseBatch::class, 'course_id', 'id');
    }
    
    /**
     * Boot method to handle model events.
     */
    protected static function boot() {
        parent::boot();

        static::deleting(function ($course) {
            // Delete related chapters
            $course->chapters()->each(function ($chapter) {
                $chapter->delete();
            });

            // Delete related partner instructors
            $course->partnerInstructors()->each(function ($instructor) {
                $instructor->delete();
            });

            // Delete related levels
            $course->levels()->each(function ($level) {
                $level->delete();
            });

            // Delete related languages
            $course->languages()->each(function ($language) {
                $language->delete();
            });

            // Delete related filter options
            $course->filtersOptions()->each(function ($filterOption) {
                $filterOption->delete();
            });

            // Delete related reviews
            $course->reviews()->each(function ($review) {
                $review->delete();
            });
        });
    }
}
