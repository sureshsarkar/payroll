# MASTER PROMPT — Convert MBS Guru's HR/Attendance/Leave/Payroll System into a Multi-Tenant (Multi-Company) SaaS

Paste this whole document into a coding session (Claude Code / this repo) as the brief for
implementation. It is grounded in the **actual** codebase as of 2026-08-18, not assumptions —
verified against `composer.json`, `modules_statuses.json`, and the `HrEmployee` / `Attendance` /
`Leave` / `Payroll` modules directly. Follow the same **additive-first, non-destructive** strategy
already used in `docs/PAYROLL_CONVERSION_PLAN.md`: nothing existing breaks, new capability is
layered on top, and destructive cleanup (if any) happens last, only after everything is verified.

---

## 1. Where the codebase actually is today (ground truth)

- Laravel 10.46, PHP 8.1+, nWidart Laravel-Modules 10, MariaDB. Modular structure under `Modules/`.
- Roles today: `admins` table/guard = **Super Admin** (platform owner). `users.role` = `instructor`
  (relabelled **HR**) or `student` (relabelled **Employee**). This is flat and **single-tenant**:
  one HR person manages one team of employees via `employee_profiles.reporting_hr_id`. There is
  **no concept of "company" anywhere in the HR/Attendance/Leave/Payroll modules today.**
- `spatie/laravel-permission` `^6.1` is already installed — its **teams** feature is the natural
  fit for multi-tenancy and should be used rather than hand-rolling a parallel permission system.
- A separate, **dormant, unrelated** white-label system already exists for LMS "coaches"
  (`docs/PATH_B_WHITELABEL_OPERATOR_GUIDE.md` — per-coach logo/colors/custom-domain via
  `ResolveCoachByDomain` middleware). **Do not reuse or wake this up.** Per product decision below,
  this project needs *data-level* multi-tenancy only, not per-tenant branding/domains — that Path B
  machinery is a different feature for a different actor (LMS coaches) and out of scope here.
- Existing HR-domain tables (all currently company-less): `departments`, `employee_profiles`,
  `attendances`, `attendance_regularizations`, `leave_types`, `leaves`, `leave_balances`,
  `salary_structures`, `salary_components`, `salary_revisions`, `payroll_runs`, `payroll_items`,
  `loans_advances`, `bonuses`.
- A `Subscription` module already exists in the codebase (currently used for the LMS side).
  **Not wired into this phase** (see §7 Out of scope) — leave a clean extension point for later.

## 2. Product decisions (already made — do not re-litigate these)

1. **Multi-tenancy shape**: data-level only. One shared UI/branding across the whole platform.
   No per-company logo, color theme, subdomain, or custom domain in this phase.
2. **Who creates companies**: self-serve. Any user with the HR role can sign up and then register
   one or more companies themselves — no Super-Admin approval gate in the create flow.
3. **Billing**: none in this phase. Do not wire the `Subscription` module to companies yet. Just
   make sure the schema doesn't fight a future `company_id` → subscription/plan relationship.

## 3. Target mental model

- **Super Admin** (existing `admins` guard) — sees and can act across **all** companies, platform-wide.
- **HR** (`users.role = 'instructor'`) — an account that can own **multiple Companies**. After
  login, an HR with more than one company picks/switches an "active company"; every HR screen
  (employees, attendance, leave, payroll) operates against that active company only.
- **Employee** (`users.role = 'student'`) — belongs to exactly **one** Company (via
  `employee_profiles.company_id`). Never sees a company switcher; everything is implicitly scoped
  to their own company.
- **Company** — the new tenant boundary. Every HR-domain record (department, employee, attendance,
  leave, payroll run, payslip, salary structure, loan/bonus, ...) belongs to exactly one company,
  and a query must never be able to leak across companies — this is the #1 thing to get right and
  the #1 thing to test.

## 4. Schema changes

### 4.1 New table: `companies`
```
id
name                 string
slug                 string, unique   -- for URLs/reference, not a subdomain
code                 string, nullable, unique   -- internal reference code
owner_user_id         FK -> users.id (the HR who registered it)
industry              string, nullable
timezone              string, default 'Asia/Kolkata'
status                string, default 'active'   -- active|suspended
address, city, state, country, postal_code   -- nullable, basic company profile fields
created_at, updated_at
```

