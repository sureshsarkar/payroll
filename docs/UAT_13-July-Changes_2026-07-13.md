# UAT — 13-July-Changes (2026-07-13)

White-label, multi-tenant: every check must hold for **any** coach/tenant, not a
specific one. Perform each as the stated role on that role's own tenant.

Automated coverage backing these steps:
- PHPUnit — `tests/Feature/Domain/July13ChangesTest.php` (titles + order RBAC slugs), 7 tests.
- Playwright — `tests/e2e/instructor/31-july13-changes-2026-07.spec.ts`,
  `tests/e2e/student/01-july13-changes-2026-07.spec.ts`,
  `tests/e2e/public/16-july13-staff-order-create-2026-07.spec.ts`.
- Before the Playwright RBAC spec: `php tests/e2e/seed-fixtures.php`.

---

## #1 — Dynamic browser-tab titles

| # | Role | Steps | Expected | P/F |
|---|------|-------|----------|-----|
|1.1| Coach | Open each of: Certificate Builder, Fees, Offline Payments, Plan & Billing, Zoom Settings, YouTube Settings, Staff Roles, Staff Permissions, Brand Settings, Website Builder, Subscription History, Tax Settings, Membership | Browser tab reads the module name + brand (e.g. "Offline Payments \| <Brand>"), **never** "Coach Dashboard" | |
|1.2| Coach | Open the dashboard landing (`/instructor/dashboard`) | Tab reads "Coach Dashboard \| <Brand>" | |
|1.3| Student | Open Attendance, then Fees | Tab reads "Attendance …" / "Fees …", never "Student Dashboard" | |
|1.4| Student | Open the dashboard landing | Tab reads "Student Dashboard \| <Brand>" | |
|1.5| Coach | Hard-refresh + direct-URL each page above | Title is correct on refresh and direct load (server-rendered, no JS needed) | |

## #2 — Enterprise plan shows "Talk to Us"

| # | Role | Steps | Expected | P/F |
|---|------|-------|----------|-----|
|2.1| Coach | Open Membership; find the Enterprise plan card | Price area shows **"Talk to Us"** — no number, no "/month", no "Save x%" | |
|2.2| Coach | Same card | "Contact us" CTA still present and working | |
|2.3| Coach | Non-Enterprise cards (Starter/Medium) | Still show their real price + period unchanged | |
|2.4| Coach | Toggle Monthly/Annual | Enterprise stays "Talk to Us" in both; other plans switch figures | |

## #3 — Staff Order Create/Edit permission

| # | Role | Steps | Expected | P/F |
|---|------|-------|----------|-----|
|3.1| Coach | Open Orders (`/instructor/coach-orders`) | "Add manual order" button visible | |
|3.2| Staff **with** "Create Order" | Open Orders | "Add manual order" button **visible**; clicking opens the create form | |
|3.3| Staff **with** "Create Order" | Submit a manual order | Order is created (no access-denied) | |
|3.4| Staff **without** "Create Order" (view only) | Open Orders | Button **hidden**; typing `/instructor/coach-orders/create` shows access-denied, not the form | |
|3.5| Staff **with** "Update Order" | Open an order detail, change status | Update succeeds | |
|3.6| Staff **without** "Update Order" | Open an order detail | No edit/actions; direct POST to update is blocked | |

## #4 — Referral copy-link → /register

| # | Role | Steps | Expected | P/F |
|---|------|-------|----------|-----|
|4.1| Student | Dashboard → "Refer & earn" widget → Copy link | Copied URL is `<site>/register?ref=<code>` (not `<site>/?ref=<code>`) | |
|4.2| Anyone | Paste that link in a fresh browser | Lands on the **registration** form with the referral applied | |
|4.3| Coach | Full referral panel (`/referral`) copy link | Also `/register?ref=` (already correct — regression check) | |

## #5 — Landing-Page Enquiry email uses coach SMTP

| # | Role | Steps | Expected | P/F |
|---|------|-------|----------|-----|
|5.1| Coach **with** own SMTP configured | Enquiry details → send email | Email arrives **from the coach's own address** (their SMTP) | |
|5.2| Coach **without** SMTP | Enquiry details → send email | Email still sends, from the platform (MBSGuru) default — no hardcoded personal address | |
|5.3| — | Inspect any sent enquiry email headers | Never `mrsantoshdhs@gmail.com` | |

## #6 — Sidebar renames

| # | Role | Steps | Expected | P/F |
|---|------|-------|----------|-----|
|6.1| Coach | Open the sidebar | "Certificate" (not "My Certificate") | |
|6.2| Coach | Open the sidebar | "Plan & Billing" (not "My Plan & Billing") | |
|6.3| Coach | Click each | Still navigate to the certificate builder / billing pages | |

---

### Tenant-isolation spot-checks (must pass for all items)
- Coach A never sees Coach B's plans, orders, students, receipts, or branding.
- Enterprise "Talk to Us" and the "Contact us" link are derived from tier +
  the coach/admin-managed contact number — no hardcoded coach/number.
- Referral link uses `config('app.url')` (per-environment), no hardcoded host.
