<?php

use App\Http\Controllers\API\AuthenticatedController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CoachDashboardController;
use App\Http\Controllers\API\StudentApiDashboardController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\FrontendController;
use Illuminate\Support\Facades\Route;

/* Auth bootstrap. Throttles must match web-side limits — without these
 * the API was open to credential-stuffing and registration spam, while
 * the web /login already had `throttle:user-login`.
 *  - login:           5/min per IP+email   (mirrors RouteServiceProvider 'user-login')
 *  - register:        3/min per IP         (registration spam)
 *  - forget-password: 3/min per IP+email   (enumeration / email-flood) */
Route::middleware(['guest:sanctum'])->group(function () {
    Route::post('register', [AuthenticatedController::class, 'register'])
        ->middleware('throttle:3,1')->name('api.register');
    Route::post('login', [AuthenticatedController::class, 'login'])
        ->middleware('throttle:5,1')->name('api.patient-login');
    Route::post('forget-password', [AuthenticatedController::class, 'forgetPassword'])
        ->middleware('throttle:3,1')->name('api.forget-password');
    Route::post('reset-password', [AuthenticatedController::class, 'resetPassword'])
        ->middleware('throttle:5,1')->name('api.reset-password');

    /* Phase 1B (2026-05-13) — 2FA challenge verification. The challenge
       token is minted by /api/login when the user has 2FA enabled and
       has a 15-minute TTL (see App\Http\Controllers\API\TwoFactorController).
       Throttle to match login itself. */
    Route::post('2fa/verify', [\App\Http\Controllers\API\TwoFactorController::class, 'verify'])
        ->middleware('throttle:5,1')->name('api.2fa.verify');
});


// student dashboad APIs start
// SECURITY NOTE (audit 2026-06-12) — intentionally gated by `auth:sanctum`
// only, NOT a student-role middleware. (a) Every handler scopes to
// auth()->id()/the caller's enrollments, so there is no cross-user leak even
// if a coach token calls these. (b) This group MIXES student-only endpoints
// (dashboard, courses, profile) with shared COMMERCE endpoints (cart, wishlist,
// add-to-cart) that — exactly like the web — must remain usable by a coach
// acting as a buyer. A blanket student-role gate would break that parity, so we
// keep the self-scoping defense and avoid a false role wall.
Route::middleware('auth:sanctum')->group(function () {
    // F23 (audit 2026-06-26) — issue a short-lived SIGNED download URL (no bearer
    // token in the URL). The app calls these with its normal header token, then
    // opens the returned signed URL in a webview. Replaces ?bearer_token= leaks.
    Route::get('invoice-download-link/{invoice_id}', [DashboardController::class, 'invoiceDownloadLink'])->where('invoice_id', '[a-zA-Z0-9-_]+');
    Route::get('certificate-download-link/{course_slug}', [DashboardController::class, 'certificateDownloadLink'])->where('course_slug', '[a-zA-Z0-9-_]+');

    Route::controller(StudentApiDashboardController::class)->group(function () {
        Route::get('student/dashboard', 'student_dashboard');
        Route::get('student/live-classes', 'student_live_classes');
        Route::post('student/free-enroll/{slug}', 'free_enroll')
            ->where('slug', '[a-zA-Z0-9-_]+');
        Route::get('student/order-history', 'order_history');
        Route::get('student/show-order-history/{id}', 'show_order_history');
        Route::get('student/order-invoice/{id}', 'print_invoice');
        Route::get('student/courses', 'student_courses');
        Route::get('student/learn-course/{slug}', 'learn_course');
        Route::get('student/wishlist', 'wishlist');
        Route::get('student/add-remove-wishlist/{course:slug}', 'add_remove_wishlist')->where('slug', '[a-zA-Z0-9-_]+');
        Route::get('student/profile', 'profile');
        Route::put('student/profile-update', 'profileUpdate');
        Route::put('student/profile-bio-update', 'profileBioUpdate');
        Route::put('student/profile-address-update', 'profileAddressUpdate');
        Route::get('student/cart-list', 'cart_list');
        Route::post('student/add-to-cart/{slug}', 'add_to_cart')->where('slug', '[a-zA-Z0-9-_]+');
        Route::delete('student/remove-from-cart/{slug}', 'remove_from_cart')->where('slug', '[a-zA-Z0-9-_]+');

 
    });
});
// Student dashboad APIs end


