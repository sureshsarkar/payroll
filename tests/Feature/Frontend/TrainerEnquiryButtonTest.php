<?php

namespace Tests\Feature\Frontend;

use App\Http\Controllers\Frontend\PricingEnquiryController;
use App\Models\CoachPricingEnquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Trainer Details page → "Enquire Now" button → reusable booking modal →
 * coach_pricing_enquiries → Coach Panel → Pricing Enquiries.
 *
 * The button reuses the shared coach.booking-enquiry endpoint. On the coach's
 * custom website the owning coach is resolved from the host (resolved_coach_id
 * stamped by the domain middleware), with the viewed trainer carried as context.
 * Mirrors BookingEnquiryTest's direct-controller approach.
 */
class TrainerEnquiryButtonTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::factory()->create(['role' => 'instructor']);
    }

    private function bookingReq(array $data, ?int $resolvedCoach = null): Request
    {
        $req = Request::create('http://mansi.test/coach/booking-enquiry', 'POST', $data, [], [], [
            'HTTP_USER_AGENT' => 'PHPUnit-Trainer/1.0',
            'REMOTE_ADDR'     => '203.0.113.9',
        ]);
        app()->instance('request', $req);
        if ($resolvedCoach) {
            $req->attributes->set('resolved_coach_id', $resolvedCoach);
        }
        return $req;
    }

    public function test_trainer_enquiry_creates_pricing_enquiry_for_the_coach(): void
    {
        $coach = $this->coach();

        $req = $this->bookingReq([
            'coach_id'      => $coach->id,
            'name'          => 'Riya Verma',
            'email'         => 'riya@example.com',
            'mobile'        => '+91 98765 43210',
            'message'       => 'Interested in personal yoga classes',
            'source_page'   => 'Trainer Detail',
            'source_button' => 'Enquire Now',
            'trainer'       => 'Mansi Rawat',
            'trainer_id'    => (string) $coach->id,
        ], $coach->id);

        $resp = (new PricingEnquiryController())->storeBooking($req);
        $this->assertTrue($resp->getData(true)['ok']);

        $row = CoachPricingEnquiry::forCoach($coach->id)->first();
        $this->assertNotNull($row, 'enquiry must be stored for the coach');
        $this->assertSame('Riya Verma', $row->name);
        $this->assertSame('Trainer Detail', $row->source_page);
        $this->assertSame('Enquire Now', $row->source_button);
        $this->assertSame('Mansi Rawat', $row->reason);   // trainer surfaced in the panel
        $this->assertSame(CoachPricingEnquiry::STATUS_NEW, $row->status);
    }

    public function test_enquiry_cannot_be_forged_onto_a_coach_from_the_platform(): void
    {
        // No resolved_coach_id (bare platform) + no tenant session → F26 rejects
        // the posted coach id, so no CRM poisoning.
        $coach = $this->coach();

        $req = $this->bookingReq([
            'coach_id' => $coach->id,
            'name'     => 'Attacker',
            'email'    => 'a@example.com',
            'mobile'   => '+91 90000 00000',
        ], null);

        $resp = (new PricingEnquiryController())->storeBooking($req);

        $this->assertFalse($resp->getData(true)['ok']);
        $this->assertSame(0, CoachPricingEnquiry::forCoach($coach->id)->count());
    }

    public function test_trainer_detail_view_has_enquire_button_and_modal(): void
    {
        // The page wires the reusable trigger + includes the shared modal.
        $src = file_get_contents(base_path('resources/views/frontend/pages/instructor-details.blade.php'));
        $this->assertStringContainsString('cs-book-trigger', $src);
        $this->assertStringContainsString("data-source-page=\"{{ __('Trainer Detail') }}\"", $src);
        $this->assertStringContainsString('booking-enquiry-modal', $src);
    }
}
