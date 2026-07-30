<?php

namespace App\Services;

use App\Models\CoachDomain;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Auto-assign a platform subdomain to a coach.
 *
 * Every coach gets a white-label URL out of the box:
 *
 *   acme-coaching.<platform-host>
 *
 * The slug is derived from their name (Str::slug + lowercase),
 * collision-safe (append a counter on dupes), and the resulting
 * subdomain is stored as a kind='subdomain' row with verified_at
 * set immediately — we own the parent domain, so there's no DNS
 * proof step.
 *
 * Two call sites:
 *
 *   1. User model 'created' event for new instructors — runs on
 *      signup so every fresh coach has a subdomain from day one.
 *
 *   2. Backfill migration for existing coaches who don't yet have
 *      ANY coach_domains row.
 *
 * Idempotent: ensureForCoach() is a no-op if the coach already has
 * any domain row (custom or subdomain). Calling it on every login
 * would be safe.
 */
class SubdomainAssigner
{
    /**
     * Make sure this coach has at least one coach_domains row.
     * Returns the row (newly created or already-existing).
     */
    public function ensureForCoach(User $coach): ?CoachDomain
    {
        if (($coach->role ?? null) !== 'instructor') {
            return null;
        }

        $existing = CoachDomain::where('coach_id', $coach->id)->first();
        if ($existing) {
            return $existing;
        }

        $platformHost = $this->platformHost();
        if (! $platformHost) {
            // Can't generate a subdomain without a parent host
            // (e.g. localhost on dev without COACH_DOMAIN set).
            // Skip silently — coach can still add a custom domain
            // manually via the brand-settings UI.
            return null;
        }

        $slug = $this->uniqueSlugFor($coach);
        $host = "$slug.$platformHost";

        // Guard the unique(hostname) constraint at the controller
        // layer with one more existence check — protects against
        // a race where two coaches with similar names sign up at
        // the same instant.
        if (CoachDomain::where('hostname', $host)->exists()) {
            $host = "$slug-" . strtolower(Str::random(4)) . '.' . $platformHost;
        }

        return CoachDomain::create([
            'coach_id'    => $coach->id,
            'hostname'    => $host,
            'kind'        => 'subdomain',
            'is_primary'  => true,
            'verified_at' => now(), // platform-controlled host = trusted
            'status'      => CoachDomain::STATUS_ACTIVE,
        ]);
    }

    /**
     * Generate a slug from the coach's name, ensure global uniqueness
     * across coach_domains.hostname by appending -2 / -3 / ... on
     * collision.
     */
    public function uniqueSlugFor(User $coach): string
    {
        $base = Str::slug((string) ($coach->name ?? '')) ?: ('coach-' . $coach->id);
        // Cap at 40 chars so the final host (slug + . + platform-host)
        // stays well under the 255 DNS limit + remains readable.
        $base = substr($base, 0, 40);

        $platformHost = $this->platformHost();
        if (! $platformHost) return $base;

        $attempt = $base;
        $counter = 2;
        while (CoachDomain::where('hostname', "$attempt.$platformHost")->exists()) {
            $attempt = "$base-$counter";
            $counter++;
            if ($counter > 999) {
                // Give up at 999 dupes — fall back to a random suffix.
                return $base . '-' . strtolower(Str::random(6));
            }
        }
        return $attempt;
    }

    /**
     * The host portion used as the suffix for auto-generated
     * subdomains. Driven by COACH_DOMAIN env, falls back to the
     * APP_URL host, falls back to 'localhost'.
     *
     * Returns null when the resolved host is literally 'localhost'
     * — auto-generating "acme.localhost" doesn't help anyone and
     * makes the test DB noisy.
     */
    public function platformHost(): ?string
    {
        $host = config('app.coach_domain', null);
        if (! $host || $host === 'localhost') return null;
        return strtolower(trim($host));
    }
}
