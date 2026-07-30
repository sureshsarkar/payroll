<?php

namespace App\Services\Site;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ensures every coach has at least one shoppable course so the Recorded
 * Courses / YouTube sections always have something concrete to attach the
 * "Add to Cart" CTA to.
 *
 * Why this exists:
 *   Coaches frequently configure their public site BEFORE creating any
 *   courses. Without a course, the only available CTA is "Contact for full
 *   access" — which breaks the student journey (register → cart → checkout
 *   → access via dashboard). Auto-provisioning a sensible default course
 *   lets the entire purchase flow work from day one. The coach can edit
 *   title / price / description anytime via /instructor/courses.
 *
 * Idempotent: calling ensureShoppable() repeatedly is safe. It only creates
 * a new course if the coach currently has ZERO non-deleted courses.
 */
class CoachCourseBootstrap
{
    /**
     * Return a Course the section's "Add to Cart" can route to. Creates one
     * if the coach has none. Always returns a non-null Course.
     *
     * @param  User  $coach           The coach who owns the page
     * @param  int   $defaultPrice    Default price for the auto-created course (rupees)
     * @return Course
     */
    public function ensureShoppable(User $coach, int $defaultPrice = 999): Course
    {
        // Reuse first existing non-deleted course if there is one — we do
        // NOT want to multiply auto-created stubs on every section save.
        $existing = Course::query()
            ->where('instructor_id', $coach->id)
            ->where('coach_soft_delete', 0)
            ->orderBy('id')
            ->first();
        if ($existing) {
            return $existing;
        }

        return $this->createDefaultBundle($coach, $defaultPrice);
    }

    /**
     * Ensure a shoppable Course exists for THIS specific YouTube video, so
     * each card in the Recorded Courses section can be added to cart
     * independently. Without this, all video cards share a single "bundle"
     * course and once any one is added every other card shows "already in
     * cart" — which confuses students who expect to buy videos individually.
     *
     * Slug is deterministic: <coach-id>-yt-<video-id>. Repeated calls with
     * the same arguments return the same Course row.
     *
     * @param  User   $coach       The coach who owns the video
     * @param  string $videoId     YouTube video id (11 chars, e.g. dQw4w9WgXcQ)
     * @param  string $videoTitle  Title to use if we create the course
     * @param  string $thumbnail   Optional thumbnail URL
     * @param  int    $price       Default price (rupees) if creating
     * @return Course
     */
    public function ensureForVideo(User $coach, string $videoId, string $videoTitle, string $thumbnail = '', int $price = 499): Course
    {
        $slug = 'c' . $coach->id . '-yt-' . preg_replace('/[^A-Za-z0-9_\-]/', '', $videoId);

        $existing = Course::query()
            ->where('instructor_id', $coach->id)
            ->where('slug', $slug)
            ->first();
        if ($existing) {
            return $existing;
        }

        $title = trim($videoTitle) !== '' ? Str::limit($videoTitle, 240, '') : ('Video ' . $videoId);

        return DB::transaction(function () use ($coach, $title, $slug, $price, $thumbnail, $videoId) {
            $attrs = $this->seedAttributes($coach, $title, $slug, $price);
            // Override description to reference the specific video
            $attrs['description'] = '<p>On-demand video session. '
                . 'Customize this course in <strong>Instructor → Courses</strong> '
                . 'to add modules and upload your full content.</p>';
            if ($thumbnail !== '') {
                $attrs['thumbnail']         = $thumbnail;
                $attrs['demo_video_source'] = $videoId;
                $attrs['demo_video_storage'] = 'youtube';
            }

            $course = new Course();
            $course->forceFill($attrs)->save();

            Log::info('coach-video-course-auto-provisioned', [
                'coach_id'   => $coach->id,
                'course_id'  => $course->id,
                'video_id'   => $videoId,
            ]);

            return $course;
        });
    }

    /**
     * Create a placeholder "On-Demand Sessions" course for the coach.
     * Marked recorded + active so it appears in the marketplace, but the
     * coach should edit title / price / description before announcing it.
     */
    public function createDefaultBundle(User $coach, int $price = 999): Course
    {
        $coachName = trim((string) ($coach->name ?? $coach->first_name ?? 'Coach'));
        if ($coachName === '') {
            $coachName = 'Coach';
        }
        $title = $coachName . ' — On-Demand Sessions';
        $baseSlug = Str::slug($coachName . '-on-demand-sessions');
        // Slug collisions across coaches are possible; suffix with coach id.
        $slug = $baseSlug . '-' . $coach->id;

        // forceFill bypasses MassAssignmentException — Course model has a
        // strict $fillable list because most paths come through the admin
        // form. This is a programmatic provision, not user input, so the
        // bypass is safe.
        return DB::transaction(function () use ($coach, $title, $slug, $price) {
            $course = new Course();
            $course->forceFill($this->seedAttributes($coach, $title, $slug, $price))->save();

            Log::info('coach-course-auto-provisioned', [
                'coach_id'  => $coach->id,
                'course_id' => $course->id,
                'slug'      => $slug,
            ]);

            return $course;
        });
    }

    /**
     * Minimal column set that satisfies the courses-table NOT NULL columns
     * + the conventions the rest of the platform expects (status=1,
     * type=recorded, coach_soft_delete=0).
     */
    protected function seedAttributes(User $coach, string $title, string $slug, int $price): array
    {
        // The Course::active() scope requires status='active' AND
        // is_approved='approved'. Both are enum columns — passing the
        // exact string is mandatory so the cart's Course::active()->find()
        // lookup matches and the course is buyable.
        return [
            'instructor_id'      => $coach->id,
            'added_by'           => $coach->id,
            'type'               => 'recorded',
            'title'              => $title,
            'slug'               => $slug,
            'seo_description'    => 'Auto-generated bundle — edit title, price, and content from your Courses dashboard.',
            'price'              => $price,
            'discount'           => 0,
            'status'             => 'active',
            'is_approved'        => 'approved',
            'coach_soft_delete'  => 0,
            'preview_seconds'    => 60,
            'description'        => '<p>On-demand sessions from ' . e($coach->name ?? 'your coach') . '. '
                                 . 'Customize this course in <strong>Instructor → Courses</strong> '
                                 . 'to add modules, change pricing, and upload your full content.</p>',
        ];
    }
}
