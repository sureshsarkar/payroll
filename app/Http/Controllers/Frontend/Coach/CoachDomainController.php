<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Per-coach white-label — Phase 5 (custom-domain onboarding).
 *
 * Three endpoints:
 *
 *   POST /instructor/domains              add a custom hostname
 *                                          (status=unverified, owner = current coach)
 *   POST /instructor/domains/{id}/verify  attempt DNS TXT verification;
 *                                          on success stamp verified_at
 *   DELETE /instructor/domains/{id}       remove a hostname from coach
 *
 * Verification model — TXT record on the apex:
 *   The coach is given a token like "mbsguru-verify=<8 random chars>".
 *   They add it as a TXT record on the hostname (or its parent — both
 *   are checked). We DNS-resolve the TXT records via PHP's dns_get_record
 *   and look for the token among them. If found within the lookup
 *   window, mark verified_at = now() and the host becomes a valid
 *   tenant lookup target for the P2 middleware.
 *
 * Subdomain self-service is automatic on coach creation — coach signs
 * up, gets a row at acme.<platform domain> with kind=subdomain and
 * verified_at=now (platform owns the cert). Only custom-domain
 * onboarding needs the manual DNS dance.
 *
 * Auth: instructor role only. IDOR-gated — coach can only verify /
 * delete domains they own.
 */
class CoachDomainController extends Controller
{
    /**
     * Add a custom hostname to this coach.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(userAuth()?->role === 'instructor', 403);

        $settings = app(\App\Services\CustomDomainSettings::class);
        if (! $settings->enabled()) {
            return back()->withErrors(['hostname' => __('Custom domains are not available on your plan. Please contact support.')]);
        }

        $request->validate([
            // Permissive — coach can paste with protocol/whitespace,
            // CoachDomain::normalise() cleans it up server-side.
            'hostname' => ['required', 'string', 'max:255'],
        ]);

        $host = CoachDomain::normalise($request->input('hostname'));
        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host)) {
            return back()->withErrors(['hostname' => __('Enter a valid hostname like coach1.com or yoga.coach1.com')]);
        }

        $coachId = (int) userAuth()->id;

        // Block submitting the platform's own host / a platform subdomain
        // through the custom-domain flow (that is the subdomain feature).
        $platformHost = strtolower(trim((string) config('app.coach_domain', '')));
        if ($platformHost !== '' && $platformHost !== 'localhost'
            && ($host === $platformHost || str_ends_with($host, '.' . $platformHost))) {
            return back()->withErrors(['hostname' => __('Use the instant subdomain option above for this domain.')]);
        }

        // UNIQUE constraint catches the cross-coach duplicate at the
        // DB level; we pre-check here for a friendlier message.
        $existing = CoachDomain::where('hostname', $host)->first();
        if ($existing) {
            if ($existing->coach_id === $coachId) {
                return back()->withErrors(['hostname' => __('You\'ve already added this hostname.')]);
            }
            return back()->withErrors(['hostname' => __('This domain is already connected to another account. Contact support if this is yours.')]);
        }

        // Per-coach quota.
        $count = CoachDomain::where('coach_id', $coachId)->where('kind', 'custom')->count();
        if ($count >= $settings->maxPerCoach()) {
            return back()->withErrors(['hostname' => __('You have reached your custom-domain limit.')]);
        }

        $row = CoachDomain::create([
            'coach_id'    => $coachId,
            'hostname'    => $host,
            'kind'        => 'custom',
            'is_primary'  => false,
            'verified_at' => null,
            'status'      => CoachDomain::STATUS_PENDING,
        ]);
        CoachDomain::recordEvent($row, 'added', [
            'actor_type' => 'coach', 'actor_id' => $coachId, 'ip' => $request->ip(),
            'meta' => ['kind' => 'custom'],
        ]);

        return back()->with([
            'messege'    => __('Domain added — verify it below to start serving traffic.'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Attempt DNS TXT verification.
     *
     * We resolve the TXT records for the hostname and look for the
     * coach's verification token. If found, mark verified_at = now,
     * which lets the P2 middleware start resolving this host to
     * this coach.
     */
    public function verify(int $id): JsonResponse
    {
        abort_unless(userAuth()?->role === 'instructor', 403);

        $row = CoachDomain::where('id', $id)
            ->where('coach_id', (int) userAuth()->id)
            ->where('kind', 'custom')
            ->firstOrFail();

        if ($row->verified_at && $row->status === CoachDomain::STATUS_ACTIVE) {
            return response()->json([
                'ok'      => true,
                'message' => __('Already verified.'),
                'verified_at' => $row->verified_at->toIso8601String(),
            ]);
        }

        // 2026-06-06 — SIMPLEST path first: if the domain already POINTS to us
        // (an A record at our platform IP, or a CNAME to our platform host) it
        // is verified — no separate TXT record needed. This is the one-record
        // onboarding that replaces the old TXT + A two-step.
        if ($this->resolvesToPlatform($row->hostname)) {
            return $this->applyVerified($row, 'a_or_cname');
        }

        // Fallback: legacy TXT verification (still honoured for anyone who
        // already set it up that way).
        $token   = $this->tokenFor($row);
        $records = $this->lookupTxt($row->hostname);

        $matched = collect($records)->contains(function ($r) use ($token) {
            $val = is_array($r) ? ($r['txt'] ?? ($r['entries'][0] ?? '')) : (string) $r;
            return is_string($val) && stripos($val, $token) !== false;
        });

        if (! $matched) {
            // Record the failed attempt + reason, but never downgrade a row
            // that is already verified/active.
            $error = __('Domain does not point to us yet. Add the A or CNAME record and try again — DNS can take a few minutes to propagate.');
            if ($row->status !== CoachDomain::STATUS_ACTIVE && $row->status !== CoachDomain::STATUS_VERIFIED) {
                $row->markStatus(CoachDomain::STATUS_FAILED, [
                    'fill'  => ['verify_attempts' => (int) $row->verify_attempts + 1, 'last_error' => $error],
                    'event' => 'failed',
                    'actor_type' => 'coach', 'actor_id' => (int) userAuth()->id, 'ip' => request()->ip(),
                    'meta'  => ['records' => count($records)],
                ]);
            }
            Log::info('coach-domain-verify-miss', [
                'coach_id' => $row->coach_id,
                'host'     => $row->hostname,
                'records'  => count($records),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => $error,
                'token'   => $token,
            ]);
        }

        return $this->applyVerified($row, 'txt');
    }