### 4.2 New pivot table: `company_user`
Even though phase-1 scope is "one owner per company," build the pivot now so a company can later
have more than one HR staff member without a breaking migration:
```
id
company_id   FK -> companies.id
user_id      FK -> users.id
role         string, default 'owner'   -- owner|hr_staff (only 'owner' populated in phase 1)
status       string, default 'active'
created_at, updated_at
unique(company_id, user_id)
```
Seed this pivot automatically whenever a company is created (owner row), so all "does this HR
have access to this company" checks go through one place (the pivot), not `companies.owner_user_id`
directly — that keeps the door open for adding staff later.

### 4.3 Add `company_id` to existing tables (nullable at first, backfilled, then made required)
Add an indexed `unsignedBigInteger('company_id')` column (app-level FK, following this codebase's
existing convention of indexed-but-not-DB-constrained FKs for legacy-collation safety — see the
`attendances` migration comment) to:
- `departments`
- `employee_profiles`
- `attendances`
- `attendance_regularizations`
- `leave_types`
- `leaves`
- `leave_balances`
- `salary_structures`
- `salary_components`
- `salary_revisions`
- `payroll_runs`
- `payroll_items`
- `loans_advances`
- `bonuses`

`employee_profiles.company_id` is the source of truth for "which company does this Employee belong
to." Do **not** add `company_id` to `users` directly — keep following this codebase's established
pattern of extending via `employee_profiles` rather than altering `users` (see that migration's own
comment: "Sits alongside the existing `users` row rather than altering it").

### 4.4 Backfill strategy (must not break anything live)
1. Add all `company_id` columns as **nullable** first.
2. Migration step: for every existing HR (`instructor`) user with any employees/data, create one
   `companies` row (name = e.g. "{HR name}'s Company" or a sane default), insert the
   `company_user` owner row, then backfill `company_id` on every row currently reachable via that
   HR's `reporting_hr_id` chain (employee_profiles, and cascade to attendances/leaves/payroll rows
   for those employees).
3. Verify zero orphan rows (`company_id IS NULL`) before flipping columns to `NOT NULL` in a
   follow-up migration.
4. Only after backfill is verified in staging, add the `NOT NULL` constraint.

## 5. Code changes

### 5.1 New module: `Company` (nWidart)
Mirrors the existing modular pattern (`Modules/HrEmployee`, `Modules/Payroll`, etc.):
- `app/Models/Company.php`, `app/Models/CompanyUser.php`
- `app/Http/Controllers/CompanyController.php` — list "my companies", create/register, edit,
  switch active company
- `app/Http/Middleware/EnsureCompanyContext.php` — resolves the "active company" for the current
  request (from session for HR, from `employeeProfile.company_id` for Employee) and binds it into
  the container (e.g. `app()->instance('currentCompany', $company)`); redirects HR with no
  companies yet to "Register your first company"; redirects HR with companies but none selected to
  the company picker.
- `app/Policies/CompanyPolicy.php` — `view`/`update` only if the user has an `owner`/`hr_staff` row
  in `company_user` for that company.
- Routes: `GET /hr/companies` (list/switch), `GET|POST /hr/companies/create`, `POST
  /hr/companies/{company}/switch`.
- Views: companies list with a "Register New Company" CTA, a simple create form (name, industry,
  address, timezone), and a company-switcher dropdown to drop into the shared HR layout/nav
  partial used by `Attendance`, `Leave`, `Payroll`, `HrEmployee` (`resources/views/layouts/master.blade.php`
  in each of those modules — check whether they already share one layout partial; if not,
  introduce one shared partial to avoid duplicating the switcher).

### 5.2 Tenant scoping (apply to every model touched in §4.3)
- Add a `BelongsToCompany` trait with a global scope that filters every query by
  `company_id = currentCompany()->id`, plus a `creating` model event that auto-stamps
  `company_id` on new records from the resolved current company. Apply the trait to:
  `Department`, `EmployeeProfile`, `Attendance`, `AttendanceRegularization`, `LeaveType`, `Leave`,
  `LeaveBalance`, `SalaryStructure`, `SalaryComponent`, `SalaryRevision`, `PayrollRun`,
  `PayrollItem`, `LoanAdvance`, `Bonus`.
- Super Admin requests must be able to bypass the scope deliberately (e.g.
  `Model::withoutGlobalScope(CompanyScope::class)`) for cross-company/platform views — make this
  explicit and reviewed, never accidental.
- Apply `EnsureCompanyContext` middleware to every HR-guard and Employee-guard route group in
  `Modules/HrEmployee`, `Modules/Attendance`, `Modules/Leave`, `Modules/Payroll` route files.

### 5.3 Authorization
- `EmployeeProfile::teamUserIds()` (in `Modules/HrEmployee/app/Models/EmployeeProfile.php`) today
  scopes by `reporting_hr_id` / legacy `coach_id`. Extend/replace so "team" also respects
  `company_id` — an HR must never pull data for an employee in a different company even if a
  `reporting_hr_id` value collided.
