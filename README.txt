MBSGuru v215 — EmailTemplateSeeder truncate() guard (audit follow-up)
====================================================================
FIX: EmailTemplateSeeder::run() called EmailTemplate::truncate(), wiping the
WHOLE email_templates table — including the migration-seeded notif_* templates
(course sale, live class, trial, payment-failed, fee, membership, …) — if the
seeder was ever re-run on a live DB. It now UPSERTS each legacy template by its
(unique) name, leaving every other row intact.

FILES:
  Modules/GlobalSetting/database/seeders/EmailTemplateSeeder.php  (truncate → upsert-by-name)
  tests/Feature/Domain/EmailTemplateSeederGuardTest.php           (new — 2 tests)

TESTS (2/2 green):
  • seeder preserves a notif_* row + upserts legacy rows (no duplicates)
  • seeder is idempotent (running twice never duplicates)

DEPLOY: unzip over app root. No migration, no cache clear needed (seeder-only).
Also folded into the combined MBSGuru_v214_email_notif_ALL_PHASES_1to7.zip.