// instructor dashboad APIs start
// 2026-06-09 (security audit) — added 'api.instructor' so ONLY a coach /
// coach-staff token can reach these endpoints. Previously 'auth:sanctum'
// only, which let a student's token invoke coach actions (create course /
// students / sales, etc.). Queries are auth-id-scoped (no cross-coach leak),
// but this closes the cross-ROLE privilege gap.
Route::middleware(['auth:sanctum', 'api.instructor'])->group(function () {
    Route::controller(CoachDashboardController::class)->group(function () {
        Route::get('instructor-dashboard', 'instructor_dashboard');
        Route::get('instructor-courses', 'instructor_courses');
        Route::get('instructor-subscription-history', 'instructor_subscription_history');
        Route::get('instructor-questions', 'instructor_questions');
        Route::get('instructor-courses/{course_id}/questions', 'instructor_course_questions');
        Route::get('instructor-lessons/{lesson_id}/questions', 'instructor_lesson_questions');
        Route::post('instructor-questions/{question_id}/reply', 'reply_question');
        Route::put('instructor-questions/{question_id}/seen', 'mark_question_seen');
        Route::delete('instructor-questions/{question_id}', 'delete_question');
        Route::get('instructor/students', 'students');
        Route::post('instructor/students', 'createStudent');
        Route::put('instructor/students/{id}', 'updateStudent');
        Route::delete('instructor/students/{id}', 'deleteStudent');
        Route::get('instructor/announcements', 'announcements_list');
        Route::post('instructor/announcements', 'announcements_create');
        Route::put('instructor/announcements/{id}', 'announcements_update');
        Route::delete('instructor/announcements/{id}', 'announcements_delete');
        Route::get('instructor/sales', 'my_sells');
        // 2026-06-30 — dashboard endpoints. `sales/trend` + `sales/create` are
        // registered BEFORE `sales/{id}` so those literal segments are never
        // captured as an {id}; {id} is also numeric-constrained for safety.
        Route::get('instructor/sales/trend', 'sales_trend');
        Route::get('instructor/sales/create', 'my_sells_create');
        Route::post('instructor/sales', 'my_sells_store');
        Route::get('instructor/sales/{id}', 'my_sells_show')->whereNumber('id'); // was commented out → 404
        Route::put('instructor/sales/{id}', 'my_sells_update')->whereNumber('id');
        // Account sessions + payout/withdraw requests (Instructor Dashboard).
        Route::get('instructor/account/sessions', 'account_sessions');
        Route::get('instructor/withdraw-requests', 'withdraw_requests');
        // Live classes list + course-reviews moderation list (Instructor
        // Dashboard). Read-only list endpoints; scoped to the coach's own
        // course ids. Empty → [] + 200 (never 404).
        // Phase 1 — "Schedule live class" form loader (read-only). Registered
        // BEFORE the list so the literal /create-context segment is never
        // captured as a wildcard by any future /live-classes/{id} route.
        Route::get('instructor/live-classes/create-context', 'instructorLiveClassCreateContext');
        Route::get('instructor/live-classes', 'instructorLiveClasses');
        // Phase 2 — live-class management (Zoom gated by LIVE_CLASS_ZOOM_ENABLED).
        Route::post('instructor/live-classes', 'createInstructorLiveClass');
        Route::get('instructor/live-classes/{id}/host-start', 'instructorLiveClassHostStart')->whereNumber('id');
        // Attendance report + manual mark + recordings for one live class.
        Route::get('instructor/live-classes/{id}/attendance', 'liveClassAttendance')->whereNumber('id');
        Route::post('instructor/live-classes/{id}/attendance/manual', 'liveClassManualAttendance')->whereNumber('id');
        Route::get('instructor/live-classes/{id}/recordings', 'liveClassRecordings')->whereNumber('id');
        Route::put('instructor/live-classes/{id}', 'updateInstructorLiveClass')->whereNumber('id');
        Route::delete('instructor/live-classes/{id}', 'deleteInstructorLiveClass')->whereNumber('id');
        Route::get('instructor/course-reviews', 'instructorCourseReviews');
        // 404-fix Phase L — course-review moderation actions.
        Route::put('instructor/course-reviews/{id}/status', 'instructorCourseReviewSetStatus')->whereNumber('id');
        Route::delete('instructor/course-reviews/{id}', 'instructorCourseReviewDelete')->whereNumber('id');
        // 404-fix Phase A — coach coupons CRUD (coach_id-scoped).
        Route::get('instructor/coupons', 'instructorCouponsList');
        Route::post('instructor/coupons', 'instructorCouponStore');
        Route::put('instructor/coupons/{id}', 'instructorCouponUpdate')->whereNumber('id');
        Route::delete('instructor/coupons/{id}', 'instructorCouponDelete')->whereNumber('id');
        // 404-fix Phase B — coach staff + roles (coach_id-scoped).
        Route::get('instructor/staff-roles', 'instructorStaffRoles');
        Route::get('instructor/staff', 'instructorStaff');
        Route::post('instructor/staff', 'createInstructorStaff');
        Route::put('instructor/staff/{id}', 'updateInstructorStaff')->whereNumber('id');
        Route::delete('instructor/staff/{id}', 'deleteInstructorStaff')->whereNumber('id');
        // 404-fix Phase E — payout account + methods.
        Route::get('instructor/payout', 'instructorPayout');
        Route::put('instructor/payout', 'updateInstructorPayout');
        // Bug-fix — submit a withdrawal (was GET-only → POST returned 405).
        Route::post('instructor/withdraw-requests', 'instructorWithdrawStore');
        // 404-fix Phase J — account session revoke.
        Route::post('instructor/account/sessions/revoke-others', 'instructorRevokeOtherSessions');
        Route::delete('instructor/account/sessions/{tokenId}', 'instructorRevokeSession')->whereNumber('tokenId');
        // 404-fix Phase I — lesson edit/delete.
        Route::put('instructor/lessons/{lesson_id}', 'updateInstructorLesson')->whereNumber('lesson_id');
        Route::delete('instructor/lessons/{lesson_id}', 'deleteInstructorLesson')->whereNumber('lesson_id');
        // 404-fix Phase G — quizzes (list/edit/CRUD/questions/attempts/analytics).
        Route::get('instructor/courses/{courseId}/quizzes', 'instructorCourseQuizzes')->whereNumber('courseId');
        Route::post('instructor/chapters/{chapter_id}/quiz', 'createInstructorQuiz')->whereNumber('chapter_id');
        Route::get('instructor/quizzes/{id}/edit', 'instructorQuizEdit')->whereNumber('id');
        Route::get('instructor/quizzes/{id}/attempts', 'instructorQuizAttempts')->whereNumber('id');
        Route::get('instructor/quizzes/{id}/question-analytics', 'instructorQuizQuestionAnalytics')->whereNumber('id');
        Route::post('instructor/quizzes/{id}/questions', 'addInstructorQuizQuestion')->whereNumber('id');
        Route::put('instructor/quizzes/{id}', 'updateInstructorQuiz')->whereNumber('id');
        Route::delete('instructor/quizzes/{id}', 'deleteInstructorQuiz')->whereNumber('id');
        Route::get('instructor/quiz-attempts/{attemptId}', 'instructorQuizAttemptDetail')->whereNumber('attemptId');
        Route::put('instructor/questions/{id}', 'updateInstructorQuizQuestion')->whereNumber('id');
        Route::delete('instructor/questions/{id}', 'deleteInstructorQuizQuestion')->whereNumber('id');
        // 404-fix Phase H (part 1) — course status / enrollments / reorder.
        Route::patch('instructor/courses/{courseId}/status', 'instructorSetCourseStatus')->whereNumber('courseId');
        Route::get('instructor/courses/{courseId}/enrollments', 'instructorCourseEnrollments')->whereNumber('courseId');
        Route::patch('instructor/enrollments/{enrollmentId}', 'instructorEnrollmentSetAccess')->whereNumber('enrollmentId');
        Route::post('instructor/courses/{course_id}/chapters/sort', 'instructorSortChapters')->whereNumber('course_id');
        Route::post('instructor/chapters/{chapter_id}/lessons/sort', 'instructorSortLessons')->whereNumber('chapter_id');
        // 404-fix Phase H (part 2) — leaderboard + funnel reads.
        Route::get('instructor/courses/{courseId}/leaderboard', 'instructorCourseLeaderboard')->whereNumber('courseId');
        Route::get('instructor/courses/{courseId}/funnel', 'instructorCourseFunnel')->whereNumber('courseId');
        Route::get('instructor/course/{id}/analytics', 'instructorCourseAnalytics')->whereNumber('id');
        Route::get('instructor/course/{id}/edit', 'instructorCourseEditData')->whereNumber('id');
        // 404-fix final batch — student profile, email-change (OTP), broadcast,
        // bulk-enroll, course duplicate. (broadcast/bulk-enroll/duplicate write data.)
        Route::get('instructor/students/{userId}/profile', 'instructorStudentProfile')->whereNumber('userId');
        Route::post('instructor/account/email-change/request', 'instructorEmailChangeRequest')->middleware('throttle:5,1');
        Route::post('instructor/account/email-change/confirm', 'instructorEmailChangeConfirm')->middleware('throttle:5,1');
        Route::post('instructor/courses/{courseId}/broadcast', 'instructorCourseBroadcast')->whereNumber('courseId');
        Route::post('instructor/courses/{courseId}/bulk-enroll', 'instructorBulkEnroll')->whereNumber('courseId');
        Route::post('instructor/courses/{courseId}/duplicate', 'instructorCourseDuplicate')->whereNumber('courseId');
        // 404-fix Phase F — landing page.
        Route::get('instructor/landing-page', 'instructorLandingPage');
        Route::put('instructor/landing-page', 'updateInstructorLandingPage');
        Route::get('instructor/wishlist', 'wishlist_list');
        Route::delete('instructor/wishlist/{slug}', 'wishlist_delete');




        Route::post('instructor/courses', 'createCourse');
        Route::put('instructor/course/{course_id}', 'updateCourse');
        Route::post('instructor/course/{id}/more-info', 'updateMoreInfo');
        Route::post('instructor/course/{id}/chapter', 'addChapter');
        Route::put('instructor/chapters/{chapter_id}','updateChapter');
        Route::post('instructor/chapter/{chapter_id}/lesson', 'addLesson');
        Route::delete('instructor/courses/{course_id}/chapters/{chapter_id}', 'deleteChapter');
        Route::get('instructor/courses/{course_id}/content', 'getCourseContent');
        Route::get('instructor/course/{id}/analytics/progress', 'analyticsProgress');
        Route::get('instructor/course/{id}/analytics/sales', 'analyticsSales');
        Route::post('instructor/course/{id}/finish', 'finishCourse');

        Route::put('course/{id}', 'updateCourse');
        Route::put('course/{id}/more-info', 'updateMoreInfo');




        Route::put('instructor/courses/{slug}', 'updateCourse');
        Route::delete('instructor/courses/{slug}', 'deleteCourse');
    });

    // 404-fix final batch — announcements feed (from the user's enrolled courses).
    Route::get('announcements/feed', [\App\Http\Controllers\API\CoachDashboardController::class, 'announcementsFeed']);

    // 404-fix Phases C & D — coach notifications + direct messages. These
    // controllers were already implemented + tested but never routed under
    // instructor/*. All handlers are auth-user-scoped (a coach sees only their
    // own notifications / message threads), so reusing them is tenant-safe.
    // Coach notifications need the InboxEnvelope shape (data.notifications +
    // icon_color), which the student NotificationController does NOT return —
    // routing there made the app silently show empty. Use coach handlers.
    Route::get('instructor/notifications', [\App\Http\Controllers\API\CoachDashboardController::class, 'instructorNotifications']);
    Route::post('instructor/notifications/read-all', [\App\Http\Controllers\API\CoachDashboardController::class, 'instructorMarkAllNotificationsRead']);
    Route::post('instructor/notifications/{id}/read', [\App\Http\Controllers\API\CoachDashboardController::class, 'instructorMarkNotificationRead']);
    // Bug-fix: use CoachDashboardController handlers that return the DmConversations/
    // DmThread shapes the app models expect (MobileExtras returned a different shape).
    Route::get('instructor/messages', [\App\Http\Controllers\API\CoachDashboardController::class, 'instructorMessages']);
    Route::get('instructor/messages/{peerId}', [\App\Http\Controllers\API\CoachDashboardController::class, 'instructorMessageThread'])->whereNumber('peerId');
    Route::post('instructor/messages/{peerId}', [\App\Http\Controllers\API\CoachDashboardController::class, 'instructorMessageSend'])->whereNumber('peerId');

    // 404-fix Phase K — push device registry reuses the existing (auth-scoped)
    // PushDeviceController, same as the student /push-tokens routes.
    Route::post('instructor/devices/register', [\App\Http\Controllers\API\PushDeviceController::class, 'register']);
    Route::delete('instructor/devices/{token}', [\App\Http\Controllers\API\PushDeviceController::class, 'unregister'])->where('token', '.+');
});
// instructor dashboad APIs end

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthenticatedController::class, 'logout'])->name('api.logout');
    Route::post('logout/all-app', [AuthenticatedController::class, 'logoutAllApp'])->name('api.logoutAllApp');

    /* Phase 1A of the Student mobile-app build (2026-05-13) —
       FCM/APNs push-token registry. Called from the app on login and
       logout. Token uniqueness is enforced at the DB level. */
    Route::post('push-tokens', [\App\Http\Controllers\API\PushDeviceController::class, 'register'])
        ->name('api.push-tokens.register');
    Route::delete('push-tokens/{token}', [\App\Http\Controllers\API\PushDeviceController::class, 'unregister'])
        ->where('token', '.+')   // tokens contain colons/slashes/etc; allow anything
        ->name('api.push-tokens.unregister');

    /* Phase 1B (2026-05-13) — 2FA enrolment + management (post-auth).
       The challenge-verify endpoint (used during login) is in the
       guest group above. Throttle the mutating routes — TOTP confirm
       is bruteforceable (10^6 codes) and we don't want a hijacked
       Sanctum token to silently disable 2FA. */
    Route::post('2fa/enable', [\App\Http\Controllers\API\TwoFactorController::class, 'enable'])
        ->middleware('throttle:10,1')->name('api.2fa.enable');
    Route::post('2fa/confirm', [\App\Http\Controllers\API\TwoFactorController::class, 'confirm'])
        ->middleware('throttle:5,1')->name('api.2fa.confirm');
    Route::post('2fa/disable', [\App\Http\Controllers\API\TwoFactorController::class, 'disable'])
        ->middleware('throttle:5,1')->name('api.2fa.disable');
    Route::post('2fa/regenerate-recovery', [\App\Http\Controllers\API\TwoFactorController::class, 'regenerateRecovery'])
        ->middleware('throttle:5,1')->name('api.2fa.regenerate-recovery');

    Route::controller(DashboardController::class)->group(function () {
        Route::get('enrolled-courses', 'enrolled_courses');
        Route::get('wishlist-courses', 'wishlist_courses');
        Route::get('add-remove-wishlist/{course:slug}', 'add_remove_wishlist')->where('slug', '[a-zA-Z0-9-_]+');

        Route::get('learning/{slug}', 'course_learning')->where('slug', '[a-zA-Z0-9-_]+')->name('api.learning');
        Route::get('learning/{slug}/get-file-info/{type}/{lesson_id}', 'get_lesson_info')->where('slug', '[a-zA-Z0-9-_]+')->where('type', 'lesson|document|live|quiz')->where('lesson_id', '[0-9]+')->name('api.get-file-info');
        Route::get('learning/make-lesson-complete/{lesson_id}', 'make_lesson_complete')->where('lesson_id', '[0-9]+');

        Route::get('learning/{slug}/quiz/{id}', 'quiz_index')->where('slug', '[a-zA-Z0-9-_]+')->where('id', '[0-9]+')->name('api.quiz-index');
        Route::post('learning/{slug}/quiz/{id}', 'quiz_store')->where('slug', '[a-zA-Z0-9-_]+')->where('id', '[0-9]+');
        Route::get('learning/{slug}/quiz-results/{id}', 'quiz_results')->where('slug', '[a-zA-Z0-9-_]+')->where('id', '[0-9]+');

        Route::get('questions/{course_slug}/{lesson_id}', 'fetch_lesson_questions')->where('course_slug', '[a-zA-Z0-9-_]+')->where('lesson_id', '[0-9]+');
        Route::post('questions-create/{course_slug}/{lesson_id}', 'create_lesson_questions')->where('course_slug', '[a-zA-Z0-9-_]+')->where('lesson_id', '[0-9]+')->name('api.questions-create');
        Route::delete('questions-destroy/{question_id}', 'destroyQuestion')->where('question_id', '[0-9]+');

        Route::post('questions/replay/{lesson_id}/{question_id}', 'create_replay_questions')->where('lesson_id', '[0-9]+')->where('question_id', '[0-9]+');
        Route::delete('questions/replay/{reply_id}', 'destroyReply')->where('reply_id', '[0-9]+');

        Route::get('learning/{slug}/announcements', 'course_announcements')->where('slug', '[a-zA-Z0-9-_]+');

        Route::get('orders', 'orders');
        Route::get('orders/{invoice_id}', 'show_order')->where('invoice_id', '[a-zA-Z0-9-_]+');

        Route::get('reviews', 'reviews');
        Route::post('reviews', 'store_review');
        Route::get('reviews/{id}', 'show_review')->where('id', '[a-zA-Z0-9-_]+');
        Route::delete('reviews/{id}', 'destroy_review')->where('id', '[a-zA-Z0-9-_]+');
        Route::get('quiz-attempts', 'quiz_attempts');
        Route::get('quiz-attempts/{id}', 'show_quiz_attempt')->where('id', '[a-zA-Z0-9-_]+');
        Route::get('profile', 'profile');
        Route::post('update-profile-picture', 'update_profile_picture')->withoutMiddleware('json.only');
        Route::put('update-profile', 'update_profile');
        Route::put('update-bio', 'update_bio');
        Route::put('update-password', 'update_password');
        Route::put('update-address', 'update_address');
        Route::put('update-social-links', 'update_socials');
    });

    Route::controller(CartController::class)->group(function () {
        Route::get('cart-list', 'index');
        Route::post('add-to-cart/{slug}', 'add_to_cart')->where('slug', '[a-zA-Z0-9-_]+');
        Route::delete('remove-from-cart/{slug}', 'remove_from_cart')->where('slug', '[a-zA-Z0-9-_]+');
    });

    /* Phase 28 (2026-05-15) — Paid course buy-now via Razorpay.
       Distinct from membership checkout: single-course flow only (no
       cart, no coupon in v1). Reuses PaymentFulfilmentService for
       enrollment + instructor commission, same path the web success
       URL uses, so behaviour is identical. */
    Route::controller(\App\Http\Controllers\API\CoursePurchaseController::class)->group(function () {
        Route::get('course/buy/{slug}/quote',          'quote')
            ->where('slug', '[a-zA-Z0-9-_]+')
            ->name('api.course.buy.quote');
        Route::post('course/buy/{slug}/create-order',  'createOrder')
            ->where('slug', '[a-zA-Z0-9-_]+')
            ->middleware('throttle:10,1')
            ->name('api.course.buy.create-order');
        Route::post('course/buy/verify/{order}',       'verify')
            ->where('order', '[0-9]+')
            ->middleware('throttle:10,1')
            ->name('api.course.buy.verify');
    });

    /* 404-fix Phase M — cart checkout. CartCheckoutController::quote/createOrder/
       verify already existed but were never routed (app got 404 on checkout). */
    Route::controller(\App\Http\Controllers\API\CartCheckoutController::class)->group(function () {
        Route::post('cart/checkout/quote',          'quote')->middleware('throttle:20,1');
        Route::post('cart/checkout/create-order',   'createOrder')->middleware('throttle:10,1');
        Route::post('cart/checkout/verify/{order}', 'verify')->whereNumber('order')->middleware('throttle:10,1');
    });

    /* Phase 27 (2026-05-15) — Razorpay membership checkout for the
       mobile app. Mirrors the web Frontend\MembershipController +
       MembershipPaymentController flow exactly so wallet debits +
       referral-reward minting stay in lock-step. The mobile client
       does the SDK invocation locally and POSTs the resulting
       payment_id + signature to /verify. */
    Route::controller(\App\Http\Controllers\API\MembershipController::class)->group(function () {
        Route::get('membership/plans',                       'plans')
            ->name('api.membership.plans');
        Route::get('membership/quote/{plan}',                'quote')
            ->where('plan', '[0-9]+')
            ->name('api.membership.quote');
        Route::post('membership/create-order/{plan}',        'createOrder')
            ->where('plan', '[0-9]+')
            ->middleware('throttle:10,1')
            ->name('api.membership.create-order');
        Route::post('membership/verify/{membership}',        'verify')
            ->where('membership', '[0-9]+')
            ->middleware('throttle:10,1')
            ->name('api.membership.verify');
    });

    /* Phase 26 (2026-05-14) — Referral panel for the mobile app.
       Mirrors the data shape served by Frontend\ReferralController so
       the web "Refer & earn" page and the mobile screen render the
       same fields. Read-only — wallet credits are minted by existing
       server-side flows on first paid membership. */
    Route::get('referral', [\App\Http\Controllers\API\ReferralController::class, 'index'])
        ->name('api.referral.index');

    /* Phase 24 (2026-05-14) — Notifications inbox for the mobile app.
       Mirrors the same shape() as Frontend\NotificationController so the
       web bell-dropdown and the Android inbox stay aligned. UUID ids are
       not numeric, so no `where()` constraint — Eloquent's firstOrFail
       handles the not-found case. */
    Route::controller(\App\Http\Controllers\API\NotificationController::class)->group(function () {
        Route::get('notifications',                'index');
        Route::get('notifications/unread-count',   'unreadCount');
        Route::post('notifications/{id}/read',     'markRead');
        Route::post('notifications/read-all',      'markAllRead');
        Route::delete('notifications/{id}',        'destroy');
    });
});