    /**
     * Common "DNS proof succeeded" transition. In strict mode (superadmin
     * requires approval) the domain becomes VERIFIED and waits for an admin;
     * otherwise it goes straight to ACTIVE (live + serving).
     */
    protected function applyVerified(CoachDomain $row, string $method): JsonResponse
    {
        $requiresApproval = app(\App\Services\CustomDomainSettings::class)->requiresApproval();
        $newStatus = $row->applyDnsVerified($requiresApproval, [
            'method' => $method,
            'actor_type' => 'coach', 'actor_id' => (int) userAuth()->id, 'ip' => request()->ip(),
        ]);

        if ($newStatus === CoachDomain::STATUS_VERIFIED) {
            return response()->json([
                'ok'      => true,
                'pending_approval' => true,
                'message' => __('DNS verified. Your domain is awaiting administrator approval.'),
            ]);
        }

        // Notify the coach their custom domain is now live (coach-branded).
        try {
            $coach = \App\Models\User::find($row->coach_id);
            if ($coach) {
                $coach->notify(new \App\Notifications\CoachDomainLiveToCoach($row->hostname));
            }
        } catch (\Throwable $e) {
            \Log::warning('domain-live notify failed: ' . $e->getMessage());
        }

        return response()->json([
            'ok'          => true,
            'message'     => __('Verified! Your domain is live.'),
            'verified_at' => $row->fresh()->verified_at?->toIso8601String(),
        ]);
    }

    /**
     * 2026-06-06 — Instant subdomain self-service. The coach picks a label and
     * gets <label>.<platform-host> immediately (verified_at=now — we own the
     * parent domain, so no DNS proof is needed). Renames the coach's existing
     * subdomain row, or creates one. Requires a wildcard DNS + TLS for
     * *.<platform-host> on the server (one-time operator setup).
     */
    public function setSubdomain(Request $request): RedirectResponse
    {
        abort_unless(userAuth()?->role === 'instructor', 403);

        $request->validate(['slug' => ['required', 'string', 'max:40']]);

        $platformHost = strtolower(trim((string) config('app.coach_domain', '')));
        if ($platformHost === '' || $platformHost === 'localhost') {
            return back()->withErrors(['slug' => __('Instant subdomains are not available on this environment.')]);
        }

        $slug = strtolower(trim((string) $request->input('slug')));
        $slug = trim(preg_replace('/[^a-z0-9-]/', '', $slug), '-');

        if (strlen($slug) < 3 || ! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $slug)) {
            return back()->withErrors(['slug' => __('Use 3–40 letters, numbers or hyphens (e.g. yoga-with-virendra).')]);
        }

        $reserved = ['www', 'admin', 'api', 'app', 'mail', 'ftp', 'cdn', 'static', 'assets',
            'test', 'staging', 'demo', 'blog', 'help', 'support', 'dashboard', 'instructor',
            'student', 'coach', 'login', 'register', 'account', 'billing'];
        if (in_array($slug, $reserved, true)) {
            return back()->withErrors(['slug' => __('That subdomain is reserved. Please choose another.')]);
        }

        $host    = "$slug.$platformHost";
        $coachId = (int) userAuth()->id;

        if (CoachDomain::where('hostname', $host)->where('coach_id', '!=', $coachId)->exists()) {
            return back()->withErrors(['slug' => __('That subdomain is already taken. Please choose another.')]);
        }

