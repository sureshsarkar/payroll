<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Webhook\StripeWebhookController;
use App\Http\Controllers\Webhook\RazorpayWebhookController;
use App\Http\Controllers\Webhook\BkashWebhookController;
use App\Http\Controllers\Webhook\PaypalWebhookController;
use App\Http\Controllers\Webhook\MercadoPagoWebhookController;
use App\Http\Controllers\Frontend\AboutPageController;
use App\Http\Controllers\Frontend\BecomeInstructorController;
use App\Http\Controllers\Frontend\BlogController;
use App\Http\Controllers\Frontend\CartController;
use App\Http\Controllers\Frontend\CheckOutController;
use App\Http\Controllers\Frontend\Coach\CoachStaffController;
use App\Http\Controllers\Frontend\Coach\CoachStaffPermissionController;
use App\Http\Controllers\Frontend\Coach\CoachCertificateBuilderController;
use App\Http\Controllers\Frontend\Coach\CoachStaffRoleController;
use App\Http\Controllers\Frontend\Coach\LandingPageController;
use App\Http\Controllers\Frontend\Coach\CoachSiteController;
use App\Http\Controllers\Frontend\CoachSitePublicController;
use App\Http\Controllers\Frontend\Coach\LandingPageEnquiryController;
use App\Http\Controllers\Frontend\Coach\LiveClassController;
use App\Http\Controllers\Frontend\ContactController;
use App\Http\Controllers\Frontend\CourseContentController;
use App\Http\Controllers\Frontend\CoursePageController;
use App\Http\Controllers\Frontend\FavoriteController;
use App\Http\Controllers\Frontend\HomePageController;
use App\Http\Controllers\Frontend\InstructorAnnouncementController;
use App\Http\Controllers\Frontend\InstructorCourseController;
use App\Http\Controllers\Frontend\InstructorDashboardController;
use App\Http\Controllers\Frontend\InstructorLessonQnaController;
use App\Http\Controllers\Frontend\InstructorLiveCredentialController;
use App\Http\Controllers\Frontend\InstructorPayoutController;
use App\Http\Controllers\Frontend\InstructorProfileSettingController;
use App\Http\Controllers\Frontend\InstructorSubscriptionsHistoryController;
use App\Http\Controllers\Frontend\LearningController;
use App\Http\Controllers\Frontend\QnaController;
use App\Http\Controllers\Frontend\StudentDashboardController;
use App\Http\Controllers\Frontend\StudentLiveClassController;
use App\Http\Controllers\Frontend\StudentOrderController;
use App\Http\Controllers\Frontend\StudentProfileSettingController;
use App\Http\Controllers\Frontend\StudentReviewController;
use App\Http\Controllers\Frontend\TermsandConditionController;
use App\Http\Controllers\Frontend\TinymceImageUploadController;
use App\Http\Controllers\Frontend\ZoomSignatureController;
use App\Http\Controllers\Global\CloudStorageController;
use Illuminate\Support\Facades\Route;
use Modules\Customer\app\Http\Controllers\CustomerController;

// Public health check for uptime monitors (no maintenance-mode block — needs to work
// even during maintenance for the monitor to detect that you're maintenance-down).
Route::get('/up', \App\Http\Controllers\HealthCheckController::class)->name('healthcheck');

/* Payment webhooks — POST endpoints called by gateway servers, NOT browsers.
 * No auth, no CSRF (exempted in VerifyCsrfToken middleware). Each handler
 * verifies the gateway signature internally.
 *
 * Throttle policy:
 *  - Stripe / Razorpay / PayPal: HMAC or PayPal-API verified before any
 *    work. The signature check rejects 99.99% of garbage in <1ms, so a
 *    permissive throttle:120,1 is just a safety net against burst spam.
 *  - bKash / MercadoPago: NO inbound signature; the handler re-queries
 *    the gateway's API to verify. An attacker spamming these would burn
 *    our gateway API quota and our outbound HTTP timeouts. Tighter
 *    throttle:30,1 here. */