Route::controller(FrontendController::class)->group(function () {
    Route::get('settings', 'settings');
    Route::get('countries', 'country_list');
    Route::get('social-links', 'socialLinks');
    Route::get('language-list', 'allLanguages');
    Route::get('currency-list', 'allCurrency');
    Route::get('static-language/{code?}', 'getLanguageFile');
    Route::post('contact-us', 'contactUs')->middleware('throttle:3,60');
    Route::post('subscribe-us', 'newsletter_request')->middleware('throttle:3,60');
    Route::get('course-main-categories', 'main_categories');
    Route::get('course-sub-categories/{slug}', 'sub_categories');
    Route::get('course-languages', 'course_languages');
    Route::get('course-levels', 'course_levels');
    Route::get('popular-courses', 'popular_courses');
    Route::get('fresh-courses', 'fresh_courses');
    Route::get('search-courses', 'search_courses');
    Route::get('course/{slug}', 'course_details')->where('slug', '[a-zA-Z0-9-_]+');
    Route::get('course/free-lesson-info/{lesson_id}', 'get_lesson_info')->where('lesson_id', '[0-9]+')->name('api.free-lesson');
    Route::get('course/reviews/{slug}', 'course_reviews')->where('slug', '[a-zA-Z0-9-_]+');
    Route::get('privacy-policy', 'privacy_policy');
    Route::get('terms-and-conditions', 'terms_and_conditions');
    Route::get('faqs', 'faqs');
    Route::get('on-boarding-screen', 'on_boarding_screen');
});

