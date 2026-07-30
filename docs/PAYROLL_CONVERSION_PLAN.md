# MBS Guru LMS → Payroll & Attendance Management System — Conversion Plan

Source brief: *"MASTER PROMPT — Convert MBS GURU LMS into a Payroll & Attendance
Management System"*. This document translates that brief into a plan grounded in
the **actual** codebase (Laravel 10.50, 32 nWidart modules, 187 DB tables).

Strategy chosen: **additive + incremental** — build the new system alongside the
old, remap roles/labels, and only remove LMS code/tables **after** payroll is
proven. `git` baseline `master` (commit `b14bf43`) is the fallback; work happens
on branch `payroll-conversion`.

---

## 1. Reality-check vs. the brief's assumptions

| Brief assumes | Codebase actually is | Consequence |
|---|---|---|
| Separate `students` / `coaches` tables | One `users` table + string `role` column; separate `admins` table | Role change = update `role` values + labels, **not** table renames |
| Role "Coach" | Code uses **`instructor`** (middleware `studentrole`, `instructorrole`) | "Coach → HR" = **instructor → HR** |
| Simple app | 32 modules, 187 tables, 154 controllers | Larger surface; touch only role + LMS + new modules |
| — | A separate **white-label multi-tenant "coach" system** (`coach_*`, 30+ tables) — branding/domains, *unrelated* to the HR role | **Left untouched** this phase to avoid scope blow-up |

### Role mapping (as implemented)

| Brief | Actual identity in code | Action |
|---|---|---|
| Super Admin | `admins` table / admin guard (`routes/admin.php`) | Keep auth; change features + labels |
| Coach → **HR** | `users.role = 'instructor'` | Keep auth; relabel to "HR"; overhaul features |
| Student → **Employee** | `users.role = 'student'` | Keep auth; relabel to "Employee"; overhaul features |

> We keep the underlying role **string values** (`instructor`/`student`) stable
> to avoid breaking the ~200 references across routes/middleware, and introduce a
> **display-label + vocabulary layer** (HR / Employee) on top. A later phase can
> do a hard rename once everything is green.

---

## 2. Module strategy

New nWidart modules (additive):

| New module | Purpose | Reuses |
|---|---|---|
| `Attendance` | Daily marking, check-in/out, regularization, calendar | `CourseProgress`/attendance-tracking patterns |
| `Leave` | Leave types, apply, approve/reject, balances | Approval-workflow patterns |
| `Payroll` | Salary structure, run, payslip, PF/ESIC/PT/TDS, LOP | Certificate PDF engine → payslip PDF |
| `HrEmployee` | Department/team management + employee onboarding | `CourseBatch` (batches → departments), `InstructorRequest` (onboarding) |
| `SheetExport` (shared) | Universal View + Excel(.xlsx) + PDF export with role scoping | dompdf (already in `composer.json`) |

LMS modules to **deprecate → later remove**: `Course`, `CertificateBuilder`
(repurpose), `Badges`, plus course/lesson/quiz/enrollment logic in `app/`.

---

## 3. Database changes (all additive first)

New tables (new migrations under the new modules — **no drops yet**):

- `departments` (id, name, code, head_user_id, ...)
- `employee_profiles` (user_id FK, employee_code, department_id, doj, designation, ...)
- `attendances` (user_id, date, status[Present/Absent/HalfDay/Leave/Holiday/WFH], check_in, check_out, marked_by, source)
- `attendance_regularizations` (user_id, date, reason, status, approved_by)
- `leave_types` (name, is_paid, annual_quota)
- `leaves` (user_id, leave_type_id, from, to, days, reason, status, approved_by)
- `leave_balances` (user_id, leave_type_id, year, allotted, used)
- `salary_structures` (user_id, effective_from, ...), `salary_components` (structure_id, type[earning/deduction], name, calc_type, value)
- `salary_revisions` (user_id, old_ctc, new_ctc, effective_from)
- `payroll_runs` (month, year, status[draft/hr_submitted/admin_approved], prepared_by, approved_by)
- `payroll_items` (run_id, user_id, gross, deductions_json, lop_days, net, payslip_path) ← the "payslip"
- `loans_advances`, `bonuses` (one-time entries)

**Deferred drops** (Phase 7): `courses`, `course_*`, `lessons`, `enrollments`,
`quiz*`. Kept until payroll is signed off, then dropped in a reversible migration.

`users` table: keep `role`; feature-gate by role value. Optionally add
`employee_code`/`department_id` via `employee_profiles` instead of altering `users`.

---

## 4. Phased roadmap

- **Phase 0 — Safety net** ✅ git init, baseline `master`, branch `payroll-conversion`, media excluded.
- **Phase 1 — Foundation** *(current)*: scaffold new modules, additive migrations, role→label vocabulary layer.
- **Phase 2 — Attendance**: marking (self + HR bulk), check-in/out, calendar, regularization, auto totals (Present/Absent/Paid-leave/LOP).
- **Phase 3 — Leave**: types, apply, balances, HR approve/reject.
- **Phase 4 — Payroll engine**: salary structure, pull attendance → LOP, statutory deductions (configurable), draft→approve workflow, payslip PDF (reuse certificate engine), notify.
- **Phase 5 — Sheet & Export (shared)**: on-screen filterable/sortable/paginated tables + `.xlsx` + PDF, role-scoped (Employee=self, HR=team, Admin=all).
- **Phase 6 — UX remap**: sidebar/menu labels, dashboard widgets (Attendance Summary, Total Employees, pending approvals), route guards, notification templates.
- **Phase 7 — LMS removal (destructive, last)**: remove course/lesson/quiz/enrollment code + drop tables, purge LMS UI references.
- **Phase 8 — QA**: brief's testing checklist — role isolation, export correctness, payroll accuracy (LOP/PF/tax), route protection, no stray LMS references. E2E: Employee marks → HR approves → Admin approves payroll → payslip → Employee downloads.

---

## 5. Access-control matrix (target)

| Capability | Employee | HR | Super Admin |
|---|---|---|---|
| Mark own attendance | ✅ | ✅ (+ team bulk) | ✅ (all) |
| Approve leave / regularization | — | ✅ (own team) | ✅ (all) |
| Prepare payroll draft | — | ✅ | ✅ |
| Approve payroll | — | — | ✅ |
| View/download sheets | self only | team/department | company-wide |
| Salary structure / statutory config | — | — | ✅ |

---

## 6. Open items to confirm as we go

1. Hard role rename (`instructor→hr`, `student→employee` in DB/routes) — do it in
   Phase 6/7, or keep the label layer permanently?
2. White-label `coach_*` system — keep dormant, or remove in a later dedicated phase?
3. New product branding/name (currently "MBSGuru") for logo/emails.
4. Statutory defaults (PF 12%, ESIC, PT slabs, TDS) — jurisdiction/rates to seed.