Route::post('webhooks/stripe',      [StripeWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')->name('webhooks.stripe');
Route::post('webhooks/razorpay',    [RazorpayWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')->name('webhooks.razorpay');
Route::post('webhooks/bkash',       [BkashWebhookController::class, 'handle'])
    ->middleware('throttle:30,1')->name('webhooks.bkash');
Route::post('webhooks/paypal',      [PaypalWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')->name('webhooks.paypal');
Route::post('webhooks/mercadopago', [MercadoPagoWebhookController::class, 'handle'])
    ->middleware('throttle:30,1')->name('webhooks.mercadopago');

/**
 * 2026-05-21 — Caddy SSL on-demand allow-list endpoint (Path B P6+).
 * Caddy calls this with ?domain=<host> before issuing a Let's
 * Encrypt cert; we return 200 only for hostnames that exist in
 * coach_domains with verified_at set, 403 otherwise. Prevents the
 * Let's Encrypt rate-limit DoS hazard documented in
 * docs/PATH_B_WHITELABEL_OPERATOR_GUIDE.md.
 *
 * Sits OUTSIDE the web middleware group — no session, no CSRF
 * (Caddy makes an unauthenticated GET). Rate-limited to deflect
 * mass-probing of the coach catalog.
 */
Route::get('internal/ssl-allowed', [\App\Http\Controllers\Internal\SslAllowlistController::class, 'check'])
    ->middleware('throttle:60,1')
    ->name('internal.ssl-allowed');

Route::group(['middleware' => 'maintenance.mode'], function () {

    Route::get('/clear-cache', function () {
        \Artisan::call('route:clear');
        \Artisan::call('config:clear');
        \Artisan::call('view:clear');

        return 'Cache cleared!';
    })->middleware('auth:admin');

    // Coach landing-page subdomain routing — only register when coach_domain is a real
    // hostname with a dot (e.g. mbsguru.com). On localhost there's no wildcard DNS for
    // *.localhost so the group is useless AND breaks route:cache because the compiled
    // host pattern matches the bare host with an empty slug.
    if (str_contains((string) config('app.coach_domain'), '.')) {
        Route::domain('{coach_subdomain}.'.config('app.coach_domain'))->group(function () {
            // Coach Marketing Website multi-page (audit 2026-05-25)
            // Home = '/', other pages = '/{slug}'. Sitemap + robots also served per-domain.
            Route::get('/',              [CoachSitePublicController::class, 'showOnSubdomain'])->name('coach.site.subdomain.home');
            Route::get('/sitemap.xml',   [CoachSitePublicController::class, 'sitemap'])->name('coach.site.subdomain.sitemap');
            Route::get('/robots.txt',    [CoachSitePublicController::class, 'robots'])->name('coach.site.subdomain.robots');
            // 2026-06-12 — this single-segment catch-all must NOT shadow real
            // application routes, or they 404 as "missing coach page" on a
            // coach subdomain. That broke (a) the Forgot-password page and
            // (b) the PAYMENT flow: gateways redirect back to single-segment
            // routes (payment-success, pay-via-*, *-success) generated on the
            // coach-subdomain host, which were swallowed here → order never
            // marked paid. Exclude every reserved system slug (auth, commerce,
            // and ALL payment redirect/return routes) so they fall through to
            // the global routes. Coach page slugs (about-us, services, …) still
            // match. Built from a list for maintainability; applies to EVERY
            // coach subdomain automatically (no hardcoded coach/domain).
            $coachReservedSlugs = [
                // auth
                'login', 'register', 'logout', 'forgot-password', 'reset-password',
                // commerce
                'cart', 'checkout', 'dashboard', 'courses', 'blog',
                // shared dashboard pages (coach + student) — single-segment app
                // routes that must resolve, not be treated as a coach page slug.
                // 2026-06-26 — fixes /membership 404 on coach subdomains (and the
                // same class of bug for referral/affiliate/notifications).
                'membership', 'referral', 'affiliate', 'notifications',
                // 2026-07-06 (Role Permission Test doc) — the file manager popup
                // opens at the single-segment URL /frontend-filemanager (coach +
                // staff) / /laravel-filemanager (super-admin). Without reserving
                // them, thumbnail upload on a coach subdomain 404s as a missing
                // coach page. Sub-paths (/frontend-filemanager/upload …) are
                // multi-segment and already fall through.
                'frontend-filemanager', 'laravel-filemanager',
                // payment entry + return/redirect routes (every gateway)
                'payment', 'payment-success', 'payment-failed',
                'paypal-success-payment', 'mollie-payment-success', 'instamojo-success', 'stripe-success',
            ];
            $coachPageSlugPattern = '(?!(?:' . implode('|', $coachReservedSlugs) . '|pay-via-[a-z-]+)$)[a-z0-9-]+';
            Route::get('/{page_slug}',   [CoachSitePublicController::class, 'showOnSubdomain'])
                ->where('page_slug', $coachPageSlugPattern)
                ->name('coach.site.subdomain.page');
        });
    }




//     Route::get('/', function () {

//     $landing = app()->bound('landingPage') 
//         ? app('landingPage') 
//         : null;

//     if (!$landing) {
//         abort(404);
//     }
//     Route::get('/', [LandingPageController::class, 'publish_landing_page'])->name('publish-landing-page.show');
// });



    /**
     * ============================================================================
     * Global Routes
     * ============================================================================
     */
    Route::get('set-language', [DashboardController::class, 'setLanguage'])->name('set-language');
    Route::get('set-currency', [HomePageController::class, 'setCurrency'])->name('set-currency');

    Route::get('/', [HomePageController::class, 'index'])->name('home');

    Route::get('countries', [HomePageController::class, 'countries'])->name('countries');
    Route::get('states/{country_id}', [HomePageController::class, 'states'])->name('states');
    Route::get('cities/{state_id}', [HomePageController::class, 'cities'])->name('cities');

    /** become a instructor */
    Route::get('become-instructor', [BecomeInstructorController::class, 'index'])->name('become-instructor')->middleware('auth');
    Route::post('become-instructor', [BecomeInstructorController::class, 'store'])->name('become-instructor.create')->middleware('auth');

    Route::get('courses', [CoursePageController::class, 'index'])->name('courses');
    Route::get('fetch-courses', [CoursePageController::class, 'fetchCourses'])->name('fetch-courses');
    Route::get('course/{slug}', [CoursePageController::class, 'show'])->name('course.show');
 
    /** cart routes */
    Route::get('cart', [CartController::class, 'index'])->name('cart');
    Route::post('get-batch/{id}', [CartController::class, 'getBatch'])->name('get-batch');
    Route::post('add-to-cart-with-batch', [CartController::class, 'addToCartWithBatch'])->name('add-to-cart-with-batch');
    Route::post('add-to-cart/{id}', [CartController::class, 'addToCart'])->name('add-to-cart');
    Route::get('remove-cart-item/{cartId}/{rowId?}', [CartController::class, 'removeCartItem'])->name('remove-cart-item');
    Route::post('apply-coupon', [CartController::class, 'applyCoupon'])->name('apply-coupon');
    Route::get('remove-coupon', [CartController::class, 'removeCoupon'])->name('remove-coupon');

    // Terms and Conditions Route
    Route::get('terms-and-conditions', [TermsandConditionController::class, 'terms_and_conditions'])->name('terms-and-conditions');
    Route::get('privacy-policy', [TermsandConditionController::class, 'privacyPolicy'])->name('privacy-policy');

    // 2026-07-08 — public certificate verification (target of the QR + credential
    // id on every enterprise certificate). Two-segment path, so the coach
    // subdomain single-segment catch-all leaves it alone.
    Route::get('verify-certificate/{uid}', [\App\Http\Controllers\Frontend\CertificateVerificationController::class, 'show'])->name('certificate.verify');

    /** Blog Routes */
    Route::get('blog', [BlogController::class, 'index'])->name('blogs');
    // 2026-06-23 — coach-aware blog detail. On a coach host (subdomain / custom
    // domain) this serves the COACH'S published post; on the platform's own
    // domain it delegates to the platform blog (unchanged). Clean /blog/{slug}
    // on every surface.
    Route::get('blog/{slug}', [\App\Http\Controllers\Frontend\CoachBlogPublicController::class, 'show'])->name('blog.show');
    Route::post('blog/submit-comment', [BlogController::class, 'submitComment'])->name('blog.submit-comment');
    Route::get('all-coaches', [HomePageController::class, 'allInstructors'])->name('all-instructors');
    Route::get('coach-details/{id}/{slug?}', [HomePageController::class, 'instructorDetails'])->name('instructor-details');
    Route::post('quick-connect/{id}', [HomePageController::class, 'quickConnect'])->name('quick-connect');

    /** About page routes */
    Route::get('about-us', [AboutPageController::class, 'index'])->name('about-us');
    /** Contact page routes */
    Route::get('contact', [ContactController::class, 'index'])->name('contact.index');
    Route::post('contact/send-mail', [ContactController::class, 'sendMail'])
        ->middleware('throttle:5,1')
        ->name('contact.send-mail');

    /** Custom pages */
    Route::get('page/{slug}', [HomePageController::class, 'customPage'])->name('custom-page');

    /** other routes */
    Route::group(['prefix' => 'laravel-filemanager', 'middleware' => ['auth:admin'], 'as' => 'admin.'], function () {
        \UniSharp\LaravelFilemanager\Lfm::routes();
    });
    // V2 hardening (2026-06-16) — added `instructorrole` so the file manager is
    // reachable only by a coach or coach-staff (students are bounced). Superadmin
    // uses the separate admin file manager (/laravel-filemanager, auth:admin).
    // LFM remains per-user scoped (config/lfm.php: allow_private_folder=true,
    // allow_shared_folder=false) so Coach A cannot browse Coach B's files.
    Route::group(['prefix' => 'frontend-filemanager', 'as' => 'frontend.', 'middleware' => ['web', 'auth', 'verified', 'instructorrole']], function () {
        \UniSharp\LaravelFilemanager\Lfm::routes();
    });

    Route::get('change-theme/{name}', [HomePageController::class, 'changeTheme'])->name('change-theme');

    // Subdomain route

    //   Route::domain('{slug}.localhost')->group(function () {

    //         Route::get('/', [LandingPageController::class, 'publish_landing_page'])
    //             ->name('publish-landing-page.show');

    //     });

    // ── Coach-scoped white-label commerce surface (audit 2026-05-26) ───────
    // Cart / checkout / auth / dashboard rendered inside the COACH's master
    // layout so the URL stays on the coach's brand throughout the journey.
    // Order matters: these routes MUST be declared BEFORE the catch-all
    // marketing-page route below, otherwise "cart" / "checkout" would be
    // matched as a marketing page slug.
    //
    // Implementation pattern: each thin controller defers to the existing
    // platform controller for business logic, then renders a view scoped to
    // the coach master layout. This guarantees zero business-logic drift —
    // and protects us from breaking the platform-side cart/login/dashboard.
    Route::prefix('coach/{coachSlug}')
        ->middleware('tenant.context')
        ->where(['coachSlug' => '[a-z0-9-]+'])
        ->group(function () {
            // Cart + checkout
            Route::get('cart', [\App\Http\Controllers\Frontend\Coach\CoachCartController::class, 'index'])
                ->name('coach.cart');
            Route::get('checkout', [\App\Http\Controllers\Frontend\Coach\CoachCheckoutController::class, 'index'])
                ->name('coach.checkout');

            // Auth — login + register + logout under coach brand
            Route::get('login', [\App\Http\Controllers\Frontend\Coach\CoachAuthController::class, 'showLogin'])
                ->name('coach.login');
            Route::post('login', [\App\Http\Controllers\Frontend\Coach\CoachAuthController::class, 'login'])
                ->middleware('throttle:6,1')
                ->name('coach.login.submit');
            Route::get('register', [\App\Http\Controllers\Frontend\Coach\CoachAuthController::class, 'showRegister'])
                ->name('coach.register');
            Route::post('register', [\App\Http\Controllers\Frontend\Coach\CoachAuthController::class, 'register'])
                ->middleware('throttle:6,1')
                ->name('coach.register.submit');
            Route::post('logout', [\App\Http\Controllers\Frontend\Coach\CoachAuthController::class, 'logout'])
                ->name('coach.logout');

            // Student dashboard — courses + orders filtered to this coach
            // 2026-06-01 (audit) — add 2fa:web so a coach-site student with
            // 2FA enabled is challenged here too, matching the platform
            // student routes. ('verified' intentionally omitted to avoid
            // locking out not-yet-verified white-label students.)
            Route::middleware(['auth', 'studentrole', '2fa:web'])->group(function () {
                Route::get('student/my-courses',
                    [\App\Http\Controllers\Frontend\Coach\CoachStudentDashboardController::class, 'myCourses'])
                    ->name('coach.student.courses');
                Route::get('student/orders',
                    [\App\Http\Controllers\Frontend\Coach\CoachStudentDashboardController::class, 'orders'])
                    ->name('coach.student.orders');
                Route::get('student/dashboard',
                    [\App\Http\Controllers\Frontend\Coach\CoachStudentDashboardController::class, 'dashboard'])
                    ->name('coach.student.dashboard');
            });
        });

    // Coach blog detail on the PATH surface (localhost / no-DNS). Declared
    // BEFORE the catch-all page route so /coach/{slug}/blog/{post} resolves to
    // the post, not a "blog" page. Host surfaces use the clean /blog/{slug}.
    Route::get('coach/{site_slug}/blog/{slug}', [\App\Http\Controllers\Frontend\CoachBlogPublicController::class, 'showOnPath'])
        ->where('site_slug', '[a-z0-9-]+')
        ->where('slug', '[a-z0-9-]+')
        ->name('coach.blog.path');

    // Path-based fallback for the coach marketing website (audit 2026-05-25)
    // /coach/{site_slug}                — Home page
    // /coach/{site_slug}/{page_slug}    — other published pages (about/services/etc.)
    Route::get('coach/{site_slug}/{page_slug?}', [CoachSitePublicController::class, 'showOnPath'])
        ->where('site_slug', '[a-z0-9-]+')
        ->where('page_slug', '[a-z0-9-]+')
        ->name('coach.site.path');

    // Legacy single-page resolver kept as named-route shim so existing
    // mail / share links continue to work. It now reroutes through
    // the new multi-page resolver via showOnPath with no $page_slug.
    Route::get('coach-legacy/{slug}', [LandingPageController::class, 'publish_landing_page'])->name('publish-landing-page.path-show');
    Route::post('coach/landing-form', [LandingPageController::class, 'submit_landing_page'])
        ->middleware('throttle:5,1')
        ->name('publish-landing-page.submit');
    // 2026-06-24 — public submit for the Pricing & Plans booking modal. Lead is
    // attributed to the host's coach (forge-safe). Throttled against spam.
    Route::post('coach/pricing-enquiry', [\App\Http\Controllers\Frontend\PricingEnquiryController::class, 'store'])
        ->middleware('throttle:8,1')
        ->name('coach.pricing-enquiry');
    // 2026-06-26 — public submit for the REUSABLE Booking Enquiry modal (Class
    // Schedule "Book Now", Trainer "Book Personal Classes", any future CTA).
    // Same host-resolved, forge-safe attribution; throttled against spam.
    Route::post('coach/booking-enquiry', [\App\Http\Controllers\Frontend\PricingEnquiryController::class, 'storeBooking'])
        ->middleware('throttle:8,1')
        ->name('coach.booking-enquiry');
    // 2026-07-15 (restore) — schedule "Book a Session", trainer "Book Personal
    // Class Session", and the pricing/schedule/trainer payment verify+cancel.
    // These were referenced by the coach-site sections but had gone missing from
    // this file, 500-ing any coach page that renders a schedule_v1 section.
    Route::post('coach/schedule-booking', [\App\Http\Controllers\Frontend\PricingEnquiryController::class, 'storeScheduleBooking'])
        ->middleware('throttle:8,1')
        ->name('coach.schedule-booking');
    Route::post('coach/trainer-booking', [\App\Http\Controllers\Frontend\PricingEnquiryController::class, 'storeTrainerBooking'])
        ->middleware('throttle:8,1')
        ->name('coach.trainer-booking');
    Route::post('coach/pricing-enquiry/verify', [\App\Http\Controllers\Frontend\PricingEnquiryController::class, 'verify'])
        ->middleware('throttle:12,1')
        ->name('coach.pricing-enquiry.verify');
    Route::post('coach/pricing-enquiry/cancel', [\App\Http\Controllers\Frontend\PricingEnquiryController::class, 'cancel'])
        ->middleware('throttle:12,1')
        ->name('coach.pricing-enquiry.cancel');
    // TEMPORARY STUB (2026-07-15) — the original `gift-course` handler lived in the
    // prod routes/web.php that an out-of-date deploy overwrote; its controller is
    // not in this checkout. This stub keeps course-detail pages from 500-ing on
    // route('gift-course'); it forwards to the course page. Restore the real
    // gift-course route from the prod controller when available.
    Route::get('gift-course/{slug}', function ($slug) {
        return redirect('/course-details/' . $slug);
    })->name('gift-course');
    // 2026-07-03 — "Book Your Trial Session" popup: guest submit + Razorpay
    // verify. Host-resolved coach attribution; rate-limited against spam. The
    // charge amount is server-side (coach's configured price), never the client.
    Route::post('coach/trial-session', [\App\Http\Controllers\Frontend\TrialSessionController::class, 'submit'])
        ->middleware('throttle:8,1')
        ->name('coach.trial-session');
    Route::post('coach/trial-session/verify', [\App\Http\Controllers\Frontend\TrialSessionController::class, 'verify'])
        ->middleware('throttle:12,1')
        ->name('coach.trial-session.verify');
    Route::post('coach/service-form', [LandingPageController::class, 'submit_service_page'])
        ->middleware('throttle:5,1') // 2026-06-12 — match the landing form; block lead-spam on this public endpoint
        ->name('publish-service-page.submit');
    Route::get('courses/get-subcategories', [InstructorCourseController::class, 'getSubCategories'])->name('courses.subcategories');

    /* Zoom Meeting SDK signature endpoint — shared between student and
     * instructor live-class views. We can't put it inside `student.` or
     * `instructor.` groups because each group's role-middleware would lock
     * the other side out, so authorization (host vs. attendee, enrollment
     * gate) is enforced by the controller itself. */
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::post('zoom/sdk-signature/{lesson_id}', [ZoomSignatureController::class, 'issue'])
            ->whereNumber('lesson_id')
            ->middleware('throttle:30,1')
            ->name('zoom.sdk-signature');

        // 2026-06-05 — Pre-join status for the countdown / "waiting for the
        // coach" screen. The launcher polls this before/while waiting to join.
        // Same controller-side authorization (enrollment / batch / ownership).
        Route::get('live-class/{lesson_id}/status', [ZoomSignatureController::class, 'status'])
            ->whereNumber('lesson_id')
            ->middleware('throttle:120,1')
            ->name('zoom.live-status');

        // Live-class attendance log — launcher pings join/leave from the
        // Component View `connection-change` event listener. Throttled
        // generously since legitimate flaky-network rejoins can spike.
        Route::post('live-class/{live_class_id}/attendance',
            [\App\Http\Controllers\Frontend\LiveClassAttendanceController::class, 'track'])
            ->whereNumber('live_class_id')
            ->middleware('throttle:60,1')
            ->name('live-class.attendance');

        // Personal lesson notes — server-side mirror of the localStorage
        // notes drawer in the live-class launcher. GET fetches, POST upserts.
        // Authorization gate matches the attendance + signature endpoints:
        // enrollment OR instructor-of-course.
        Route::controller(\App\Http\Controllers\Frontend\LessonNoteController::class)
            ->prefix('lesson-notes')
            ->name('lesson-notes.')
            ->group(function () {
                Route::get('{lesson_id}',  'show')->whereNumber('lesson_id')->name('show');
                Route::post('{lesson_id}', 'store')->whereNumber('lesson_id')->middleware('throttle:60,1')->name('store');
            });

        /* 1:1 Instant Meeting room (2026-07-03) — shared by the coach (host) and
         * the invited student (attendee); role + access enforced in-controller,
         * so it can't live under the student-only or instructor-only groups. */
        Route::controller(\App\Http\Controllers\Frontend\InstantMeetingRoomController::class)
            ->prefix('instant-meeting')
            ->name('instant-meeting.')
            ->group(function () {
                Route::get('{id}/room', 'room')->whereNumber('id')->middleware('zoom.live.headers')->name('room');
                Route::get('{id}/status', 'status')->whereNumber('id')->middleware('throttle:120,1')->name('status');
                Route::post('{id}/signature', 'issue')->whereNumber('id')->middleware('throttle:30,1')->name('signature');
                Route::post('{id}/attendance', 'track')->whereNumber('id')->middleware('throttle:60,1')->name('attendance');
            });
    });

    /**
     * ============================================================================
     * Student Dashboard Routes
     * ============================================================================
     */
    // Route::group(['middleware' => ['auth', 'verified','role:student'], 'prefix' => 'student', 'as' => 'student.'], function () {
    Route::group(['middleware' => ['auth', 'verified', 'studentrole', '2fa:web'], 'prefix' => 'student', 'as' => 'student.'], function () {
        Route::get('dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        // 2026-05-21 — multi-coach: student leaves a coach's roster.
        // Throttled to discourage accidental rapid clicks.
        Route::post('coaches/{coach}/leave', [StudentDashboardController::class, 'leaveCoach'])
            ->whereNumber('coach')
            ->name('coaches.leave')
            ->middleware('throttle:6,1');

        // Profile setting routes
        Route::get('setting', [StudentProfileSettingController::class, 'index'])->name('setting.index');
        Route::put('setting/profile', [StudentProfileSettingController::class, 'updateProfile'])->name('setting.profile.update');
        Route::put('setting/bio', [StudentProfileSettingController::class, 'updateBio'])->name('setting.bio.update');
        Route::put('setting/password', [StudentProfileSettingController::class, 'updatePassword'])->name('setting.password.update');
        Route::get('setting/experience-modal', [StudentProfileSettingController::class, 'showExperienceModal'])->name('setting.experience-modal');
        Route::get('setting/edit-experience-modal/{id}', [StudentProfileSettingController::class, 'editExperienceModal'])->name('setting.edit-experience-modal');

        Route::post('setting/experience', [StudentProfileSettingController::class, 'storeExperience'])->name('setting.experience.store');
        Route::put('setting/experience/{id}', [StudentProfileSettingController::class, 'updateExperience'])->name('setting.experience.update');
        Route::delete('setting/experience/{id}', [StudentProfileSettingController::class, 'destroyExperience'])->name('setting.experience.destroy');

        Route::get('setting/add-education-modal', [StudentProfileSettingController::class, 'addEducationModal'])->name('setting.add-education-modal');
        Route::post('setting/education', [StudentProfileSettingController::class, 'storeEducation'])->name('setting.education.store');
        Route::get('setting/edit-education-modal/{id}', [StudentProfileSettingController::class, 'editEducationModal'])->name('setting.edit-education-modal');
        Route::put('setting/education/{id}', [StudentProfileSettingController::class, 'updateEducation'])->name('setting.education.update');
        Route::delete('setting/education/{id}', [StudentProfileSettingController::class, 'destroyEducation'])->name('setting.education.destroy');

        Route::put('setting/address', [StudentProfileSettingController::class, 'updateAddress'])->name('setting.address.update');
        Route::put('setting/socials', [StudentProfileSettingController::class, 'updateSocials'])->name('setting.socials.update');

        /** Order Routes */
        Route::get('orders', [StudentOrderController::class, 'index'])->name('orders.index');
        Route::get('order-details/{id}', [StudentOrderController::class, 'show'])->name('order.show');
        Route::get('order/invoice/{id}', [StudentOrderController::class, 'printInvoice'])->name('order.print-invoice');

        /** Order Routes */
        Route::get('live-classes', [StudentLiveClassController::class, 'index'])->name('live-classes.index');

        // Audit 2026-05-18 Req 1 — student-facing attendance overview.
        Route::get('attendance', [\App\Http\Controllers\Frontend\StudentAttendanceController::class, 'index'])
            ->name('attendance.index');

        // Phase 4C 2026-05-19 — Fee Management student-side.
        // /student/fees                 → dues list
        // /student/fees/{id}/checkout   → Razorpay order create + initiated row
        // /student/fees/checkout/verify → JS callback HMAC verify
        Route::get('fees', [\App\Http\Controllers\Frontend\StudentFeePaymentController::class, 'index'])
            ->name('fees.index');

        // 2026-05-20 — Student announcements (Phase E of audit).
        Route::get('announcements', [\App\Http\Controllers\Frontend\StudentAnnouncementController::class, 'index'])
            ->name('announcements.index');
        Route::get('announcements/unread-count.json',
                [\App\Http\Controllers\Frontend\StudentAnnouncementController::class, 'unreadCount'])
            ->name('announcements.unread-count');
        Route::get('announcements/{id}', [\App\Http\Controllers\Frontend\StudentAnnouncementController::class, 'show'])
            ->whereNumber('id')
            ->name('announcements.show');
        Route::post('announcements/{id}/read', [\App\Http\Controllers\Frontend\StudentAnnouncementController::class, 'markRead'])
            ->whereNumber('id')
            ->name('announcements.read');
        Route::post('fees/{demand}/checkout', [\App\Http\Controllers\Frontend\StudentFeePaymentController::class, 'checkout'])
            ->name('fees.checkout')
            ->whereNumber('demand');
        Route::post('fees/checkout/verify', [\App\Http\Controllers\Frontend\StudentFeePaymentController::class, 'verify'])
            ->name('fees.verify');

        Route::get('reviews', [StudentReviewController::class, 'index'])->name('reviews.index');
        Route::get('reviews/{id}', [StudentReviewController::class, 'show'])->name('reviews.show');
        Route::get('reviews-delete/{id}', [StudentReviewController::class, 'destroy'])->name('reviews.destroy');
        Route::get('enrolled-courses', [StudentDashboardController::class, 'enrolledCourses'])->name('enrolled-courses');
        Route::get('quiz-attempts', [StudentDashboardController::class, 'quizAttempts'])->name('quiz-attempts');

        /** learning routes */
        Route::get('learning/{slug}', [LearningController::class, 'index'])->name('learning.index');
        // Per-course "My attendance" view (2026-05-11) — the student-side
        // mirror of the instructor watchlist. Enrollment is enforced inside
        // the controller; defense-in-depth here would be a course-slug
        // middleware but every other learning/* route trusts the controller
        // for consistency.
        Route::get('learning/{slug}/my-attendance', [LearningController::class, 'myAttendance'])
            ->name('learning.my-attendance');
        Route::post('learning/get-file-info', [LearningController::class, 'getFileInfo'])->name('get-file-info');
        Route::post('learning/make-lesson-complete', [LearningController::class, 'makeLessonComplete'])->name('make-lesson-complete');
        Route::get('learning/resource-download/{id}', [LearningController::class, 'downloadResource'])->name('download-resource');

        Route::get('learning/quiz/{id}', [LearningController::class, 'quizIndex'])->name('quiz.index');
        Route::post('learning/quiz/{id}', [LearningController::class, 'quizStore'])->name('quiz.store');
        Route::get('learning/quiz-result/{id}/{result_id}', [LearningController::class, 'quizResult'])->name('quiz.result');
        Route::get('learning/{slug}/{lesson_id}', [LearningController::class, 'liveSession'])
            ->middleware('zoom.live.headers')
            ->name('learning.live');

        /** qna routes */
        Route::post('create-question', [QnaController::class, 'create'])->name('qna.create');
        Route::get('fetch-lesson-questions', [QnaController::class, 'fetchLessonQuestions'])->name('fetch-lesson-questions');
        Route::post('create-reply', [QnaController::class, 'createReply'])->name('create-reply');
        Route::get('fetch-replies', [QnaController::class, 'fetchReply'])->name('fetch-replies');

        Route::delete('delete-question/{id}', [QnaController::class, 'destroyQuestion'])->name('destroy-question');
        Route::delete('delete-reply/{id}', [QnaController::class, 'destroyReply'])->name('destroy-reply');

        /** course review Routes */
        Route::post('add-review', [LearningController::class, 'addReview'])->name('add-review');
        Route::get('fetch-reviews/{course_id}', [LearningController::class, 'fetchReviews'])->name('fetch-reviews');

        /** download certificate route */
        Route::get('download-certificate/{id}', [StudentDashboardController::class, 'downloadCertificate'])->name('download-certificate');
        Route::view('wishlist', 'frontend.wishlist.index')->name('wishlist');
    });

    /**
     * ============================================================================
     * Instructor Dashboard Routes
     * ============================================================================
     */

    // Route::group(['middleware' => ['auth', 'verified', 'approved.instructor', 'role:instructor'], 'prefix' => 'instructor', 'as' => 'instructor.'], function () {
    // Route::group(['middleware' => ['auth', 'verified', 'role:instructor'], 'prefix' => 'instructor', 'as' => 'instructor.'], function () {
    // ── Coach onboarding (Phase 3 — theme picker) ─────────────────────────
    // Standalone flow shown to coaches once after first login. Gated by
    // `auth` only (not the heavy instructor stack) so the wizard runs
    // before 2FA / membership / role checks kick in.
    Route::middleware('auth')->group(function () {
        Route::get('onboarding/theme-picker',  [\App\Http\Controllers\Frontend\Coach\OnboardingController::class, 'themePicker'])->name('onboarding.theme-picker');
        Route::post('onboarding/apply-theme',  [\App\Http\Controllers\Frontend\Coach\OnboardingController::class, 'applyTheme'])->name('onboarding.apply-theme');
        Route::get('onboarding/progress',      [\App\Http\Controllers\Frontend\Coach\OnboardingController::class, 'progress'])->name('onboarding.progress');
        Route::get('onboarding/skip',          [\App\Http\Controllers\Frontend\Coach\OnboardingController::class, 'skip'])->name('onboarding.skip');
    });

    // ── Lightweight coach-site preview route (audit 2026-05-26) ────────────
    // Mounted OUTSIDE the heavy instructor stack so the editor's preview
    // iframe never trips on 2fa challenges, membership redirects, or role
    // checks (any of which can produce malformed responses inside an iframe).
    // Auth is enforced via `auth` only; ownership is verified inside the
    // controller (`CoachSitePublicController::preview`).
    Route::get('coach-preview/{id}', [\App\Http\Controllers\Frontend\CoachSitePublicController::class, 'preview'])
        ->middleware('auth')
        ->name('instructor.coach.site.preview');

    Route::group(['middleware' => ['auth', 'verified', 'instructorrole', '2fa:web'], 'prefix' => 'instructor', 'as' => 'instructor.'], function () {
        Route::get('dashboard', [InstructorDashboardController::class, 'index'])->name('dashboard');

        // Coach-specific payment gateways (2026-06-29) — Enterprise self-managed.
        // `requires.enterprise` blocks non-Enterprise coaches at the URL, not just
        // the menu. Coach id is taken from auth (never the request) — no IDOR seam.
        Route::middleware(['requires.enterprise', 'permission:settings-payment-gateway,payment-gateways'])->group(function () {
            Route::get('payment-gateways', [\App\Http\Controllers\Instructor\CoachPaymentGatewayController::class, 'index'])->name('payment-gateways.index');
            Route::put('payment-gateways/{gateway}', [\App\Http\Controllers\Instructor\CoachPaymentGatewayController::class, 'update'])->name('payment-gateways.update');
        });
        Route::get('analytics', [\App\Http\Controllers\Frontend\CoachAnalyticsController::class, 'index'])->name('analytics.index')->middleware('requires.membership', 'permission:analytics'); // 2026-07-06 gate (Role Permission Test doc A)

        // 2026-07-18 (Dashboard Nav Enhancement #6/#7) — centralised Reports
        // module (Revenue / Payments / Invoices / Attendance). Same gate as
        // Analytics (financial data → permission:analytics) + membership. Coach
        // id is taken from auth inside the controller (tenant-scoped, no IDOR).
        Route::middleware(['requires.membership', 'permission:analytics'])->prefix('reports')->as('reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Frontend\CoachReportsController::class, 'index'])->name('index');
            Route::get('{type}/export', [\App\Http\Controllers\Frontend\CoachReportsController::class, 'export'])
                ->where('type', 'revenue|payments|invoices|attendance')->name('export');
            Route::get('{type}', [\App\Http\Controllers\Frontend\CoachReportsController::class, 'show'])
                ->where('type', 'revenue|payments|invoices|attendance')->name('show');
        });

        // Profile setting routes — 2026-07-04 (RBAC Phase 4): staff need the
        // matching settings-* permission to open a settings page (blocks direct
        // URL access). A real coach always bypasses the gate.
        Route::get('zoom-setting', [InstructorLiveCredentialController::class, 'index'])->name('zoom-setting.index')->middleware(['requires.membership', 'permission:settings-zoom']);
        Route::put('zoom-setting', [InstructorLiveCredentialController::class, 'update'])->name('zoom-setting.update')->middleware(['requires.membership', 'permission:settings-zoom']);
        Route::get('youtube-setting', [InstructorLiveCredentialController::class, 'youtube_index'])->name('youtube-setting.index')->middleware(['requires.membership', 'permission:settings-youtube']);
        Route::put('youtube-setting', [InstructorLiveCredentialController::class, 'youtube_update'])->name('youtube-setting.update')->middleware(['requires.membership', 'permission:settings-youtube']);
        Route::get('setting', [InstructorProfileSettingController::class, 'index'])->name('setting.index')->middleware(['requires.membership', 'permission:settings-profile']);

        /**
         * 2026-05-21 — Per-coach white-label brand settings (Phase B P1).
         * Logo / favicon upload + brand name / colors / support contact
         * / footer text / email signature. Coach overrides for the
         * platform's defaults; resolver composes both transparently.
         */
        Route::middleware('requires.membership')->group(function () {
            // 2026-07-04 (RBAC Phase 4) — brand settings gated by `settings-brand`.
            Route::get('brand-settings',           [\App\Http\Controllers\Frontend\Coach\CoachBrandSettingController::class, 'edit'])->name('brand-settings.edit')->middleware('permission:settings-brand');
            Route::post('brand-settings',          [\App\Http\Controllers\Frontend\Coach\CoachBrandSettingController::class, 'update'])->name('brand-settings.update')->middleware('permission:settings-brand');
            Route::post('brand-settings/test-smtp', [\App\Http\Controllers\Frontend\Coach\CoachBrandSettingController::class, 'testSmtp'])
                ->name('brand-settings.test-smtp')
                ->middleware(['throttle:6,1', 'permission:settings-brand']); // P3 — coach SMTP verification; rate-limited so a typo on the password doesn't hammer the SMTP server

            // 2026-06-22 — coach email preview + test-send. Lets a coach see how
            // their branded emails look and send themselves a live test.
            // 2026-07-04 (RBAC Phase 4) — email preview + templates gated by `settings-email`.
            Route::get('email-preview',  [\App\Http\Controllers\Frontend\Coach\CoachEmailPreviewController::class, 'index'])->name('email-preview.index')->middleware('permission:settings-email');
            Route::get('email-preview/render', [\App\Http\Controllers\Frontend\Coach\CoachEmailPreviewController::class, 'render'])->name('email-preview.render')->middleware('permission:settings-email');
            Route::post('email-preview/test-send', [\App\Http\Controllers\Frontend\Coach\CoachEmailPreviewController::class, 'testSend'])
                ->name('email-preview.test-send')
                ->middleware(['throttle:6,1', 'permission:settings-email']);

            // 2026-06-26 — per-coach (tenant-specific) email template editor.
            // Customise subject/body of white-label notification emails; revert
            // to the platform default any time. Tenant-scoped in the controller.
            Route::get('email-templates', [\App\Http\Controllers\Frontend\Coach\CoachEmailTemplateController::class, 'index'])->name('email-templates.index')->middleware('permission:settings-email');
            Route::get('email-templates/{key}/edit', [\App\Http\Controllers\Frontend\Coach\CoachEmailTemplateController::class, 'edit'])->name('email-templates.edit')->middleware('permission:settings-email');
            Route::put('email-templates/{key}', [\App\Http\Controllers\Frontend\Coach\CoachEmailTemplateController::class, 'update'])->name('email-templates.update')->middleware('permission:settings-email');
            Route::delete('email-templates/{key}', [\App\Http\Controllers\Frontend\Coach\CoachEmailTemplateController::class, 'reset'])->name('email-templates.reset')->middleware('permission:settings-email');

            /**
             * 2026-05-21 — Per-coach white-label P5 (custom domains).
             * Coach adds a hostname like coach1.com, we hand them a
             * TXT record, they prove DNS control, we mark verified.
             * Once verified the P2 middleware routes that host to
             * this coach's brand.
             */
            Route::post('domains',                 [\App\Http\Controllers\Frontend\Coach\CoachDomainController::class, 'store'])
                ->name('domains.store')
                ->middleware('throttle:10,1');
            // 2026-06-06 — Instant subdomain self-service: coach picks/renames
            // their <label>.<platform> link, live immediately (zero DNS).
            Route::post('domains/subdomain',       [\App\Http\Controllers\Frontend\Coach\CoachDomainController::class, 'setSubdomain'])
                ->name('domains.subdomain')
                ->middleware('throttle:10,1');
            Route::post('domains/{id}/verify',     [\App\Http\Controllers\Frontend\Coach\CoachDomainController::class, 'verify'])
                ->whereNumber('id')
                ->name('domains.verify')
                ->middleware('throttle:10,1');
            Route::delete('domains/{id}',          [\App\Http\Controllers\Frontend\Coach\CoachDomainController::class, 'destroy'])
                ->whereNumber('id')
                ->name('domains.destroy');
        });
        Route::put('setting/profile', [InstructorProfileSettingController::class, 'updateProfile'])->name('setting.profile.update')->middleware('requires.membership');
        Route::put('setting/bio', [InstructorProfileSettingController::class, 'updateBio'])->name('setting.bio.update')->middleware('requires.membership');
        Route::put('setting/password', [InstructorProfileSettingController::class, 'updatePassword'])->name('setting.password.update')->middleware('requires.membership');
        Route::get('setting/experience-modal', [InstructorProfileSettingController::class, 'showExperienceModal'])->name('setting.experience-modal')->middleware('requires.membership');
        Route::get('setting/edit-experience-modal/{id}', [InstructorProfileSettingController::class, 'editExperienceModal'])->name('setting.edit-experience-modal')->middleware('requires.membership');
        Route::post('setting/experience', [InstructorProfileSettingController::class, 'storeExperience'])->name('setting.experience.store')->middleware('requires.membership');
        Route::put('setting/experience/{id}', [InstructorProfileSettingController::class, 'updateExperience'])->name('setting.experience.update')->middleware('requires.membership');
        Route::delete('setting/experience/{id}', [InstructorProfileSettingController::class, 'destroyExperience'])->name('setting.experience.destroy')->middleware('requires.membership');
        Route::get('setting/add-education-modal', [InstructorProfileSettingController::class, 'addEducationModal'])->name('setting.add-education-modal')->middleware('requires.membership');
        Route::post('setting/education', [InstructorProfileSettingController::class, 'storeEducation'])->name('setting.education.store')->middleware('requires.membership');
        Route::get('setting/edit-education-modal/{id}', [InstructorProfileSettingController::class, 'editEducationModal'])->name('setting.edit-education-modal')->middleware('requires.membership');
        Route::put('setting/education/{id}', [InstructorProfileSettingController::class, 'updateEducation'])->name('setting.education.update')->middleware('requires.membership');
        Route::delete('setting/education/{id}', [InstructorProfileSettingController::class, 'destroyEducation'])->name('setting.education.destroy')->middleware('requires.membership');
        Route::put('setting/payout', [InstructorProfileSettingController::class, 'updatePayout'])->name('setting.payout.update')->middleware('requires.membership');
        Route::put('setting/address', [InstructorProfileSettingController::class, 'updateAddress'])->name('setting.address.update')->middleware('requires.membership');
        Route::put('setting/socials', [InstructorProfileSettingController::class, 'updateSocials'])->name('setting.socials.update')->middleware('requires.membership');

        /** Course Routes */
        Route::get('courses', [InstructorCourseController::class, 'index'])->name('courses.index')->middleware('requires.membership');
        Route::get('courses/create', [InstructorCourseController::class, 'create'])->name('courses.create')->middleware('requires.membership');
        Route::get('courses/create/{id}/step/{step?}', [InstructorCourseController::class, 'edit'])->name('courses.edit')->middleware('requires.membership');
        Route::get('courses/{id}/edit', [InstructorCourseController::class, 'editView'])->name('courses.edit-view')->middleware('requires.membership');
        // Attendance watchlist — per-course rollup of who's below the
        // attendance threshold across all live classes. IDOR-gated to
        // course owner via findOwnedCourseOrFail() in the controller.
        Route::get('courses/{id}/attendance-watchlist',
            [InstructorCourseController::class, 'attendanceWatchlist'])
            ->whereNumber('id')
            ->name('courses.attendance-watchlist')
            ->middleware('requires.membership');
        Route::get('courses/{id}/attendance-watchlist/export',
            [InstructorCourseController::class, 'attendanceWatchlistExport'])
            ->whereNumber('id')
            ->name('courses.attendance-watchlist.export')
            ->middleware('requires.membership');
        Route::get('courses/get-filters/{category_id}', [InstructorCourseController::class, 'getFiltersByCategory'])->name('courses.get-filters')->middleware('requires.membership');
        Route::get('courses/get-instructors', [InstructorCourseController::class, 'getInstructors'])->name('courses.get-instructors')->middleware('requires.membership');
        Route::post('courses/create', [InstructorCourseController::class, 'store'])->name('courses.store')->middleware('requires.membership');
        Route::post('courses/update', [InstructorCourseController::class, 'update'])->name('courses.update')->middleware('requires.membership');

        // ── Coach Marketing Website (Phase 1, audit 2026-05-25) ───────────
        // Replaces the legacy GrapesJS Newsletter-preset builder. The
        // /instructor/web-page entry point now serves the new section-based
        // multi-page editor. Legacy /website-builder routes are kept as
        // back-compat redirects in case anything still links to them.
        Route::get('web-page', [CoachSiteController::class, 'index'])
            ->name('web-page.index')
            ->middleware('requires.membership');

        // Backwards-compat — old bookmarks / templates redirect to the new builder.
        // We keep the route name `website-builder.index` because some legacy
        // views still reference it; the URL just forwards to the new dashboard.
        Route::get('web-page-legacy', function () {
            return redirect()->route('instructor.web-page.index');
        })->name('website-builder.index')->middleware('requires.membership');
        Route::get('website-builder-legacy', function () {
            return redirect()->route('instructor.web-page.index');
        })->name('landing-page-builder.index')->middleware('requires.membership');

        Route::post('web-page/site',                  [CoachSiteController::class, 'updateSite'])->name('web-page.site')->middleware('requires.membership');
        Route::post('web-page/pages',                 [CoachSiteController::class, 'createPage'])->name('web-page.pages.store')->middleware('requires.membership');
        Route::get('web-page/pages/{id}',             [CoachSiteController::class, 'editPage'])->name('web-page.edit')->middleware('requires.membership');
        Route::put('web-page/pages/{id}',             [CoachSiteController::class, 'updatePage'])->name('web-page.update')->middleware('requires.membership');
        Route::post('web-page/pages/{id}/publish',    [CoachSiteController::class, 'publishPage'])->name('web-page.publish')->middleware('requires.membership');
        Route::delete('web-page/pages/{id}',          [CoachSiteController::class, 'deletePage'])->name('web-page.destroy')->middleware('requires.membership');
        Route::post('web-page/pages/{id}/sections',   [CoachSiteController::class, 'addSection'])->name('web-page.sections.add')->middleware('requires.membership');
        Route::post('web-page/pages/{id}/reorder',    [CoachSiteController::class, 'reorderSections'])->name('web-page.sections.reorder')->middleware('requires.membership');
        Route::put('web-page/sections/{id}',          [CoachSiteController::class, 'updateSection'])->name('web-page.section.update')->middleware('requires.membership');
        Route::delete('web-page/sections/{id}',       [CoachSiteController::class, 'deleteSection'])->name('web-page.section.delete')->middleware('requires.membership');
        Route::get('web-page/sections/{id}/content',  [CoachSitePublicController::class, 'sectionContent'])->name('web-page.section.content')->middleware('requires.membership');
        Route::post('web-page/media',                 [CoachSiteController::class, 'uploadMedia'])->name('web-page.media')->middleware('requires.membership');
        // 2026-07-08 — course list for the Featured Courses Carousel section picker.
        Route::get('web-page/courses',                [CoachSiteController::class, 'coursesForPicker'])->name('web-page.courses')->middleware('requires.membership');
        Route::post('web-page/youtube-refresh',       [CoachSiteController::class, 'refreshYouTube'])->name('web-page.youtube-refresh')->middleware('requires.membership');
        // NOTE: a lightweight preview route is mounted OUTSIDE this group
        // so iframe-loaded previews never trip on the heavy middleware
        // stack (2fa, membership, role). See `coach.site.preview` below.
        Route::get('web-page/pages/{id}/versions',    [CoachSiteController::class, 'listVersions'])->name('web-page.versions')->middleware('requires.membership');
        Route::post('web-page/pages/{id}/restore/{vid}', [CoachSiteController::class, 'restoreVersion'])->name('web-page.restore')->middleware('requires.membership');

        // ── Full customization (audit 2026-05-25 evening) ─────────────────
        // Site-wide settings (analytics, favicon, sticky CTA, WhatsApp, social, custom CSS, footer)
        Route::get('web-page/settings',                    [CoachSiteController::class, 'getSettings'])->name('web-page.settings.get')->middleware('requires.membership');
        Route::post('web-page/settings',                   [CoachSiteController::class, 'updateSettings'])->name('web-page.settings.update')->middleware('requires.membership');
        // Section duplicate + show/hide toggle
        Route::post('web-page/sections/{id}/duplicate',    [CoachSiteController::class, 'duplicateSection'])->name('web-page.section.duplicate')->middleware('requires.membership');
        Route::post('web-page/sections/{id}/toggle-visible', [CoachSiteController::class, 'toggleSectionVisible'])->name('web-page.section.toggle-visible')->middleware('requires.membership');
        // Page duplicate + nav + slug + bulk reorder
        Route::post('web-page/pages/{id}/duplicate',       [CoachSiteController::class, 'duplicatePage'])->name('web-page.page.duplicate')->middleware('requires.membership');
        Route::post('web-page/pages/{id}/nav',             [CoachSiteController::class, 'updatePageNav'])->name('web-page.page.nav')->middleware('requires.membership');
        Route::put('web-page/pages/{id}/slug',             [CoachSiteController::class, 'updatePageSlug'])->name('web-page.page.slug')->middleware('requires.membership');
        Route::get('web-page/pages-list',                  [CoachSiteController::class, 'pagesList'])->name('web-page.pages.list')->middleware('requires.membership');
        Route::post('web-page/pages-reorder',              [CoachSiteController::class, 'reorderPages'])->name('web-page.pages.reorder')->middleware('requires.membership');
        Route::post('web-page/pages/{id}/starter-pack',    [CoachSiteController::class, 'applyStarterPack'])->name('web-page.page.starter-pack')->middleware('requires.membership');

        // Phase 4 — coach can change theme from their panel
        Route::get('web-page/theme-picker',  [CoachSiteController::class, 'themePickerInPanel'])->name('web-page.theme-picker')->middleware('requires.membership');
        Route::post('web-page/change-theme', [CoachSiteController::class, 'changeTheme'])->name('web-page.change-theme')->middleware('requires.membership');
        // 2026-06-16 — undo a theme switch (restore the soft-deleted previous site).
        Route::post('web-page/revert-theme', [CoachSiteController::class, 'revertThemeSwitch'])->name('web-page.revert-theme')->middleware('requires.membership');

        // 2026-06-17 — dedicated GLOBAL FOOTER (one per coach, shared on every page).
        Route::get('web-page/footer',  [CoachSiteController::class, 'editGlobalFooter'])->name('web-page.footer')->middleware('requires.membership');
        Route::post('web-page/footer', [CoachSiteController::class, 'updateGlobalFooter'])->name('web-page.footer.update')->middleware('requires.membership');

        // 2026-06-17 — fully customizable NAVIGATION MENU (per-coach menu builder).
        Route::get('web-page/menu',                 [\App\Http\Controllers\Frontend\Coach\CoachMenuController::class, 'index'])->name('web-page.menu')->middleware('requires.membership');
        Route::post('web-page/menu/items',          [\App\Http\Controllers\Frontend\Coach\CoachMenuController::class, 'storeItem'])->name('web-page.menu.item.store')->middleware('requires.membership');
        Route::put('web-page/menu/items/{id}',      [\App\Http\Controllers\Frontend\Coach\CoachMenuController::class, 'updateItem'])->name('web-page.menu.item.update')->middleware('requires.membership');
        Route::delete('web-page/menu/items/{id}',   [\App\Http\Controllers\Frontend\Coach\CoachMenuController::class, 'deleteItem'])->name('web-page.menu.item.delete')->middleware('requires.membership');
        Route::post('web-page/menu/reorder',        [\App\Http\Controllers\Frontend\Coach\CoachMenuController::class, 'reorder'])->name('web-page.menu.reorder')->middleware('requires.membership');
        Route::post('web-page/menu/toggle',         [\App\Http\Controllers\Frontend\Coach\CoachMenuController::class, 'toggleMenu'])->name('web-page.menu.toggle')->middleware('requires.membership');

        // Legacy GrapesJS endpoints kept temporarily for back-compat. New
        // coaches don't see them — only used by html_passthrough_v1 migration.
        Route::post('website-builder/media-upload', [LandingPageController::class, 'mediaUpload'])->name('landing-page-builder.media.upload')->middleware('requires.membership');
        // 2026-07-15 (restore) — builder autosave; referenced by the landing-page editor.
        Route::post('website-builder/save/{id}', [LandingPageController::class, 'saveBuilder'])->name('landing-page-builder.save')->middleware('requires.membership');
        Route::post('check-website-name', [LandingPageController::class, 'checkWebsiteName'])->name('check.website-builder.name')->middleware('requires.membership');
        Route::post('website-name-store', [LandingPageController::class, 'storeName'])->name('website-builder.submit')->middleware('requires.membership');
        Route::get('publish-website/{id}/{status}', [LandingPageController::class, 'publishWebsite'])->name('setPublishData')->middleware('requires.membership');
        Route::delete('web-page-delete/{id}', [LandingPageController::class, 'deleteWebsite'])->name('website-builder-delete')->middleware('requires.membership');
        Route::post('website-product-store/{id}', [LandingPageController::class, 'storeProduct'])->name('website-builder-product.submit')->middleware('requires.membership');

        /** Coach Staff Routes */
        Route::get('coach-staff', [CoachStaffController::class, 'index'])->name('coach-staff.index')->middleware('requires.membership');
        Route::get('coach-staff/create', [CoachStaffController::class, 'create'])->name('coach-staff.create')->middleware('requires.membership');
        Route::post('coach-staff/store', [CoachStaffController::class, 'store'])->name('coach-staff.store')->middleware('requires.membership');
        Route::get('coach-staff/{user}/edit', [CoachStaffController::class, 'edit'])->name('coach-staff.edit')->middleware('requires.membership');
        Route::put('coach-staff/update/{id}', [CoachStaffController::class, 'update'])->name('coach-staff.update')->middleware('requires.membership');
        Route::delete('coach-staff/delete/{id}', [CoachStaffController::class, 'destroy'])->name('coach-staff.destroy')->middleware('requires.membership');

        /** Coach Coupon Routes (2026-06-16) — each coach creates/manages their own
            coupons; scoped to coach_id, applied only on that coach's site. */
        Route::get('coupons', [\App\Http\Controllers\Frontend\Coach\CoachCouponController::class, 'index'])->name('coupons.index')->middleware('requires.membership');
        Route::post('coupons/store', [\App\Http\Controllers\Frontend\Coach\CoachCouponController::class, 'store'])->name('coupons.store')->middleware('requires.membership');
        Route::put('coupons/update/{id}', [\App\Http\Controllers\Frontend\Coach\CoachCouponController::class, 'update'])->name('coupons.update')->middleware('requires.membership');
        Route::delete('coupons/delete/{id}', [\App\Http\Controllers\Frontend\Coach\CoachCouponController::class, 'destroy'])->name('coupons.destroy')->middleware('requires.membership');

        /** Coach Blog Routes (2026-06-23) — each coach manages their own blog
            posts; scoped to coach_id, shown only on that coach's website. */
        Route::get('blogs', [\App\Http\Controllers\Frontend\Coach\CoachBlogController::class, 'index'])->name('blogs.index')->middleware('requires.membership');
        Route::get('blogs/create', [\App\Http\Controllers\Frontend\Coach\CoachBlogController::class, 'create'])->name('blogs.create')->middleware('requires.membership');
        Route::post('blogs', [\App\Http\Controllers\Frontend\Coach\CoachBlogController::class, 'store'])->name('blogs.store')->middleware('requires.membership');
        Route::get('blogs/{id}/edit', [\App\Http\Controllers\Frontend\Coach\CoachBlogController::class, 'edit'])->name('blogs.edit')->middleware('requires.membership');
        Route::put('blogs/{id}', [\App\Http\Controllers\Frontend\Coach\CoachBlogController::class, 'update'])->name('blogs.update')->middleware('requires.membership');
        Route::delete('blogs/{id}', [\App\Http\Controllers\Frontend\Coach\CoachBlogController::class, 'destroy'])->name('blogs.destroy')->middleware('requires.membership');
        Route::put('blogs/{id}/status', [\App\Http\Controllers\Frontend\Coach\CoachBlogController::class, 'toggleStatus'])->name('blogs.status')->middleware('requires.membership');

        /** Pricing & Plans enquiries (2026-06-24) — leads from the website
            Book Class modal; scoped to the coach.
            2026-07-04 (RBAC Phase 4) — staff need the `pricing-enquiries`
            permission; a real coach always bypasses the gate. */
        Route::middleware('permission:pricing-enquiries')->group(function () {
        Route::get('pricing-enquiries', [\App\Http\Controllers\Frontend\Coach\PricingEnquiryAdminController::class, 'index'])->name('pricing-enquiries.index')->middleware('requires.membership');
        Route::put('pricing-enquiries/{id}/status', [\App\Http\Controllers\Frontend\Coach\PricingEnquiryAdminController::class, 'updateStatus'])->name('pricing-enquiries.status')->middleware('requires.membership');
        Route::delete('pricing-enquiries/{id}', [\App\Http\Controllers\Frontend\Coach\PricingEnquiryAdminController::class, 'destroy'])->name('pricing-enquiries.destroy')->middleware('requires.membership');
        }); // permission:pricing-enquiries

        /** Trial Session popup (2026-07-03) — per-coach settings, time-slot CRUD,
            and read-only enquiry + payment lists. Scoped to the coach.
            2026-07-04 (RBAC Phase 4) — staff need `trial-sessions`; coach bypasses. */
        Route::middleware('permission:trial-sessions')->group(function () {
        Route::get('trial-sessions', [\App\Http\Controllers\Frontend\Coach\CoachTrialSessionController::class, 'index'])->name('trial-sessions.index')->middleware('requires.membership');
        Route::put('trial-sessions/settings', [\App\Http\Controllers\Frontend\Coach\CoachTrialSessionController::class, 'update'])->name('trial-sessions.update')->middleware('requires.membership');
        Route::post('trial-sessions/slots', [\App\Http\Controllers\Frontend\Coach\CoachTrialSessionController::class, 'storeSlot'])->name('trial-sessions.slots.store')->middleware('requires.membership');
        Route::put('trial-sessions/slots/{id}', [\App\Http\Controllers\Frontend\Coach\CoachTrialSessionController::class, 'updateSlot'])->name('trial-sessions.slots.update')->middleware('requires.membership');
        Route::delete('trial-sessions/slots/{id}', [\App\Http\Controllers\Frontend\Coach\CoachTrialSessionController::class, 'destroySlot'])->name('trial-sessions.slots.destroy')->middleware('requires.membership');
        Route::get('trial-sessions/enquiries', [\App\Http\Controllers\Frontend\Coach\CoachTrialSessionController::class, 'enquiries'])->name('trial-sessions.enquiries.index')->middleware('requires.membership');
        Route::put('trial-sessions/enquiries/{id}/status', [\App\Http\Controllers\Frontend\Coach\CoachTrialSessionController::class, 'updateEnquiryStatus'])->name('trial-sessions.enquiries.status')->middleware('requires.membership');
        Route::get('trial-sessions/payments', [\App\Http\Controllers\Frontend\Coach\CoachTrialSessionController::class, 'payments'])->name('trial-sessions.payments.index')->middleware('requires.membership');
        }); // permission:trial-sessions

        /** Coach My Plan & Billing (2026-06-24, Phase 4) — READ-ONLY. The coach
            views their Super-Admin-assigned plan + billing; no edit routes. */
        Route::get('my-plan', [\App\Http\Controllers\Frontend\Coach\CoachBillingController::class, 'index'])->name('my-plan.index')->middleware('requires.membership', 'permission:my-plan'); // 2026-07-06 gate (Role Permission Test doc A)

        /** Role Routes */
        Route::get('staff-role', [CoachStaffRoleController::class, 'index'])->name('coach-staff-role.index')->middleware('requires.membership');
        Route::get('staff-role/create', [CoachStaffRoleController::class, 'create'])->name('coach-staff-role.create')->middleware('requires.membership');
        Route::post('staff-role/store', [CoachStaffRoleController::class, 'store'])->name('coach-staff-role.store')->middleware('requires.membership');
        Route::get('staff-role/edit/{id}', [CoachStaffRoleController::class, 'edit'])->name('coach-staff-role.edit')->middleware('requires.membership');
        Route::put('staff-role/update{id}', [CoachStaffRoleController::class, 'update'])->name('coach-staff-role.update')->middleware('requires.membership');
        Route::delete('staff-role/delete/{id}', [CoachStaffRoleController::class, 'destroy'])->name('coach-staff-role.destroy')->middleware('requires.membership');

        /** Role Routes */
        Route::get('landing-page-enquiry', [LandingPageEnquiryController::class, 'index'])->name('landing-page-enquiry.index')->middleware('requires.membership');
        Route::get('landing-page-enquiry/create', [LandingPageEnquiryController::class, 'create'])->name('landing-page-enquiry.create')->middleware('requires.membership');
        Route::post('landing-page-enquiry/store', [LandingPageEnquiryController::class, 'store'])->name('landing-page-enquiry.store')->middleware('requires.membership');
        Route::get('landing-page-enquiry/edit/{id}', [LandingPageEnquiryController::class, 'edit'])->name('landing-page-enquiry.edit')->middleware('requires.membership');
        Route::put('landing-page-enquiry/update{id}', [LandingPageEnquiryController::class, 'update'])->name('landing-page-enquiry.update')->middleware('requires.membership');
        Route::delete('landing-page-enquiry/delete/{id}', [LandingPageEnquiryController::class, 'destroy'])->name('landing-page-enquiry.destroy')->middleware('requires.membership');
        // Tier-1 CRM upgrade (2026-05-12): in-line status change (AJAX) +
        // streaming CSV export of the filtered enquiry list.
        Route::post('landing-page-enquiry/{id}/status', [LandingPageEnquiryController::class, 'updateStatus'])
            ->whereNumber('id')
            ->name('landing-page-enquiry.update-status')
            ->middleware('requires.membership');
        Route::get('landing-page-enquiry/export', [LandingPageEnquiryController::class, 'export'])
            ->name('landing-page-enquiry.export')
            ->middleware('requires.membership');

        // Tier-2 CRM upgrade (2026-05-12):
        //   - Kanban view (table/kanban toggle)
        //   - Lead detail page with notes timeline + follow-up
        //   - Bulk actions (status change / delete)
        Route::get('landing-page-enquiry/kanban', [LandingPageEnquiryController::class, 'kanban'])
            ->name('landing-page-enquiry.kanban')
            ->middleware('requires.membership');
        Route::get('landing-page-enquiry/{id}/show', [LandingPageEnquiryController::class, 'show'])
            ->whereNumber('id')
            ->name('landing-page-enquiry.show')
            ->middleware('requires.membership');
        Route::post('landing-page-enquiry/{id}/notes', [LandingPageEnquiryController::class, 'addNote'])
            ->whereNumber('id')
            ->name('landing-page-enquiry.notes.add')
            ->middleware(['requires.membership', 'throttle:30,1']);
        Route::post('landing-page-enquiry/{id}/follow-up', [LandingPageEnquiryController::class, 'setFollowUp'])
            ->whereNumber('id')
            ->name('landing-page-enquiry.follow-up')
            ->middleware('requires.membership');
        // 2026-06-12 Phase 2 — convert a won lead into a student (funnel completion)
        Route::post('landing-page-enquiry/{id}/convert', [LandingPageEnquiryController::class, 'convertToStudent'])
            ->whereNumber('id')
            ->name('landing-page-enquiry.convert')
            ->middleware('requires.membership');
        Route::post('landing-page-enquiry/bulk', [LandingPageEnquiryController::class, 'bulk'])
            ->name('landing-page-enquiry.bulk')
            ->middleware('requires.membership');

        // Tier-3 CRM upgrade (2026-05-12):
        //   - assign-to-staff
        //   - send email (logs in email_sends, audit-logged)
        //   - CSV import (two-step: preview then commit)
        Route::post('landing-page-enquiry/{id}/assign', [LandingPageEnquiryController::class, 'assign'])
            ->whereNumber('id')
            ->name('landing-page-enquiry.assign')
            ->middleware('requires.membership');
        Route::post('landing-page-enquiry/{id}/email', [LandingPageEnquiryController::class, 'sendEmail'])
            ->whereNumber('id')
            ->name('landing-page-enquiry.email')
            ->middleware(['requires.membership', 'throttle:20,1']);
        Route::get('landing-page-enquiry/import', [LandingPageEnquiryController::class, 'importForm'])
            ->name('landing-page-enquiry.import')
            ->middleware('requires.membership');
        Route::post('landing-page-enquiry/import/preview', [LandingPageEnquiryController::class, 'importPreview'])
            ->name('landing-page-enquiry.import.preview')
            ->middleware(['requires.membership', 'throttle:10,1']);
        Route::post('landing-page-enquiry/import/commit', [LandingPageEnquiryController::class, 'importCommit'])
            ->name('landing-page-enquiry.import.commit')
            ->middleware(['requires.membership', 'throttle:5,1']);

        /** Live Classes Routes */
        Route::get('live-classes', [LiveClassController::class, 'index'])->name('live-classes.index')->middleware('requires.membership');
        Route::get('live-class/{lesson_id}', [LiveClassController::class, 'coachLiveSession'])
            ->name('live-class')
            ->middleware(['requires.membership', 'zoom.live.headers']);
        // NOTE: live-counts must come BEFORE live-classes/{id} so it
        // doesn't get captured by the greedy {id} edit route.
        Route::get('live-classes/live-counts', [LiveClassController::class, 'liveCounts'])
            ->name('live-classes.live-counts')
            ->middleware('requires.membership');
        Route::get('live-classes/{id}', [LiveClassController::class, 'edit'])->name('live-class.edit')->middleware('requires.membership');
        Route::get('live-classes/{live_class_id}/attendance', [LiveClassController::class, 'attendance'])
            ->name('live-class.attendance')
            ->middleware('requires.membership');
        // 2026-06-05 — Coach marks a live class COMPLETED: sets ended_at (class
        // moves to "completed", no longer joinable) and credits attendance to
        // each attendee's course progress. Coach / assigned-teacher only.
        Route::post('live-classes/{live_class_id}/complete', [LiveClassController::class, 'markCompleted'])
            ->whereNumber('live_class_id')
            ->name('live-class.complete')
            ->middleware(['requires.membership', 'throttle:30,1']);
        // CSV export of the same attendance data — instructors download
        // for compliance / record-keeping. Same auth gate as the HTML view.
        Route::get('live-classes/{live_class_id}/attendance/export', [LiveClassController::class, 'attendanceExport'])
            ->name('live-class.attendance.export')
            ->middleware('requires.membership');
        // Manual attendance override (#7, 2026-05-12) — instructor marks
        // a student present with a reason. For tech-failure scenarios.
        Route::post('live-classes/{live_class_id}/attendance/manual',
            [LiveClassController::class, 'attendanceManualMark'])
            ->name('live-class.attendance.manual-mark')
            ->middleware(['requires.membership', 'throttle:30,1']);
        Route::post('live-classes/{id}', [LiveClassController::class, 'update'])->name('live-class.update')->middleware('requires.membership');
        Route::post('live-class/store', [LiveClassController::class, 'store'])->name('live-class.store')->middleware('requires.membership');
        // Audit 2026-05-19 phase 4 — Instant Live Class.
        // Creates a Zoom meeting + supporting lesson/chapter rows
        // immediately and returns a redirect URL the FE can open.
        Route::post('live-class/instant', [LiveClassController::class, 'instantStart'])
            ->name('live-class.instant')
            ->middleware('requires.membership');

        /** 1:1 Instant Meeting (2026-07-03) — coach picks one student and starts a
         *  private Zoom room now. Separate from batch/group Live Classes. */
        // 2026-07-04 (RBAC Phase 4) — staff need `instant-meetings`; coach bypasses.
        // Starting a meeting additionally accepts the granular `instant-meetings-create`.
        Route::get('instant-meetings', [\App\Http\Controllers\Frontend\Coach\InstantMeetingController::class, 'index'])
            ->name('instant-meetings.index')->middleware(['requires.membership', 'permission:instant-meetings']);
        Route::post('instant-meetings/start', [\App\Http\Controllers\Frontend\Coach\InstantMeetingController::class, 'start'])
            ->name('instant-meetings.start')->middleware(['requires.membership', 'throttle:20,1', 'permission:instant-meetings-create,instant-meetings']);
        Route::post('instant-meetings/{id}/end', [\App\Http\Controllers\Frontend\Coach\InstantMeetingController::class, 'end'])
            ->whereNumber('id')->name('instant-meetings.end')->middleware(['requires.membership', 'throttle:30,1', 'permission:instant-meetings']);

        /** Fee Management — Phase 4B foundation (2026-05-19).
         *  Coach raises fee demands against batches and tracks payments.
         *  Razorpay collection + student-side checkout come in a
         *  follow-up phase. */
        Route::middleware('requires.membership')->prefix('fees')->name('fees.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Frontend\Coach\FeeManagementController::class, 'index'])
                ->name('index');
            Route::post('demands', [\App\Http\Controllers\Frontend\Coach\FeeManagementController::class, 'storeDemand'])
                ->name('demands.store');
            Route::get('transactions', [\App\Http\Controllers\Frontend\Coach\FeeManagementController::class, 'transactions'])
                ->name('transactions');
            Route::get('demands/{demand}/students', [\App\Http\Controllers\Frontend\Coach\FeeManagementController::class, 'studentsForDemand'])
                ->name('demands.students');
            Route::post('demands/{demand}/payments', [\App\Http\Controllers\Frontend\Coach\FeeManagementController::class, 'recordPayment'])
                ->name('payments.store');
            Route::post('payments/{payment}/refund', [\App\Http\Controllers\Frontend\Coach\FeeManagementController::class, 'refundPayment'])
                ->name('payments.refund');
        });

        /** Coach Staff Permission — read-only on the coach side.
         *  Permissions live in the global `coach_staff_permissions` table (no `added_by` column),
         *  so allowing coaches to mutate them is a privilege-escalation hole. Keep `index` so coaches
         *  can see/assign permissions in role forms, but mutations belong to admin (admin/role/...).
         */
        Route::get('staff-permission', [CoachStaffPermissionController::class, 'index'])->name('coach-staff-permission.index')->middleware('requires.membership');
        // 2026-07-15 (restore) — were referenced by the staff-permission create form.
        Route::post('staff-permission', [CoachStaffPermissionController::class, 'store'])->name('coach-staff-permission.store')->middleware('requires.membership');
        Route::put('staff-permission/{id}', [CoachStaffPermissionController::class, 'update'])->name('coach-staff-permission.update')->middleware('requires.membership');

        /** 2026-06-12 — Per-coach certificate builder (coach designs their OWN
         *  branded certificate; scoped to their coach_id, never the global one). */
        Route::get('certificate-builder', [CoachCertificateBuilderController::class, 'index'])->name('certificate-builder.index')->middleware('requires.membership');
        Route::put('certificate-builder', [CoachCertificateBuilderController::class, 'update'])->name('certificate-builder.update')->middleware('requires.membership');
        Route::post('certificate-builder/item/update', [CoachCertificateBuilderController::class, 'updateItem'])->name('certificate-builder.item.update')->middleware('requires.membership');
        // 2026-07-09 — one-click reset of the Classic drag layout to clean defaults.
        Route::post('certificate-builder/reset-layout', [CoachCertificateBuilderController::class, 'resetLayout'])->name('certificate-builder.reset-layout')->middleware('requires.membership');

        /** Subscriptions History Routes */
        Route::get('subscription-histories', [InstructorSubscriptionsHistoryController::class, 'index'])->name('subscription-histories.index')->middleware('requires.membership');
        Route::get('subscription-histories/{id}', [InstructorSubscriptionsHistoryController::class, 'show'])->name('subscription-histories.show')->middleware('requires.membership');
        Route::get('subscriptions/create', [InstructorSubscriptionsHistoryController::class, 'create'])->name('subscriptions.create')->middleware('requires.membership');
        Route::get('subscriptions/create/{id}/step/{step?}', [InstructorSubscriptionsHistoryController::class, 'edit'])->name('subscriptions.edit')->middleware('requires.membership');
        Route::get('subscriptions/{id}/edit', [InstructorSubscriptionsHistoryController::class, 'editView'])->name('subscriptions.edit-view')->middleware('requires.membership');
        Route::get('subscriptions/get-filters/{category_id}', [InstructorSubscriptionsHistoryController::class, 'getFiltersByCategory'])->name('subscriptions.get-filters')->middleware('requires.membership');
        Route::get('subscriptions/get-instructors', [InstructorSubscriptionsHistoryController::class, 'getInstructors'])->name('subscriptions.get-instructors')->middleware('requires.membership');
        Route::post('subscriptions/create', [InstructorSubscriptionsHistoryController::class, 'store'])->name('subscriptions.store')->middleware('requires.membership');
        Route::post('subscriptions/update', [InstructorSubscriptionsHistoryController::class, 'update'])->name('subscriptions.update')->middleware('requires.membership');

        /** Course content routes */
        Route::post('course-chapter/{course_id?}/store', [CourseContentController::class, 'chapterStore'])->name('course-chapter.store')->middleware('requires.membership');
        Route::get('course-chapter/sorting/{course_id}', [CourseContentController::class, 'chapterSorting'])->name('course-chapter.sorting.index')->middleware('requires.membership');
        Route::get('course-chapter/edit/{chapter_id}', [CourseContentController::class, 'chapterEdit'])->name('course-chapter.edit')->middleware('requires.membership');
        Route::put('course-chapter/update/{chapter_id}', [CourseContentController::class, 'chapterUpdate'])->name('course-chapter.update')->middleware('requires.membership');
        Route::delete('course-chapter/delete/{chapter_id}', [CourseContentController::class, 'chapterDestroy'])->name('course-chapter.destroy')->middleware('requires.membership');

        Route::post('course-chapter/sorting/{course_id}', [CourseContentController::class, 'chapterSortingStore'])->name('course-chapter.sorting.store')->middleware('requires.membership');
        Route::get('course-chapter/lesson/create', [CourseContentController::class, 'lessonCreate'])->name('course-chapter.lesson.create')->middleware('requires.membership');
        Route::post('course-chapter/lesson/create', [CourseContentController::class, 'lessonStore'])->name('course-chapter.lesson.store')->middleware('requires.membership');
        Route::get('course-chapter/lesson/edit', [CourseContentController::class, 'lessonEdit'])->name('course-chapter.lesson.edit')->middleware('requires.membership');

        Route::post('course-chapter/lesson/update', [CourseContentController::class, 'lessonUpdate'])->name('course-chapter.lesson.update')->middleware('requires.membership');
        Route::delete('course-chapter/lesson/{chapter_item_id}/destroy', [CourseContentController::class, 'chapterLessonDestroy'])->name('course-chapter.lesson.destroy')->middleware('requires.membership');
        Route::post('course-chapter/lesson/sorting/{chapter_id}', [CourseContentController::class, 'sortLessons'])->name('course-chapter.lesson.sorting')->middleware('requires.membership');

        Route::get('course-chapter/quiz-question/create/{quiz_id}', [CourseContentController::class, 'createQuizQuestion'])->name('course-chapter.quiz-question.create')->middleware('requires.membership');
        Route::post('course-chapter/quiz-question/create/{quiz_id}', [CourseContentController::class, 'storeQuizQuestion'])->name('course-chapter.quiz-question.store')->middleware('requires.membership');
        Route::get('course-chapter/quiz-question/edit/{question_id}', [CourseContentController::class, 'editQuizQuestion'])->name('course-chapter quiz-question.edit')->middleware('requires.membership');
        Route::put('course-chapter/quiz-question/update/{question_id}', [CourseContentController::class, 'updateQuizQuestion'])->name('course-chapter.quiz-question.update')->middleware('requires.membership');
        Route::delete('course-chapter/quiz-question/delete/{question_id}', [CourseContentController::class, 'destroyQuizQuestion'])->name('course-chapter.quiz-question.destroy')->middleware('requires.membership');
        Route::get('course-delete-request/{course_id}', [InstructorCourseController::class, 'showDeleteRequest'])->name('course.delete-request.show')->middleware('requires.membership');
        Route::post('course-delete-request', [InstructorCourseController::class, 'sendDeleteRequest'])->name('course.send-delete-request')->middleware('requires.membership');

        /** Course Batches Routes */
        Route::get('course-batches/{course_id?}', [InstructorCourseController::class, 'batchesIndex'])->name('course-batches.index')->middleware('requires.membership');
        Route::post('course-batches/store', [InstructorCourseController::class, 'batchesStore'])->name('course-batches.store')->middleware('requires.membership');
        Route::get('course-batches/{id}/edit', [InstructorCourseController::class, 'batchesEdit'])->name('course-batches.edit')->middleware('requires.membership');
        Route::put('course-batches/{id}/update', [InstructorCourseController::class, 'batchesUpdate'])->name('course-batches.update')->middleware('requires.membership');
        Route::delete('course-batches/{id}/delete', [InstructorCourseController::class, 'batchesDestroy'])->name('course-batches.destroy')->middleware('requires.membership');
        Route::get('get-batches/{course_id}', [InstructorCourseController::class, 'getBatchesByCourse'])->name('get-batches-by-course');

        // Per-coach Tax Settings (Phase 1, 2026-06-13). Each coach configures
        // their own optional tax profile + rates; nothing applies platform-wide.
        // 2026-07-04 (RBAC Phase 4) — gated by `settings-tax`; coach bypasses.
        Route::middleware('permission:settings-tax')->group(function () {
        Route::get('tax-settings', [\App\Http\Controllers\Frontend\Coach\CoachTaxController::class, 'index'])->name('tax.index');
        Route::get('tax-settings/report', [\App\Http\Controllers\Frontend\Coach\CoachTaxController::class, 'report'])->name('tax.report');
        Route::post('tax-settings/profile', [\App\Http\Controllers\Frontend\Coach\CoachTaxController::class, 'updateProfile'])->name('tax.profile');
        Route::post('tax-settings/rates', [\App\Http\Controllers\Frontend\Coach\CoachTaxController::class, 'storeRate'])->name('tax.rates.store');
        Route::put('tax-settings/rates/{id}', [\App\Http\Controllers\Frontend\Coach\CoachTaxController::class, 'updateRate'])->name('tax.rates.update');
        Route::delete('tax-settings/rates/{id}', [\App\Http\Controllers\Frontend\Coach\CoachTaxController::class, 'destroyRate'])->name('tax.rates.destroy');
        }); // permission:settings-tax

        /**
         * 2026-05-20 — Teacher → Batch assignment CRUD (coach-side).
         * Coach grants their CoachStaff "teachers" access to specific
         * course_batches. Gate is enforced at every teacher-facing
         * live-class route via TeacherBatchAssignment::assignedBatchIdsFor().
         */
        Route::middleware('requires.membership')->prefix('teacher-batches')->name('teacher-batches.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class, 'index'])->name('index');
            Route::get('create',             [\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class, 'create'])->name('create');
            Route::post('/',                 [\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class, 'store'])->name('store');
            Route::get('{id}/edit',          [\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}',               [\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}',            [\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::get('batches/{courseId}', [\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class, 'batchesForCourse'])->whereNumber('courseId')->name('batches-for-course');
        });

        /*
         * Audit 2026-05-18 — coach-side batch attendance summary.
         * Renders cards showing Total / Attended Today / Not Attended Today
         * for a single batch the coach owns.
         */
        Route::get('batch-attendance/{batch}', [\App\Http\Controllers\Frontend\CoachBatchAttendanceController::class, 'show'])
            ->whereNumber('batch')
            ->name('batch-attendance.show')
            ->middleware('requires.membership');

        // Audit 2026-05-18 phase 2 — bulk manual attendance + CSV export
        Route::post('batch-attendance/{batch}/bulk-mark', [\App\Http\Controllers\Frontend\CoachBatchAttendanceController::class, 'bulkMark'])
            ->whereNumber('batch')
            ->name('batch-attendance.bulk-mark')
            ->middleware('requires.membership');
        Route::get('batch-attendance/{batch}/export.csv', [\App\Http\Controllers\Frontend\CoachBatchAttendanceController::class, 'exportCsv'])
            ->whereNumber('batch')
            ->name('batch-attendance.export')
            ->middleware('requires.membership');

        // 2026-06-02 — assign course-enrolled students (who aren't in this
        // batch yet, e.g. batch_id NULL) INTO this batch, so batch-scoped
        // fees / announcements / attendance reach them. Handles the multi-batch
        // courses the auto-assign rule deliberately can't resolve on its own.
        Route::post('batch-attendance/{batch}/assign-students', [\App\Http\Controllers\Frontend\CoachBatchAttendanceController::class, 'assignStudents'])
            ->whereNumber('batch')
            ->name('batch-attendance.assign-students')
            ->middleware('requires.membership');

        /** payout routes */
        Route::get('payout', [InstructorPayoutController::class, 'index'])->name('payout.index')->middleware('requires.membership');
        Route::get('payout/create', [InstructorPayoutController::class, 'create'])->name('payout.create')->middleware('requires.membership');
        Route::post('payout/create', [InstructorPayoutController::class, 'store'])->name('payout.store')->middleware('requires.membership');
        Route::delete('payout/delete/{id}', [InstructorPayoutController::class, 'destroy'])->name('payout.destroy')->middleware('requires.membership');

        /** announcement routes */
        Route::resource('announcements', InstructorAnnouncementController::class)->middleware('requires.membership');
        // Audit 2026-05-18 — AJAX endpoint for the create/edit form to load
        // the coach's active batches when a course is selected.
        Route::get('announcements/batches/{course}', [InstructorAnnouncementController::class, 'batchesForCourse'])
            ->name('announcements.batches-for-course')
            ->middleware('requires.membership');
        // Audit 2026-05-18 phase 3 — attachment upload/delete + download.
        Route::delete('announcements/attachments/{attachment}', [InstructorAnnouncementController::class, 'destroyAttachment'])
            ->whereNumber('attachment')
            ->name('announcements.attachments.destroy');
        Route::get('announcements/attachments/{attachment}/download', [InstructorAnnouncementController::class, 'downloadAttachment'])
            ->whereNumber('attachment')
            ->name('announcements.attachments.download');

        /** coach students routes */
        Route::get('coach-students', [InstructorDashboardController::class, 'myStudents'])->name('my-students.index')->middleware('requires.membership');
        Route::get('coach-students/create', [InstructorDashboardController::class, 'createStudents'])->name('my-students.create')->middleware('requires.membership');
        Route::post('coach-students/store', [InstructorDashboardController::class, 'storeStudetns'])->name('my-students.store')->middleware('requires.membership');
        Route::get('coach-students/{id}/edit', [InstructorDashboardController::class, 'editStudents'])->name('my-students.edit')->middleware('requires.membership');
        Route::post('coach-students/{id}', [InstructorDashboardController::class, 'updateStudents'])->name('my-students.update')->middleware('requires.membership');
        Route::delete('coach-students/{id}', [InstructorDashboardController::class, 'destroy'])->name('my-students.destroy')->middleware('requires.membership');

        // 2026-07-15 — Student batch assignment / reassignment. Same-course only,
        // tenant + permission gated, transaction-safe (StudentBatchService).
        Route::controller(\App\Http\Controllers\Frontend\Coach\CoachStudentBatchController::class)
            ->middleware('requires.membership')->group(function () {
                Route::post('coach-students/batch/bulk-assign', 'bulkAssign')->name('my-students.batch.bulk-assign');
                Route::get('coach-students/{id}/batch/context', 'context')->whereNumber('id')->name('my-students.batch.context');
                Route::post('coach-students/{id}/batch/reassign', 'reassign')->whereNumber('id')->name('my-students.batch.reassign');
            });

        // 2026-07-15 — Temporary (date-specific) batch slots. Same-course, keeps
        // the primary batch untouched; tenant + permission gated.
        Route::controller(\App\Http\Controllers\Frontend\Coach\CoachTemporarySlotController::class)
            ->middleware('requires.membership')->group(function () {
                Route::get('coach-students/{id}/temp-slot/context', 'context')->whereNumber('id')->name('my-students.temp-slot.context');
                Route::post('coach-students/{id}/temp-slot', 'store')->whereNumber('id')->name('my-students.temp-slot.store');
                Route::get('temporary-slots', 'index')->name('temporary-slots.index');
                Route::delete('temporary-slots/{slot}', 'destroy')->whereNumber('slot')->name('temporary-slots.destroy');
            });

        // 2026-07-15 (restore) — Trainers CRUD + session packages + bookings.
        // Referenced by the trainer views; had gone missing from this file.
        Route::controller(\App\Http\Controllers\Frontend\Coach\TrainerController::class)
            ->middleware('requires.membership')->group(function () {
                Route::get('trainers', 'index')->name('trainers.index');
                Route::get('trainers/bookings', 'bookings')->name('trainers.bookings');
                Route::put('trainers/bookings/{id}/status', 'updateBookingStatus')->name('trainers.bookings.status');
                Route::delete('trainers/bookings/{id}', 'destroyBooking')->name('trainers.bookings.destroy');
                Route::post('trainers', 'store')->name('trainers.store');
                Route::get('trainers/{id}/edit', 'edit')->name('trainers.edit');
                Route::put('trainers/{id}', 'update')->name('trainers.update');
                Route::put('trainers/{id}/toggle', 'toggle')->name('trainers.toggle');
                Route::delete('trainers/{id}', 'destroy')->name('trainers.destroy');
                Route::post('trainers/{id}/packages', 'storePackage')->name('trainers.packages.store');
                Route::put('trainers/{id}/packages/{pkg}', 'updatePackage')->name('trainers.packages.update');
                Route::put('trainers/{id}/packages/{pkg}/toggle', 'togglePackage')->name('trainers.packages.toggle');
                Route::delete('trainers/{id}/packages/{pkg}', 'destroyPackage')->name('trainers.packages.destroy');
            });


        
        /** institute branch routes — removed 2026-05-01: InstructorBranchController doesn't exist
         *  in the codebase, all routes were throwing class-not-found 500s. If this feature is
         *  meant to be built, recreate the controller and add the routes back.
         */



        /** coach sales routes */
        Route::get('coach-orders', [InstructorDashboardController::class, 'mySells'])->name('my-sells.index')->middleware('requires.membership');
        Route::get('coach-orders/create', [InstructorDashboardController::class, 'create'])->name('my-sells.create')->middleware('requires.membership');
        Route::get('get-batches', [InstructorCourseController::class, 'getBatches'])->name('my-sells.getbatches')->middleware('requires.membership');
        Route::post('coach-orders/store', [InstructorDashboardController::class, 'store'])->name('my-sells.store')->middleware('requires.membership');
        Route::get('coach-orders/{id}', [InstructorDashboardController::class, 'mySellsShow'])->name('my-sells.show')->middleware('requires.membership');
        Route::post('coach-orders/{id}', [InstructorDashboardController::class, 'mySellsupdate'])->name('my-sells.update')->middleware('requires.membership');
        Route::get('order/invoice/{id}', [InstructorDashboardController::class, 'printInvoice'])->name('my-sells.print-invoice');

        /** Offline Payment routes (2026-07-11, Phase 1) — record-only, tenant-scoped */
        Route::get('offline-payments', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'index'])->name('offline-payments.index')->middleware('requires.membership');
        Route::post('offline-payments/settings', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'updateSettings'])->name('offline-payments.settings')->middleware('requires.membership');
        Route::get('offline-payments/export', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'exportCsv'])->name('offline-payments.export')->middleware('requires.membership');
        Route::post('offline-payments/course', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'storeCourse'])->name('offline-payments.store-course')->middleware('requires.membership');
        Route::post('offline-payments/order/{orderId}', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'recordForOrder'])->name('offline-payments.record-order')->middleware('requires.membership');
        Route::post('offline-payments/trial/{enquiryId}', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'recordForTrial'])->name('offline-payments.record-trial')->middleware('requires.membership');
        Route::post('offline-payments/{id}/approve', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'approve'])->name('offline-payments.approve')->middleware('requires.membership');
        Route::post('offline-payments/{id}/reject', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'reject'])->name('offline-payments.reject')->middleware('requires.membership');
        Route::post('offline-payments/{id}/cancel', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'cancel'])->name('offline-payments.cancel')->middleware('requires.membership');
        Route::get('offline-payments/{id}/proof', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'downloadProof'])->name('offline-payments.proof')->middleware('requires.membership');
        Route::get('offline-payments/{id}/receipt', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'receipt'])->name('offline-payments.receipt')->middleware('requires.membership');
        Route::get('offline-payments/{id}/receipt-pdf', [\App\Http\Controllers\Frontend\Coach\OfflinePaymentController::class, 'receiptPdf'])->name('offline-payments.receipt-pdf')->middleware('requires.membership');

        /** lessons qna routes */
        Route::get('lesson-question', [InstructorLessonQnaController::class, 'index'])->name('lesson-questions.index')->middleware('requires.membership');
        Route::post('lesson-question/{id}', [InstructorLessonQnaController::class, 'createReply'])->name('lesson-question.reply')->middleware('requires.membership');
        Route::delete('lesson-question/destroy/{id}', [InstructorLessonQnaController::class, 'destroyQuestion'])->name('lesson-question.destroy')->middleware('requires.membership');
        Route::delete('lesson-question/reply/destroy/{id}', [InstructorLessonQnaController::class, 'destroyReply'])->name('lesson-reply.destroy')->middleware('requires.membership');
        Route::put('lesson-question/seen-update/{id}', [InstructorLessonQnaController::class, 'markAsReadUnread'])->name('lesson-question.seen-update')->middleware('requires.membership');

        Route::post('cloud/store', [CloudStorageController::class, 'store'])->name('cloud.store');

        // get subscription by instructor
        Route::post('instructor-subscription/{id}', [CustomerController::class, 'assign_subscription'])->name('instructor-subscription');

        Route::view('wishlist', 'frontend.instructor-dashboard.wishlist.index')->name('wishlist')->middleware('requires.membership');

    });

    /** wishlist routes */
    Route::group(['middleware' => ['auth', 'verified']], function () {
        Route::controller(FavoriteController::class)->group(function () {
            Route::get('wishlist/{course:slug}', 'update')->name('wishlist.update');
            Route::delete('wishlist/{course:slug}', 'destroy')->name('wishlist.remove');
        });
        /** secure-video route */
        Route::get('secure-video/{hash}', App\Http\Controllers\SecureLinkPreviewController::class)->name('secure.video')->middleware('signed');
    });

    Route::group(['middleware' => ['auth', 'verified']], function () {
        Route::get('checkout', [CheckOutController::class, 'index'])->name('checkout.index');
        Route::post('tinymce-upload-image', [TinymceImageUploadController::class, 'upload']);
        Route::delete('tinymce-delete-image', [TinymceImageUploadController::class, 'destroy']);

        // Browser-push subscription save/delete (used by /global/push/push.js)
        Route::post('push/subscribe',   [\App\Http\Controllers\Frontend\PushSubscriptionController::class, 'store']);
        Route::delete('push/subscribe', [\App\Http\Controllers\Frontend\PushSubscriptionController::class, 'destroy']);

        // Affiliate / referral dashboard — shared by coach + student.
        Route::get('affiliate', [\App\Http\Controllers\Frontend\AffiliateController::class, 'index'])->name('affiliate.index');

        // Referral panel (new system — code, link, wallet stats, membership credit usage)
        Route::get('referral', [\App\Http\Controllers\Frontend\ReferralController::class, 'index'])->name('referral.index');

        // 2026-06-26 — announcement attachment download, shared by coach + student.
        // The controller method already does role-aware, tenant-scoped permission
        // checks (coach owns the announcement OR the student is enrolled AND the
        // announcement is visible to their batch), so it is safe for both. This
        // fixes students being redirected ("that section is for coaches") because
        // the only download route lived in the instructor-only group.
        Route::get('announcements/attachments/{attachment}/download', [InstructorAnnouncementController::class, 'downloadAttachment'])
            ->name('announcements.attachments.download');

        // Membership — shared by coach + student. Plan picker, checkout, history.
        Route::controller(\App\Http\Controllers\Frontend\MembershipController::class)
            ->prefix('membership')->name('membership.')->group(function () {
                Route::get('/',                  'index')->name('index');
                Route::get('checkout/{plan}',    'checkout')->name('checkout');
                Route::post('pay/{plan}',        'pay')->name('pay');
            });

        // Razorpay checkout for membership pending-payment rows.
        Route::controller(\App\Http\Controllers\Frontend\MembershipPaymentController::class)
            ->prefix('membership/pay')->name('membership.razorpay.')->group(function () {
                Route::get('{membership}/razorpay',           'showRazorpay')->name('show');
                Route::post('{membership}/razorpay/callback', 'razorpayCallback')->name('callback');
            });

        // Bell-icon notification endpoints — shared by coach + student dashboards.
        Route::controller(\App\Http\Controllers\Frontend\NotificationController::class)
            ->prefix('notifications')->name('notifications.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('recent', 'recent')->name('recent');
                Route::get('unread-count', 'unreadCount')->name('unread-count');
                Route::post('{id}/read', 'markRead')->name('mark-read');
                Route::post('read-all', 'markAllRead')->name('mark-all-read');
                Route::delete('{id}', 'destroy')->name('destroy');
            });

        // Per-user notification preferences (channels × events)
        Route::controller(\App\Http\Controllers\Frontend\NotificationPreferenceController::class)
            ->prefix('notifications/preferences')->name('notifications.preferences.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'update')->name('update');
            });

        /* 2FA — challenge endpoints (must be reachable AFTER login but BEFORE
           the 2fa:web middleware can pass). Session must be active.
           Throttle: 6-digit TOTP has only 10^6 codes (3M with ±1 skew window),
           so unbounded attempts = trivial bruteforce once the password is known. */
        Route::controller(\App\Http\Controllers\Frontend\TwoFactorController::class)->group(function () {
            Route::get('2fa/challenge',           'showChallenge')->name('web.2fa.challenge');
            Route::post('2fa/challenge/verify',   'verifyChallenge')
                ->middleware('throttle:5,1')->name('web.2fa.challenge.verify');
            Route::post('2fa/challenge/recovery', 'useRecovery')
                ->middleware('throttle:5,1')->name('web.2fa.challenge.recovery');
        });

        /* 2FA — setup/management. Gated by 2fa:web so an enrolled user can't
           bypass the challenge by going directly to /2fa/setup. */
        Route::middleware('2fa:web')->controller(\App\Http\Controllers\Frontend\TwoFactorController::class)->group(function () {
            Route::get('2fa/setup',      'showSetup')->name('web.2fa.setup');
            Route::post('2fa/enable',    'enable')->middleware('throttle:10,1')->name('web.2fa.enable');
            Route::post('2fa/confirm',   'confirm')->middleware('throttle:5,1')->name('web.2fa.confirm');
            Route::post('2fa/disable',   'disable')->middleware('throttle:5,1')->name('web.2fa.disable');
            Route::post('2fa/regenerate','regenerateRecovery')->middleware('throttle:5,1')->name('web.2fa.regenerate');
        });
    });
});

