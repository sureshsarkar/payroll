<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\Coach\TrainerController;
use App\Models\CoachPricingEnquiry;
use App\Models\CoachTrainer;
use App\Models\TrainerSessionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trainer feature Phase 3 — dedicated coach-panel Trainer Bookings list.
 *
 * The list shows ONLY enquiry_type = trainer_personal_session, is strictly
 * coach-scoped, honours the trainer/package/payment/date filters, and computes
 * paid/pending/collected KPIs. The `trainers` staff-permission catalog is seeded.
 */
class TrainerBookingsPanelTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::find(DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no', 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function trainer(User $coach): CoachTrainer
    {
        return CoachTrainer::create([
            'coach_id' => $coach->id, 'name' => 'Virendra',
            'slug' => CoachTrainer::uniqueSlug($coach->id, 'V-' . uniqid()), 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function pkg(CoachTrainer $t): TrainerSessionPackage
    {
        return TrainerSessionPackage::create([
            'trainer_id' => $t->id, 'coach_id' => $t->coach_id, 'name' => 'Starter',
            'sessions' => 5, 'validity_value' => 15, 'validity_unit' => 'days', 'price' => 7000, 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function booking(User $coach, CoachTrainer $t, TrainerSessionPackage $p, string $pay, ?float $paid = null): CoachPricingEnquiry
    {
        return CoachPricingEnquiry::create([
            'coach_id' => $coach->id, 'enquiry_type' => CoachPricingEnquiry::TYPE_TRAINER_SESSION,
            'trainer_ref_id' => $t->id, 'trainer_package_id' => $p->id,
            'category' => $t->name, 'time_period' => $p->label(),
            'name' => 'Zoe', 'email' => 'z' . uniqid() . '@t.local', 'mobile' => '7897897897',
            'status' => 'new', 'plan_amount' => 7000, 'paid_amount' => $paid, 'currency' => 'INR', 'payment_status' => $pay,
        ]);
    }

    /** Invoke the panel as the given coach; returns the view data array. */
    private function panel(User $coach, array $query = []): array
    {
        $req = Request::create('/instructor/trainers/bookings', 'GET', $query);
        $req->setUserResolver(fn () => $coach);
        $this->app->instance('request', $req);
        \Illuminate\Support\Facades\Auth::setUser($coach);
        return app(TrainerController::class)->bookings($req)->getData();
    }

    public function test_only_trainer_bookings_are_listed_and_tenant_scoped(): void
    {
        $coach = $this->coach();
        $t = $this->trainer($coach);
        $p = $this->pkg($t);
        $this->booking($coach, $t, $p, 'paid', 7000);

        // A plain pricing enquiry for the same coach must NOT appear.
        CoachPricingEnquiry::create([
            'coach_id' => $coach->id, 'name' => 'Other', 'email' => 'o@t.local', 'mobile' => '7897897897',
            'status' => 'new', 'category' => 'Online', 'time_period' => '1 Month', 'payment_status' => 'unpaid',
        ]);
        // Another coach's trainer booking must NOT appear.
        $c2 = $this->coach(); $t2 = $this->trainer($c2); $p2 = $this->pkg($t2);
        $this->booking($c2, $t2, $p2, 'paid', 7000);

        $data = $this->panel($coach);
        $this->assertCount(1, $data['enquiries']->items());
        $this->assertSame(CoachPricingEnquiry::TYPE_TRAINER_SESSION, $data['enquiries']->items()[0]->enquiry_type);
    }

    public function test_kpis_count_paid_pending_and_sum_collected(): void
    {
        $coach = $this->coach();
        $t = $this->trainer($coach);
        $p = $this->pkg($t);
        $this->booking($coach, $t, $p, 'paid', 7000);
        $this->booking($coach, $t, $p, 'paid', 7000);
        $this->booking($coach, $t, $p, 'pending');

        $k = $this->panel($coach)['kpis'];
        $this->assertSame(3, $k['total']);
        $this->assertSame(2, $k['paid']);
        $this->assertSame(1, $k['pending']);
        $this->assertSame(14000.0, (float) $k['collected']);
    }

    public function test_payment_and_trainer_filters_apply(): void
    {
        $coach = $this->coach();
        $t1 = $this->trainer($coach); $p1 = $this->pkg($t1);
        $t2 = $this->trainer($coach); $p2 = $this->pkg($t2);
        $this->booking($coach, $t1, $p1, 'paid', 7000);
        $this->booking($coach, $t2, $p2, 'pending');

        $paid = $this->panel($coach, ['payment' => 'paid']);
        $this->assertCount(1, $paid['enquiries']->items());

        $byT2 = $this->panel($coach, ['trainer_id' => $t2->id]);
        $this->assertCount(1, $byT2['enquiries']->items());
        $this->assertSame((int) $t2->id, (int) $byT2['enquiries']->items()[0]->trainer_ref_id);
    }

    public function test_trainers_permission_catalog_is_seeded(): void
    {
        foreach (['trainers', 'trainers-create', 'trainers-edit', 'trainers-delete'] as $slug) {
            $this->assertTrue(
                DB::table('coach_staff_permissions')->where('slug', $slug)->exists(),
                "permission slug {$slug} seeded"
            );
        }
    }
}
