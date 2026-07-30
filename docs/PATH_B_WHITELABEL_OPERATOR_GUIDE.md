# Path B — Per-Coach White-Label Operator Guide

Path B = "one platform install, N coaches, each coach gets their own
branded experience (logo / colors / domain / email) that their
students see end-to-end with the word 'MBSGuru' nowhere visible."

This guide is for the **platform operator** — the person who runs
the server, points DNS, installs SSL certs, and onboards coaches.
It is NOT for coaches themselves; they self-serve everything via
`/instructor/brand-settings` once the operator-side pieces are in
place.

Code phases P1-P6 (commits `3036e27`, `a1fa054`, `82a19c4`,
`806ad38`, `53e04d2`, `e92553d`) are feature-complete. What remains
is one-time infrastructure setup.

---

## Required environment variables

Add to `.env` BEFORE running migrations on a fresh deploy:

```dotenv
# Parent host for auto-generated coach subdomains. Used to build
# acme-coaching.<this-value> when a coach signs up. Falls back to
# APP_URL's host if unset. Leave as 'localhost' or unset to DISABLE
# auto-subdomain assignment (development default — generating
# .localhost rows is just noise).
COACH_DOMAIN=mbsguru.com

# Public IP a coach points a CUSTOM domain at (the A record we hand
# them in the UI) so the platform can AUTO-VERIFY by "does this domain
# resolve to us?" instead of a TXT step. Comma-separate for multiple
# origins. If left blank the verify flow falls back to resolving
# COACH_DOMAIN's own A record at check time, so this is optional — but
# setting it explicitly is faster and avoids a DNS round-trip per
# verify. Set it to the SAME <SERVER_IP> your wildcard A record uses.
COACH_PLATFORM_IP=203.0.113.10

# Should already be set; per-coach white-label uses these for
# fallback resolution when a coach hasn't overridden a field.
APP_URL=https://mbsguru.com
MAIL_FROM_ADDRESS=hello@mbsguru.com
MAIL_FROM_NAME="Platform Default"
```

Verify:
```bash
php artisan tinker --execute="echo config('app.coach_domain');"
# Expect: mbsguru.com   (NOT localhost)
```

---

## DNS setup (one-time, per platform install)

The platform needs to serve TWO domain shapes:

### 1. The platform's own root + admin

```
mbsguru.com                       A   <SERVER_IP>   ; platform marketing site
admin.mbsguru.com  (optional)     A   <SERVER_IP>   ; admin panel
```

### 2. Wildcard subdomain for auto-generated coach white-label

```
*.mbsguru.com                     A   <SERVER_IP>
```

This is what makes `acme-coaching.mbsguru.com`,
`yoga-with-priya.mbsguru.com`, etc. all resolve to your server
without per-coach DNS work. Cloudflare / Route 53 / Namecheap
all support wildcard A records.

### 3. Custom coach domains (per-coach, optional)

When a coach adds `acme.com` in the brand-settings UI, THEY add **one
DNS record** at their own provider pointing the domain at the platform:

```
acme.com        A      <SERVER_IP>          ; the COACH_PLATFORM_IP value
# or, for a sub-host:
shop.acme.com   CNAME  mbsguru.com          ; the COACH_DOMAIN value
```

Then they click **Verify**. The platform resolves the domain and, if it
already points at us (A → `COACH_PLATFORM_IP`, or CNAME → `COACH_DOMAIN`),
stamps `verified_at` and the P2 middleware starts serving that host.

> **2026-06 simplification.** The old flow required a *separate TXT
> record* in addition to the A record. That TXT step is gone — pointing
> the domain at us IS the proof of control (you can't point a domain you
> don't own). The TXT path still works as a fallback (shown under
> "Prefer to verify by TXT record instead?" in the UI) for coaches whose
> DNS provider makes apex A/CNAME awkward, but it's no longer the
> headline instruction. The platform side still requires nothing per
> coach.

---

## SSL setup — three valid choices

The verify flow works WITHOUT SSL. Coaches and students just see
HTTP until you provision certs. For a production deploy you want
HTTPS end-to-end. Pick one of:

### Option A — Caddy reverse proxy (recommended)

Best for: small-to-medium platforms (<200 coaches), zero ongoing
cost, auto-renews everything.