Route::middleware('payment.api')->group(function () {
    // Legacy bearer-token-in-URL downloads — kept for backward compat with
    // already-shipped app versions (F23). New app builds should use the signed
    // routes below instead (no token in the URL).
    Route::get('download-invoice/{invoice_id}', [DashboardController::class, 'downloadInvoice'])->where('invoice_id', '[a-zA-Z0-9-_]+')->withoutMiddleware('json.only');
    Route::get('download-certificate/{course_slug}', [DashboardController::class, 'downloadCertificate'])->where('course_slug', '[a-zA-Z0-9-_]+')->withoutMiddleware('json.only');
});

// F23 (audit 2026-06-26) — SIGNED download routes. The signature (minted by the
// *-download-link endpoints) authorizes the request; no bearer token rides in
// the URL, so nothing leaks into access logs / history / referer.
Route::middleware('signed')->group(function () {
    Route::get('secure-invoice/{invoice_id}', [DashboardController::class, 'downloadInvoice'])
        ->where('invoice_id', '[a-zA-Z0-9-_]+')->name('api.invoice.signed')->withoutMiddleware('json.only');
    Route::get('secure-certificate/{course_slug}', [DashboardController::class, 'downloadCertificate'])
        ->where('course_slug', '[a-zA-Z0-9-_]+')->name('api.certificate.signed')->withoutMiddleware('json.only');
});

Route::fallback(function () {
    return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
});
