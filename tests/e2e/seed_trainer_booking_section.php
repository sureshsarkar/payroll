<?php
require 'D:/xampp/htdocs/MBSGuru1/vendor/autoload.php';
$app = require 'D:/xampp/htdocs/MBSGuru1/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CoachLandingPage;
use App\Models\CoachPage;
use App\Models\CoachPageSection;

$lp = CoachLandingPage::orderBy('id')->first();
$coachId = (int) $lp->added_by;
$siteSlug = $lp->slug;
$pageSlug = 'qa-trainer-booking-e2e';

// Idempotent: drop any prior test page + its sections.
$old = CoachPage::where('coach_id', $coachId)->where('slug', $pageSlug)->first();
if ($old) {
    CoachPageSection::where('coach_page_id', $old->id)->delete();
    $old->delete();
}

$page = CoachPage::create([
    'coach_id'     => $coachId,
    'slug'         => $pageSlug,
    'page_type'    => 'custom',
    'title'        => 'QA Trainer Booking E2E',
    'is_published' => 1,
]);

CoachPageSection::create([
    'coach_page_id' => $page->id,
    'section_type'  => 'trainer_booking_v1',
    'content_json'  => [
        'trainer_name'  => 'Mansi Rawat',
        'heading'       => 'Book a Personal Session',
        'currency'      => 'INR',
        'show_packages' => true,
        'button_text'   => 'Book Now',
        'packages'      => [
            ['label' => '5 Session Validity 10 days', 'price' => '6000'],
            ['label' => '10 Session Validity 25 days', 'price' => '10000'],
        ],
        'plan_types'    => ['Online', 'Offline'],
        'course_types'  => ['Individual Plan', 'Couple Plan'],
        'reasons'       => ['Fitness', 'Weight Loss', 'Problem'],
    ],
    'is_visible'  => 1,
    'sort_order'  => 1,
]);

echo $siteSlug . '|' . $pageSlug . '|' . $page->id;