```caddyfile
# /etc/caddy/Caddyfile
*.mbsguru.com {
    reverse_proxy localhost:8080
    tls {
        on_demand
    }
}

# Custom-domain catch-all — Caddy will auto-provision a cert
# the first time a coach's domain hits the server (after their
# DNS A record is live).
:443 {
    tls {
        on_demand
    }
    reverse_proxy localhost:8080
}
```

Caddy uses Let's Encrypt under the hood. First hit on a new domain
takes 2-5 seconds while the cert is issued; subsequent requests
are fast. Caddy auto-renews 30 days before expiry. **Zero ongoing
maintenance** once configured.

**Caution**: enable Caddy's `on_demand` carefully — without rate
limits, an attacker can request certs for arbitrary domains and
hit Let's Encrypt's rate limits. The platform ships a built-in
`ask` endpoint that confirms the domain is in `coach_domains` with
`verified_at` NOT NULL:

```caddyfile
tls {
    on_demand
}
on_demand_tls {
    ask https://mbsguru.com/internal/ssl-allowed
    interval 2m
    burst 5
}
```

The endpoint lives at `GET /internal/ssl-allowed?domain=<host>` and
returns:
- `200` for verified hostnames (Caddy issues the cert)
- `403` for unverified or unknown hostnames (Caddy skips)
- `400` for malformed / missing `domain` query parameter

Route-level throttle is 60 req/min/IP to deflect mass-probing.
Implementation: `app/Http/Controllers/Internal/SslAllowlistController.php`,
test coverage: `tests/Feature/Domain/CaddySslAllowlistTest.php`.

### Option B — Cloudflare for SaaS

Best for: larger platforms (200+ coaches), already-on-Cloudflare
setups, willingness to pay $0.10/active-domain/month.

Cloudflare for SaaS handles the SSL termination at their edge. You
add domains via their API; they provision the cert and proxy to
your origin. Their docs cover the integration.

Pros: enterprise-grade DDoS protection, global edge cache, fast.
Cons: monthly cost scales with active domains.

### Option C — Manual cert per custom domain

Best for: tiny deploys (1-10 coaches), regulated environments where
auto-cert tooling is forbidden.

When a coach verifies a domain, you get a notification (set up via
the existing `Log::warning` channel — `coach-domain-verify-miss`
isn't logged on SUCCESS, so wire up a separate listener if you
want this). You manually:
1. SSH to the server
2. `certbot --apache -d acme.com -d www.acme.com`
3. Restart Apache

Painful but zero infrastructure surface beyond the cert tool.

For the AUTO-subdomain wildcard, a single
`certbot --dns-cloudflare -d "*.mbsguru.com"` covers all coaches.

---

## Migration order

```bash
# 1. Pull the latest code
git pull

# 2. Configure .env (see above)
$EDITOR .env

# 3. Run migrations — order matters because P6 backfill reads
# data created by P1/P2 backfills
php artisan migrate --force

# 4. Verify the auto-subdomain backfill assigned coaches
php artisan tinker --execute="
\$total = App\Models\User::where('role','instructor')->count();
\$assigned = App\Models\User::where('role','instructor')->whereExists(function(\$q) {
    \$q->select(DB::raw(1))->from('coach_domains')->whereColumn('coach_id','users.id');
})->count();
echo 'coaches: ' . \$total . ', assigned: ' . \$assigned;
"
# Expect: assigned == total (or close to it; coaches with all-NULL
# names get a 'coach-<id>' slug)

# 5. Verify route hygiene (P2 from module-routes commit ebd4e90)
php artisan modules:verify-routes

# 6. Clear caches so the new BrandResolver kicks in
php artisan view:clear
php artisan config:cache
```

---

## Coach onboarding (what they see, end to end)

1. **Coach signs up** at `/register`
   The User::created event fires → SubdomainAssigner generates
   `<their-slug>.mbsguru.com`, marks it verified (you own the
   wildcard cert), creates the brand row.
   **(2026-06)** The coach can later **rename** this subdomain from
   `/instructor/brand-settings` → "Your instant branded link" → type a
   new label → Claim/Rename. It's live immediately (no DNS), since the
   wildcard `*.mbsguru.com` cert + A record already cover any label.
   Endpoint: `POST /instructor/domains/subdomain`
   (`CoachDomainController::setSubdomain`). Reserved labels (`www`,
   `admin`, `api`, …) and labels already taken by another coach are
   rejected.

