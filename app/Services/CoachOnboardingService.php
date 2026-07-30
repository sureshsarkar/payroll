<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseLiveClass;
use App\Models\User;
use Modules\Order\app\Models\OrderItem;

/**
 * Computes the "Get started" onboarding checklist state for a coach.
 * Each item resolves to ['key', 'label', 'description', 'icon', 'done', 'cta_url'].
 *
 * The progression is intentionally short (6 items) and focused on actions
 * that drive activation/retention — these are the moments a new coach
 * actually realises the platform works for them.
 */
class CoachOnboardingService
{
    /**
     * @return array<int, array{key:string,label:string,description:string,icon:string,done:bool,cta_url:string}>
     */
    public function checklistFor(User $coach): array
    {
        $coachId = $coach->id;

        // Verified email
        $emailVerified = !is_null($coach->email_verified_at);

        // Profile completeness — borrows the same heuristic the profile-completion
        // partial uses (image, bio, phone, address-or-country, gender, job_title).
        $profileFields = [$coach->image, $coach->short_bio ?? $coach->bio, $coach->phone, $coach->state ?? $coach->country_id, $coach->gender, $coach->job_title];
        $profileComplete = collect($profileFields)->filter(fn ($v) => !empty($v))->count() >= 5;

        // Created at least one course
        $hasCourse = Course::where(function ($q) use ($coachId) {
            $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
        })->exists();

        // Scheduled at least one live class
        $hasLiveClass = CourseLiveClass::whereHas('lesson.course', function ($q) use ($coachId) {
            $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
        })->exists();

        // Got at least one paid sale (someone enrolled in a course)
        $hasSale = OrderItem::whereIn('course_id', function ($q) use ($coachId) {
            $q->select('id')->from('courses')
              ->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
        })->whereHas('order', fn ($o) => $o->where('payment_status', 'paid'))->exists();

        // Configured payout method (so they can actually receive earnings).
        // The Payout tab on /instructor/setting writes to instructor_requests
        // (not the users table), so the check has to look at that relation.
        $payoutInfo = $coach->instructorInfo;
        $hasPayout = $payoutInfo
            && (!empty($payoutInfo->payout_account) || !empty($payoutInfo->payout_information));

        return [
            [
                'key'         => 'email',
                'label'       => __('Verify your email'),
                'description' => __('Check your inbox and click the verification link.'),
                'icon'        => 'fa-envelope-open-text',
                'done'        => $emailVerified,
                'cta_url'     => $emailVerified ? '#' : '#',
            ],
            [
                'key'         => 'profile',
                'label'       => __('Complete your profile'),
                'description' => __('Add photo, bio, contact + bank info — students trust complete profiles.'),
                'icon'        => 'fa-user-edit',
                'done'        => $profileComplete,
                'cta_url'     => route('instructor.setting.index'),
            ],
            [
                'key'         => 'first_course',
                'label'       => __('Create your first course'),
                'description' => __('Build a course outline. You can publish later — just get the structure in.'),
                'icon'        => 'fa-graduation-cap',
                'done'        => $hasCourse,
                'cta_url'     => route('instructor.courses.create'),
            ],
            [
                'key'         => 'live_class',
                'label'       => __('Schedule a live class'),
                'description' => __('Live sessions are the #1 retention driver — schedule one inside any course.'),
                'icon'        => 'fa-video',
                'done'        => $hasLiveClass,
                'cta_url'     => route('instructor.live-classes.index'),
            ],
            [
                'key'         => 'first_sale',
                'label'       => __('Get your first sale'),
                'description' => __('Share your course link or invite a student. The first sale is always the hardest.'),
                'icon'        => 'fa-shopping-cart',
                'done'        => $hasSale,
                'cta_url'     => route('instructor.my-sells.index'),
            ],
            [
                'key'         => 'payout',
                'label'       => __('Set up payouts'),
                'description' => __('Tell us where to send your earnings before your first withdrawal.'),
                'icon'        => 'fa-money-check-alt',
                'done'        => $hasPayout,
                'cta_url'     => route('instructor.setting.index'),
            ],
        ];
    }

    public function progress(User $coach): array
    {
        $items = $this->checklistFor($coach);
        $done  = collect($items)->where('done', true)->count();
        $total = count($items);
        return [
            'done'    => $done,
            'total'   => $total,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            'items'   => $items,
        ];
    }
}
