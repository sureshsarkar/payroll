<?php

namespace Tests\Feature\Domain;

use App\Models\CoachDomain;
use App\Models\User;
use App\Services\SubdomainAssigner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Per-coach white-label — Phase 6 (auto-subdomain on coach signup).
 *
 * Five contracts:
 *
 *   1. ensureForCoach creates a subdomain row on a real
 *      (non-localhost) platform host.
 *   2. ensureForCoach skips when platform host is unset/localhost
 *      (don't generate junky .localhost rows on dev).
 *   3. ensureForCoach is idempotent — second call returns the
 *      existing row, no duplicate insert.
 *   4. uniqueSlugFor avoids collisions by appending -2 / -3 / ...
 *   5. The User model 'created' event auto-creates a subdomain
 *      for new instructors (proves the wiring fires).
 */
class CoachSubdomainAutoAssignTest extends TestCase
{
    use DatabaseTransactions;

    /* ───────── assigner core ───────── */

    public function test_ensure_creates_subdomain_when_platform_host_is_set(): void
    {
        config(['app.coach_domain' => 'platform.test']);

        $coach = User::factory()->create([
            'role' => 'instructor',
            'name' => 'Acme Coaching',
        ]);

        // The User-creation event already ran the assigner — pull
        // the row it created.
        $row = CoachDomain::where('coach_id', $coach->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('subdomain', $row->kind);
        $this->assertTrue((bool) $row->is_primary);
        $this->assertNotNull($row->verified_at,
            'platform-owned subdomain must be verified at creation');
        $this->assertStringEndsWith('.platform.test', $row->hostname);
        $this->assertStringContainsString('acme', $row->hostname);
    }

    public function test_ensure_skips_when_platform_host_is_localhost(): void
    {
        config(['app.coach_domain' => 'localhost']);

        $coach = User::factory()->create([
            'role' => 'instructor',
            'name' => 'Local Dev Coach',
        ]);

        $this->assertSame(0,
            CoachDomain::where('coach_id', $coach->id)->count(),
            'localhost host must not generate auto-subdomains'
        );
    }

    public function test_ensure_is_idempotent(): void
    {
        config(['app.coach_domain' => 'platform.test']);

        $coach = User::factory()->create([
            'role' => 'instructor',
            'name' => 'Idem Coach',
        ]);

        $first = CoachDomain::where('coach_id', $coach->id)->first();
        $this->assertNotNull($first);

        // Manually re-invoke — must return the same row, no duplicate.
        $again = app(SubdomainAssigner::class)->ensureForCoach($coach);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, CoachDomain::where('coach_id', $coach->id)->count());
    }

    /* ───────── slug uniqueness ───────── */

    public function test_unique_slug_appends_counter_on_collision(): void
    {
        config(['app.coach_domain' => 'platform.test']);

        $c1 = User::factory()->create(['role' => 'instructor', 'name' => 'Clash Coach']);
        $c2 = User::factory()->create(['role' => 'instructor', 'name' => 'Clash Coach']);

        $h1 = CoachDomain::where('coach_id', $c1->id)->value('hostname');
        $h2 = CoachDomain::where('coach_id', $c2->id)->value('hostname');

        $this->assertNotSame($h1, $h2,
            'two coaches with identical names must get distinct subdomains'
        );
        $this->assertStringStartsWith('clash-coach.', $h1);
        $this->assertStringStartsWith('clash-coach-', $h2);
    }

    public function test_creation_event_does_not_assign_for_non_instructors(): void
    {
        config(['app.coach_domain' => 'platform.test']);

        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'Some Student',
        ]);

        $this->assertSame(0,
            CoachDomain::where('coach_id', $student->id)->count(),
            'students must not get coach subdomains'
        );
    }

    /* ───────── degraded paths ───────── */

    public function test_platform_host_returns_null_for_localhost(): void
    {
        config(['app.coach_domain' => 'localhost']);
        $this->assertNull(app(SubdomainAssigner::class)->platformHost());
    }

    public function test_platform_host_returns_configured_value(): void
    {
        config(['app.coach_domain' => 'MIXED.case.test']);
        $this->assertSame('mixed.case.test',
            app(SubdomainAssigner::class)->platformHost(),
            'platform host must be lowercased + trimmed'
        );
    }
}