// maintenance mode route
Route::get('/maintenance-mode', function () {
    $setting = Illuminate\Support\Facades\Cache::get('setting', null);
    if (! $setting?->maintenance_mode) {
        return redirect()->route('home');
    }

    return view('global.maintenance');
})->name('maintenance.mode');

require __DIR__.'/auth.php';

require __DIR__.'/admin.php';

/*
|--------------------------------------------------------------------------
| White-Label custom-domain fallback (G1b, 2026-06-04)
|--------------------------------------------------------------------------
| Runs ONLY when no other route matched. On a VERIFIED coach custom domain
| (resolved_coach_id stamped by ResolveCoachByDomain) it serves the coach's
| marketing sub-pages at the root (client.com/about, /contact, ...). On the
| platform's own domain there is no stamp, so it aborts 404 — exactly the
| behaviour an unmatched route had before. Single-segment slugs only.
*/
Route::fallback(function () {
    $coachId = (int) request()->attributes->get('resolved_coach_id');
    $slug    = trim(request()->path(), '/');
    // F20 (audit 2026-06-26) — never treat a reserved SYSTEM slug as a coach
    // page on a custom domain. These are app/auth/commerce/payment-return routes;
    // if one reaches the fallback (unmatched) it must 404, not render a coach
    // page. Mirrors the subdomain route's reserved-slug exclusion.
    $reserved = [
        'login', 'register', 'logout', 'forgot-password', 'reset-password',
        'cart', 'checkout', 'dashboard', 'courses', 'blog',
        'membership', 'referral', 'affiliate', 'notifications',
        'payment', 'payment-success', 'payment-failed',
    ];
    $isReserved = in_array($slug, $reserved, true) || str_starts_with($slug, 'pay-via-');
    if ($coachId > 0 && $slug !== '' && ! str_contains($slug, '/') && ! $isReserved) {
        return app(\App\Http\Controllers\Frontend\CoachSitePublicController::class)->showOnDomain($slug);
    }
    abort(404);
});
