<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-coach white-label — Phase 2 (host-based tenant resolution).
 *
 * One row per (coach, hostname) the platform should recognise as
 * belonging to that coach. When a request lands on that hostname,
 * the ResolveCoachByDomain middleware stamps the coach id onto the
 * request and BrandResolver::current() returns that coach's brand.
 *
 * kind:
 *   subdomain   coach1.platform.com — hosted on the platform's own
 *               wildcard cert. No DNS work needed.
 *   custom      coach1.com — coach owns the domain, points DNS at
 *               our IP. SSL is the operator's concern (handled by
 *               Caddy auto-provisioning, Cloudflare for SaaS, or
 *               manual cert install — out of scope for P2).
 *
 * is_primary:
 *   The single hostname used for canonical URL generation when
 *   sending emails / building share links for this coach. Most
 *   coaches will have exactly one row, marked primary.
 *
 * verified_at:
 *   For 'custom' hosts, set when the coach proves DNS control via
 *   the TXT-record check (Phase 5 onboarding wizard). 'subdomain'
 *   rows are verified at creation time (we control the platform
 *   domain).
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_domains')) {
            Schema::create('coach_domains', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('coach_id');
                $table->foreign('coach_id')->references('id')->on('users')
                      ->onDelete('cascade');

                // Lower-case hostname, no protocol or port.
                $table->string('hostname', 255);

                $table->enum('kind', ['subdomain', 'custom'])->default('subdomain');

                $table->boolean('is_primary')->default(false);
                $table->timestamp('verified_at')->nullable();

                $table->timestamps();

                // Hostname must be globally unique — same DNS name
                // can only resolve to one coach.
                $table->unique('hostname', 'cd_unique_hostname');
                $table->index(['coach_id', 'is_primary'], 'cd_coach_primary_idx');
            });
        }

        $this->backfillFromLandingPages();
    }

    /**
     * Hydrate from coach_landing_pages.subdomain + main_domain. Each
     * landing page may carry 0, 1, or 2 hostnames. We insert one row
     * per non-empty hostname, marking the FIRST one we see for a coach
     * as primary.
     *
     * Idempotent — the unique(hostname) index drops duplicates from
     * subsequent runs / re-imports via insertOrIgnore.
     */
    protected function backfillFromLandingPages(): void
    {
        if (! Schema::hasTable('coach_landing_pages')) return;

        $now = now();
        $primarySeen = [];

        $pages = DB::table('coach_landing_pages')
            ->select('added_by as coach_id', 'subdomain', 'main_domain')
            ->whereNotNull('added_by')
            ->get();

        foreach ($pages as $p) {
            // Defensive: coach must exist (some legacy added_by rows
            // point at deleted users).
            $coachExists = DB::table('users')->where('id', $p->coach_id)
                ->where('role', 'instructor')->exists();
            if (! $coachExists) continue;

            foreach ([
                ['hostname' => $p->subdomain,   'kind' => 'subdomain'],
                ['hostname' => $p->main_domain, 'kind' => 'custom'],
            ] as $entry) {
                $host = strtolower(trim((string) $entry['hostname']));
                if ($host === '' || $host === 'null') continue;

                // Strip protocol + port if anyone stored them.
                $host = preg_replace('#^https?://#i', '', $host);
                $host = explode('/', $host, 2)[0];
                $host = explode(':', $host, 2)[0];

                $isPrimary = ! isset($primarySeen[$p->coach_id]);
                $primarySeen[$p->coach_id] = true;

                DB::table('coach_domains')->insertOrIgnore([
                    'coach_id'    => $p->coach_id,
                    'hostname'    => $host,
                    'kind'        => $entry['kind'],
                    'is_primary'  => $isPrimary,
                    // subdomains are auto-verified (we control them);
                    // custom domains are unverified until the coach
                    // proves DNS control in Phase 5.
                    'verified_at' => $entry['kind'] === 'subdomain' ? $now : null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_domains');
    }
};