- Add a policy check (or middleware) on every controller action in the four HR modules: the
  resolved `currentCompany()` must belong to the acting HR (via `company_user`) or the acting user
  must be Super Admin.

### 5.4 Onboarding flow (self-serve, per decision #2 in §2)
1. HR signs up (existing signup flow, role = instructor) — unchanged.
2. First login with zero companies → forced to "Register your first company" (simple form).
3. From then on, an "My Companies" / switcher entry point lets them register additional companies
   and switch the active one at any time.
4. Registering a company should optionally seed sane defaults (e.g. a "General" department,
   default leave types) so the HR isn't staring at an empty company — reuse whatever seeding
   `LeaveDatabaseSeeder` already does per-install, scoped to the new company.

## 6. Access-control matrix (target)

| Capability | Employee | HR (active company only) | Super Admin |
|---|---|---|---|
| Register a new company | — | ✅ | ✅ (on behalf of, if ever needed) |
| Switch active company | — | ✅ (only own companies) | n/a (sees all, unscoped) |
| Add/manage employees | — | ✅ (active company) | ✅ (any company) |
| Mark own attendance | ✅ | ✅ | ✅ |
| Approve leave/regularization | — | ✅ (active company only) | ✅ (any company) |
| Prepare/approve payroll | — | ✅ (active company only) | ✅ (any company) |
| View another HR's company data | — | ❌ (must not be reachable, even by guessing an ID/URL) | ✅ |

## 7. Explicitly out of scope this phase (do not build)

- Per-company branding, logo, color theme, subdomain, or custom domain (that's the dormant Path B
  coach system's territory — leave it untouched and unreferenced).
- Wiring the `Subscription` module to companies (employee limits, paid plans, feature gating by
  plan). Just don't design the schema in a way that makes adding `companies.subscription_id` later
  painful.
- Hard renaming `instructor`→`hr`/`student`→`employee` in the DB — that's tracked as an existing
  open item in `docs/PAYROLL_CONVERSION_PLAN.md` §6 and is independent of this work.
- Multiple HR staff *actively* collaborating on one company (schema supports it via `company_user`,
  but building the invite/staff-management UI is a later phase).

## 8. Testing/QA checklist (tenant isolation is the critical risk here)

- **Isolation test (must-have, write first)**: HR-A with Company-1 cannot view/edit/export any
  Company-2 record by direct URL/ID manipulation (attendance row, payslip, employee profile,
  payroll run) even though both companies live in the same tables.
- Backfill migration test: run against a snapshot of current data, assert zero `company_id IS NULL`
  rows afterward and that every employee's historical attendance/leave/payroll rows landed under
  the correct backfilled company.
- Company switcher test: switching active company changes what every HR screen (dashboard,
  employees, attendance, leave approvals, payroll runs) shows, with no stale cross-company data
  after switch.
- Employee-side test: an Employee never sees a company switcher and can never be scoped to more
  than one company.
- Super Admin cross-company views still work (and are clearly marked as intentionally unscoped).
- Regression pass on existing single-company flows (today's only real usage pattern) to confirm
  nothing broke for the current HR/employees during/after backfill.

## 9. Suggested phase order

1. **Schema** — `companies`, `company_user`, nullable `company_id` columns everywhere in §4.3.
2. **Backfill** — create default companies for existing HRs, backfill, verify, then set `NOT NULL`.
3. **Company module** — model, policy, middleware, controller, routes, register/switch UI.
4. **Scoping** — `BelongsToCompany` trait + global scope wired into all 14 models; apply
   `EnsureCompanyContext` middleware to the four HR-domain modules' route groups.
5. **Authorization hardening** — policies/team-scoping rewrite in `EmployeeProfile::teamUserIds()`
   and equivalent checks across Attendance/Leave/Payroll controllers.
6. **Onboarding UX** — forced first-company registration, switcher in nav, empty-state seeding.
7. **QA** — run the full checklist in §8, with the isolation test as the release gate.

## 10. Open items to confirm before/while building

1. Can one Employee ever need to move from Company A to Company B (transfer), or is company
   membership permanent once set at onboarding? Affects whether `employee_profiles.company_id`
   needs a change-history table.
2. Should Super Admin's cross-company dashboards get company-wise breakdown widgets in this phase,
   or is "can technically bypass scope" enough for now and the UI comes later?
3. Confirm default company naming convention when auto-created during backfill (e.g. `"{HR name}'s
   Company"` vs asking each HR to name it on first login post-migration).
