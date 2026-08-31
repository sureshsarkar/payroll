<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| LMS removal phase 2 (2026-08-27) — this file used to register ~214 routes
| for the MBS Guru mobile app: auth + 2FA, the student dashboard (enrolled
| courses, lessons, quizzes, live classes, certificates), the coach dashboard
| (courses, batches, students, orders, payouts, analytics), cart, checkout and
| course purchase, memberships, referrals and the public course/blog
| catalogue. All of it read models that Phase 2 deletes.
|
| There is no mobile client for the HR/Payroll product, and no surviving view
| calls /api/*, so the file is intentionally left empty rather than deleted:
| RouteServiceProvider still groups it under the `api` middleware stack, and
| this is where an HR API would be registered when one is needed.
|
| The legacy routes/api_01_07.php snapshot went with it — it was never
| required from anywhere.
|
*/
