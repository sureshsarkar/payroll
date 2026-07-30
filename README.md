# MBS Guru LMS

[![CI](https://github.com/mrsantoshdhs-bit/mbs/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/mrsantoshdhs-bit/mbs/actions/workflows/ci.yml)

Laravel 10 LMS / e-learning marketplace. Production at <https://mbsguru.com>.

## Audit baseline (2026-05-06)

This codebase carries the post-audit baseline tagged `v1.0-audit-baseline`.
Cumulative work across 11 audit sessions is described in
[docs/](docs/) — start with the production deployment runbook
([docs/PRODUCTION_DEPLOY_2026-05-05.md](docs/PRODUCTION_DEPLOY_2026-05-05.md)).

## Installation

```bash
git clone https://github.com/mrsantoshdhs-bit/mbs.git
cd mbs
composer install
cp .env.example .env
php artisan key:generate
# Edit .env to match your DB + mail + gateway settings
php artisan migrate
php artisan audit:smoke
```

## Verification

The audit ships two ways to verify the baseline at any time:

```bash
# Live runtime check — 45 checks across DB, encryption, drivers, scheduler
php artisan audit:smoke

# Programmatic test suite — 33 PHPUnit tests, ~10 s
vendor/bin/phpunit tests/Feature/Audit
```

Both also run automatically on every push via the
[CI workflow](.github/workflows/ci.yml).

## Manual test playbook

[docs/MANUAL_TEST_PLAYBOOK.md](docs/MANUAL_TEST_PLAYBOOK.md) — 41 numbered
manual tests for the things that need a real browser (UI flows, role-based
checks, mobile responsiveness, sandbox webhook tests).

## Per-coach white-label (Path B)

One install, many coaches, each coach gets their own branded experience
(logo / colors / domain / email) that students see end-to-end with the
word "MBSGuru" nowhere visible. Code ships in 6 phases (P1-P6, commits
`3036e27` through `e92553d`); the remaining work is operator-side
infrastructure (DNS wildcard + SSL automation).

- Coach self-serve UI at `/instructor/brand-settings` — logo upload,
  brand name, colors, support contact, custom-domain onboarding with
  DNS TXT verification, optional per-coach SMTP override
- Per-request tenant resolution via `ResolveCoachByDomain` middleware
- Auto-subdomain on coach signup (`<slug>.<COACH_DOMAIN>`)
- Multi-coach support for students (one student can belong to multiple
  coaches' rosters — `coach_student_links` pivot, `f6cc816`)

Operator setup, troubleshooting log keys, SSL options, and the deploy
checklist: **[docs/PATH_B_WHITELABEL_OPERATOR_GUIDE.md](docs/PATH_B_WHITELABEL_OPERATOR_GUIDE.md)**.

## Documentation

- [PATH_B_WHITELABEL_OPERATOR_GUIDE.md](docs/PATH_B_WHITELABEL_OPERATOR_GUIDE.md) — per-coach white-label deploy guide (DNS, SSL, onboarding)
- [PRODUCTION_DEPLOY_2026-05-05.md](docs/PRODUCTION_DEPLOY_2026-05-05.md) — production deploy runbook
- [PAYMENT_WEBHOOKS_SETUP.md](docs/PAYMENT_WEBHOOKS_SETUP.md) — Stripe/Razorpay/PayPal/MP/bKash webhook setup
- [CRON_SETUP.md](docs/CRON_SETUP.md) — scheduled tasks setup (queues, prenotifications, log rotation)
- [MAIL_PASSWORD_ROTATION.md](docs/MAIL_PASSWORD_ROTATION.md) — Gmail app-password rotation
- [SESSION_DRIVER_SWITCH.md](docs/SESSION_DRIVER_SWITCH.md) — file → database session driver
- [GLOBALSETTING_SECRETS_ENCRYPTION.md](docs/GLOBALSETTING_SECRETS_ENCRYPTION.md) — at-rest encryption design
- [GIT_RECOVERY.md](docs/GIT_RECOVERY.md) — restore from bundle / push to remote
- [STRIPE_WEBHOOK_SETUP.md](docs/STRIPE_WEBHOOK_SETUP.md) — Stripe-specific setup
- [MANUAL_TEST_PLAYBOOK.md](docs/MANUAL_TEST_PLAYBOOK.md) — 41 browser-based manual tests

## Tech stack

- Laravel 10.46 · PHP 8.2 · MariaDB 10.4+
- nWidart Laravel-Modules 10 (33 enabled modules)
- Tailwind + Vite (frontend bundling — currently Vite-configured but admin layout still on raw `public/backend/*` assets)
- spatie/laravel-backup · laravel/telescope (dev only)

## License

Private.