2. **Coach logs in** to `/instructor/dashboard`
   Sees platform branding (their brand profile is all NULL — they
   inherit platform defaults transparently via BrandResolver).

3. **Coach opens `/instructor/brand-settings`**
   - Uploads logo + favicon
   - Sets brand name + 2 colors
   - Sets support email + footer text
   - (Optional Tier 1) Sets From address for outbound mail
   - (Optional Tier 2) Sets full SMTP credentials + clicks Test SMTP
   - Saves — brand cache invalidates, next request shows new brand

4. **Coach shares `acme-coaching.mbsguru.com` with their students**
   Students hit the subdomain → `ResolveCoachByDomain` middleware
   stamps `resolved_coach_id` on the request → `BrandResolver::current()`
   returns Acme's brand → every view renders with Acme's logo /
   colors / footer. Emails sent in this context use Acme's From.

5. **(Optional) Coach adds custom domain `acme.com`**
   Pastes hostname → the UI shows **one** record to add (A →
   `COACH_PLATFORM_IP`, or CNAME → `COACH_DOMAIN`) → coach adds it at
   their DNS provider → clicks Verify → goes green. SSL provisioned per
   Section above. Now `acme.com` ALSO routes to Acme's branded
   experience. (TXT verification remains available as a fallback.)

6. **Coach can revoke / replace** any of these any time. Subdomains
   are platform-managed (can't be deleted by coach); custom
   domains are coach-owned and deletable.

---

## Troubleshooting — greppable log lines

The white-label code uses structured log keys so ops can grep for
specific failure modes:

| Log key | Channel | What it means | Action |
|---|---|---|---|
| `module-route-missing` | warning | A module's route file is absent — the route is registered but the file isn't on disk | Re-deploy, check git checkout state |
| `coach-smtp-failed` | warning | A coach's SMTP credentials failed mid-send — we auto-fell-back to platform mailer | Tell the coach to re-verify their SMTP (P3 UI) |
| `coach-mail-failed` | error | Neither coach SMTP nor platform fallback worked | Check platform MAIL_* env, mail server reachability |
| `coach-domain-verify-miss` | info | A coach clicked Verify but the TXT record wasn't found | Usually DNS propagation lag — re-try in 5 min |
| `coach-subdomain-auto-assign-failed` | warning | The User::created event couldn't assign a subdomain to a new coach | Coach can add one manually via brand-settings; check log for FK / DB error |
| `csl-backfill-added-by skip` | warning | An existing student.added_by points at a deleted user during multi-coach backfill | Inspect the orphaned row, decide whether to clean |
| `subdomain-backfill-row-failed` | warning | One row in the auto-subdomain backfill choked | Usually a clash that the assigner couldn't resolve; re-run manually |

```bash
# One-liner to see all white-label-related lines in the last day
grep -E "module-route|coach-smtp|coach-mail|coach-domain|coach-subdomain|csl-" \
  storage/logs/laravel-$(date +%Y-%m-%d).log
```

---

## Verification — is Path B actually working?

After deploy + setup, run:

```bash
# 1. Module routes are all healthy (no missing files)
php artisan modules:verify-routes

# 2. Every coach has a subdomain (sanity check)
php artisan tinker --execute="
\$missing = App\Models\User::where('role','instructor')
    ->whereDoesntHave('domains_via_pivot', fn(\$q) => \$q->where('kind','subdomain'))
    ->count();
echo \"coaches without subdomain: \$missing\";
"

# 3. Pick one coach + simulate a student visit to their subdomain
php artisan tinker --execute="
\$coach = App\Models\User::where('role','instructor')->first();
\$dom = App\Models\CoachDomain::where('coach_id',\$coach->id)->where('kind','subdomain')->first();
echo \"coach: {\$coach->name}\";
echo PHP_EOL . \"subdomain: {\$dom?->hostname}\";
echo PHP_EOL . 'test: curl -I https://' . \$dom?->hostname;
"
```

If a real student visit to `acme-coaching.mbsguru.com` returns 200
with Acme's logo in the HTML title, Path B is live for that coach.

---

## What's NOT supported (deliberate scope cuts)

- **Per-coach payment gateway accounts** — platform takes all
  payments centrally, credits coach wallets, coach withdraws.
  Per-coach Razorpay/Stripe was explicitly de-scoped during design.
- **Per-coach payment receiver address on invoices** — invoice From
  shows brand From, but the payment-processor receipt always
  reflects the platform's merchant account.
- **In-body link rewriting in emails** — emails sent from a coach
  context have correct From / Reply-To, but absolute URLs inside
  the body still point at `APP_URL`. If a student gets a "click here
  to log in" link, it goes to the platform URL, not the coach's
  subdomain. Follow-up P4b candidate if customers report it.
- **Custom CSS / theme override per coach** — the "medium" brand
  depth was chosen (logo + colors + contact), not the "heavy" tier
  with arbitrary CSS. Heavy CSS would double the testing surface.

---

## Custom-domain governance layer (2026-06-09)

The custom-domain feature now has a **status state machine + superadmin
admin panel + audit trail**, layered on the existing mapping/verify core.

**Status state machine** (`coach_domains.status`):
`pending → verified → active`, plus `failed` and `suspended`.
- A host resolves (serves) only when `verified_at` is set **and** status is
  not `suspended`. Suspending a domain stops it serving immediately.
- New columns: `status`, `last_verified_at`, `verify_attempts`, `last_error`,
  `ssl_status`, `ssl_checked_at`, `approved_at`, `approved_by`, `rejected_reason`.
- Migration `2026_06_09_120000_*` is additive + backfills `verified→active`.

**Superadmin settings** (key/value rows in `settings`, editable at
`/admin/custom-domains/settings`):

| key | meaning | default |
|---|---|---|
| `custom_domain_enabled` | feature on/off (coach add is gated) | `0` |
| `custom_domain_server_ip` | A-record target shown + verified against | `COACH_PLATFORM_IP` env |
| `custom_domain_requires_approval` | strict mode — verified domains wait for admin | `0` |
| `custom_domain_max_per_coach` | quota | `1` |

> **Action required after deploy:** set `custom_domain_server_ip` (or the
> `COACH_PLATFORM_IP` env) AND toggle `custom_domain_enabled` ON, or coaches
> cannot add custom domains. The IP shown to coaches is now read from this
> setting, not hardcoded.

**Admin panel** (`/admin/custom-domains`, permissions
`custom_domain.view/manage/settings`, granted to Super Admin by migration
`2026_06_09_130000_*`): list with status/SSL/last-verified, per-domain
approve / reject / suspend / resume / remove / re-check, a detail page with
the full audit timeline, and the settings page above.

**Approval modes:**
- *Auto* (default): coach adds domain → DNS verify → `active` immediately.
  Admin retains suspend/remove.
- *Strict* (`custom_domain_requires_approval=1`): DNS verify → `verified`
  (not serving) → admin **Approve** → `active`.

**Auto-recheck job** — `domains:recheck-pending` (scheduled every 10 min)
re-verifies `pending`/`failed` custom domains and activates those now
pointing at us, so a coach doesn't have to re-click Verify after DNS
propagates. Capped by `verify_attempts` (default 30). Admin "Re-check" uses
the same command with `--id --force`.

**Audit trail** — `coach_domain_events` (append-only): added, verify_attempt,
verified, failed, approved, rejected, suspended, resumed, ssl_issued,
removed, renamed — with actor + IP + meta. Survives domain deletion.

**SSL status** — `SslAllowlistController` (the Caddy `ask` endpoint) now
stamps `ssl_status=issued` + `ssl_checked_at` when it allows a host, and
denies suspended/unverified hosts (via the same resolution gate).

## When to revisit

This guide is current as of commits P1-P6 (above). If you touch:
- `app/Services/BrandResolver.php` — re-verify the resolution order
- `app/Http/Middleware/ResolveCoachByDomain.php` — re-test the
  middleware ordering in `app/Http/Kernel.php`
- `coach_domains` schema — update the troubleshooting queries
- `CoachDomainController::setSubdomain` / `::verify` — the self-service
  rename + one-record verify (2026-06); covered by
  `tests/Feature/Domain/CoachDomainOnboardingTest.php`

Last reviewed: 2026-06-09 (added the custom-domain governance layer:
status state machine, superadmin admin panel + settings, approval mode,
auto-recheck job, SSL-status + audit trail). Covered by
`tests/Feature/Domain/CustomDomain*Test.php` + `AdminCustomDomainTest.php`.