        $row = CoachDomain::where('coach_id', $coachId)->where('kind', 'subdomain')->first();
        if ($row) {
            $oldHost = $row->hostname;
            $row->forceFill([
                'hostname'    => $host,
                'verified_at' => now(),
                'is_primary'  => true,
                'status'      => CoachDomain::STATUS_ACTIVE,
            ])->save();
            CoachDomain::forgetCacheForHost($oldHost);
            CoachDomain::recordEvent($row, 'renamed', [
                'actor_type' => 'coach', 'actor_id' => $coachId,
                'ip' => $request->ip(), 'meta' => ['from' => $oldHost, 'to' => $host],
            ]);
        } else {
            $row = CoachDomain::create([
                'coach_id' => $coachId, 'hostname' => $host, 'kind' => 'subdomain',
                'is_primary' => true, 'verified_at' => now(),
                'status' => CoachDomain::STATUS_ACTIVE,
            ]);
            CoachDomain::recordEvent($row, 'added', [
                'actor_type' => 'coach', 'actor_id' => $coachId,
                'ip' => $request->ip(), 'meta' => ['kind' => 'subdomain'],
            ]);
        }

        // Notify the coach their subdomain is live (coach-branded).
        try {
            userAuth()->notify(new \App\Notifications\CoachDomainLiveToCoach($host));
        } catch (\Throwable $e) {
            \Log::warning('subdomain-live notify failed: ' . $e->getMessage());
        }

        return back()->with([
            'messege'    => __('Your branded link is ready: ') . 'https://' . $host,
            'alert-type' => 'success',
        ]);
    }

    /**
     * True when the host's DNS already points at this platform — an A record at
     * one of our IPs, or a CNAME to our platform host (or a subdomain of it).
     */
    protected function resolvesToPlatform(string $host): bool
    {
        $platformIps  = $this->platformIps();
        foreach ($this->lookupA($host) as $rec) {
            $ip = is_array($rec) ? (string) ($rec['ip'] ?? '') : '';
            if ($ip !== '' && in_array($ip, $platformIps, true)) {
                return true;
            }
        }

        $platformHost = strtolower(trim((string) config('app.coach_domain', '')));
        if ($platformHost !== '' && $platformHost !== 'localhost') {
            foreach ($this->lookupCname($host) as $rec) {
                $target = strtolower(rtrim(is_array($rec) ? (string) ($rec['target'] ?? '') : '', '.'));
                if ($target !== '' && ($target === $platformHost || str_ends_with($target, '.' . $platformHost))) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Platform public IP(s) — superadmin setting → config → resolved from the platform host. */
    protected function platformIps(): array
    {
        // Superadmin-set IP(s) take precedence (the value shown to coaches).
        $configured = app(\App\Services\CustomDomainSettings::class)->serverIps();
        if (! empty($configured)) {
            return $configured;
        }
        $host = strtolower(trim((string) config('app.coach_domain', '')));
        if ($host === '' || $host === 'localhost') {
            return [];
        }
        return collect($this->lookupA($host))
            ->map(fn ($r) => is_array($r) ? (string) ($r['ip'] ?? '') : '')
            ->filter()->values()->all();
    }

    /** Resolve A records. Isolated so tests can mock it. */
    protected function lookupA(string $host): array
    {
        try {
            $r = @dns_get_record($host, DNS_A);
            return is_array($r) ? $r : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Resolve CNAME records. Isolated so tests can mock it. */
    protected function lookupCname(string $host): array
    {
        try {
            $r = @dns_get_record($host, DNS_CNAME);
            return is_array($r) ? $r : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Soft remove a coach's custom domain.
     * (Hard delete is fine — there's no audit obligation on a
     * removed white-label domain; the coach can just re-add it.)
     */
    public function destroy(int $id): RedirectResponse
    {
        abort_unless(userAuth()?->role === 'instructor', 403);

        $row = CoachDomain::where('id', $id)
            ->where('coach_id', (int) userAuth()->id)
            ->firstOrFail();

        // Subdomains are platform-managed; coach can't remove their
        // own subdomain (would break their default white-label URL).
        // Only custom domains are deletable through this endpoint.
        abort_unless($row->kind === 'custom', 403, 'Subdomains are managed by the platform.');

        $row->delete();

        return back()->with([
            'messege'    => __('Domain removed.'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Generate a stable verification token from the row id +
     * coach id. Stable so the coach doesn't have to update DNS if
     * they navigate away mid-verification.
     */
    public function tokenFor(CoachDomain $row): string
    {
        // 8 chars derived from a sha1 of (id, coach_id, app key) —
        // not reversible without the key, but deterministic for
        // repeated checks.
        $seed = $row->id . ':' . $row->coach_id . ':' . config('app.key');
        return 'mbsguru-verify=' . substr(sha1($seed), 0, 12);
    }

    /**
     * Resolve TXT records for the host. Isolated so tests can mock it.
     */
    protected function lookupTxt(string $host): array
    {
        try {
            $r = @dns_get_record($host, DNS_TXT);
            return is_array($r) ? $r : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
