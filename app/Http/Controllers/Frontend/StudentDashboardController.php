<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseChapterItem;
use App\Models\CourseProgress;
use App\Models\CourseReview;
use App\Models\QuizResult;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Modules\CertificateBuilder\app\Models\CertificateBuilder;
use Modules\CertificateBuilder\app\Models\CertificateBuilderItem;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;

class StudentDashboardController extends Controller {

    function test(): View { 
        return view('frontend.student-dashboard.zoom.test'); 

        // return view('frontend.instructor-dashboard.zoom.test');
    }


    public function index(): View {
        $userId = userAuth()->id;

        // 2026-06-10 — TENANT SCOPE: on a coach custom domain the full student
        // panel renders here, so every figure must be limited to THIS coach's
        // courses (instructor_id). resolved_coach_id is 0/null on the platform
        // domain → the when() closures are no-ops and behaviour is unchanged.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        $scopeCourse   = fn ($q) => $q->when($tenantCoachId > 0, fn ($x) =>
            $x->whereHas('course', fn ($c) => $c->where('instructor_id', $tenantCoachId)));
        $scopeQuiz     = fn ($q) => $q->when($tenantCoachId > 0, fn ($x) =>
            $x->whereHas('quiz.course', fn ($c) => $c->where('instructor_id', $tenantCoachId)));
        $scopeOrder    = fn ($q) => $q->when($tenantCoachId > 0, fn ($x) =>
            $x->whereHas('orderItems.course', fn ($c) => $c->where('instructor_id', $tenantCoachId)));

        // Cache key includes the coach id so the platform view and each coach
        // domain keep separate cached stats (otherwise one would poison another).
        $stats = Cache::remember("student.dashboard.stats:{$userId}:{$tenantCoachId}", 60, function () use ($userId, $scopeCourse, $scopeQuiz, $scopeOrder) {
            return [
                'totalEnrolledCourses' => Enrollment::where('user_id', $userId)->tap($scopeCourse)->count(),
                'totalQuizAttempts'    => QuizResult::where('user_id', $userId)->tap($scopeQuiz)->count(),
                'totalReviews'         => CourseReview::where('user_id', $userId)->tap($scopeCourse)->count(),
                'totalOrders'          => Order::where('buyer_id', $userId)->tap($scopeOrder)->count(),
            ];
        });

        $orders = Order::where('buyer_id', $userId)->tap($scopeOrder)->orderBy('id', 'desc')->take(10)->get();

        // "Resume learning" — most recent in-progress course based on CourseProgress.
        $resume = CourseProgress::where('user_id', $userId)
            ->where('current', 1)
            ->tap($scopeCourse)
            ->with(['course' => fn($q) => $q->withTrashed()])
            ->orderByDesc('id')
            ->first();

        $resumePercent = 0;
        if ($resume && $resume->course) {
            $totalLectures = CourseChapterItem::whereHas('chapter', fn($q) => $q->where('course_id', $resume->course_id))->count();
            $watched = CourseProgress::where('user_id', $userId)
                ->where('course_id', $resume->course_id)
                ->where('watched', 1)
                ->count();
            $resumePercent = $totalLectures > 0 ? (int) round(($watched / $totalLectures) * 100) : 0;
        }

        // 2026-05-20 — top 3 latest unread announcements for the
        // dashboard widget. Visibility scoped via the model scope so
        // global + batch + course-wide announcements all flow through.
        $widgetAnnouncements = \App\Models\Announcement::visibleToStudent(userAuth())
            ->when($tenantCoachId > 0, fn ($q) => $q->where('instructor_id', $tenantCoachId))
            ->with(['instructor:id,name', 'course:id,title'])
            ->whereDoesntHave('readers', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('sent_at')
            ->limit(3)
            ->get();

        return view('frontend.student-dashboard.index', array_merge($stats, [
            'orders'              => $orders,
            'resume'              => $resume,
            'resumePercent'       => $resumePercent,
            'widgetAnnouncements' => $widgetAnnouncements,
        ]));
    }

    function enrolledCourses() {
        $userId = userAuth()->id;
        // 2026-06-10 — TENANT SCOPE: only THIS coach's courses on a coach domain.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        $enrolls = Enrollment::with(['course' => function ($q) {
            $q->withTrashed();
        }])->where(['user_id'=> $userId,'has_access'=>1])
            ->when($tenantCoachId > 0, fn ($q) =>
                $q->whereHas('course', fn ($c) => $c->where('instructor_id', $tenantCoachId)))
            ->orderByDesc('id')->paginate(3);

        // 2026-06-12 — PAYMENT-PENDING courses visible up-front.
        // When a coach assigns a course (or a student starts a checkout) a
        // PENDING order is created with NO access yet (no has_access enrollment
        // until markPaid). Previously the course was invisible until paid, so
        // the student had to dig through Order History to pay. Now we surface
        // those courses here with a "Complete Payment" CTA. Content stays
        // LOCKED — LearningController::index firstOrFail()s on a has_access
        // enrollment, so no order = no content. Driven entirely by the
        // student's OWN pending orders + tenant scope → global for every coach,
        // no hardcoding.
        $accessibleCourseIds = Enrollment::where('user_id', $userId)
            ->where('has_access', 1)->pluck('course_id')->all();

        $pendingCourses = collect();
        Order::with(['orderItems.course' => fn ($q) => $q->withTrashed()])
            ->where('buyer_id', $userId)
            ->where('status', 'pending')
            ->where('payment_status', 'pending')
            ->when($tenantCoachId > 0, fn ($q) =>
                $q->whereHas('orderItems.course', fn ($c) => $c->where('instructor_id', $tenantCoachId)))
            ->orderByDesc('id')
            ->get()
            ->each(function ($order) use ($pendingCourses, $accessibleCourseIds, $tenantCoachId) {
                foreach ($order->orderItems as $item) {
                    $course = $item->course;
                    if (! $course) continue;                                   // course deleted
                    if (in_array($course->id, $accessibleCourseIds)) continue; // already unlocked elsewhere
                    if ($tenantCoachId > 0 && (int) $course->instructor_id !== $tenantCoachId) continue;
                    if ($pendingCourses->has($course->id)) continue;           // de-dup; keep most recent (orderByDesc)
                    $pendingCourses->put($course->id, (object) [
                        'course'     => $course,
                        'invoice_id' => $order->invoice_id,
                        'order_id'   => $order->id,
                        'price'      => $item->price,
                    ]);
                }
            });
        $pendingCourses = $pendingCourses->values();

        // return $enrolls;
        return view('frontend.student-dashboard.enrolled-courses.index', compact('enrolls', 'pendingCourses'));
    }

    function quizAttempts() {
        Session::forget('course_slug');
        // 2026-06-10 — TENANT SCOPE: only attempts on THIS coach's courses.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        $quizAttempts = QuizResult::with(['quiz'])->where('user_id', userAuth()->id)
            ->when($tenantCoachId > 0, fn ($q) =>
                $q->whereHas('quiz.course', fn ($c) => $c->where('instructor_id', $tenantCoachId)))
            ->orderByDesc('id')->paginate(10);

        return view('frontend.student-dashboard.quiz-attempts.index', compact('quizAttempts'));
    }

    function downloadCertificate(string $id) {
        // Must have an active enrollment to download a cert. Without this guard
        // a student can hit /download-certificate/<any-course-id> and as long
        // as they've completed *some* course they'd pass the % check below.
        Enrollment::where('user_id', userAuth()->id)
            ->where('course_id', $id)
            ->where('has_access', 1)
            ->firstOrFail();

        $course = Course::withTrashed()->findOrFail($id);
        // 2026-06-12 — per-coach branded certificate: use the OWNING coach's
        // template (course.instructor_id), falling back to the platform default.
        $certificate = CertificateBuilder::forCoach($course->instructor_id);
        $certificateItems = CertificateBuilderItem::forCoach($course->instructor_id);

        $courseLectureCount = CourseChapterItem::whereHas('chapter', function ($q) use ($course) {
            $q->where('course_id', $course->id);
        })->count();

        $courseLectureCompletedByUser = CourseProgress::where('user_id', userAuth()->id)
            ->where('course_id', $course->id)->where('watched', 1)->latest();

        $completed_date = formatDate($courseLectureCompletedByUser->first()?->created_at);

        $courseLectureCompletedByUser = CourseProgress::where('user_id', userAuth()->id)
            ->where('course_id', $course->id)->where('watched', 1)->count();

        $courseCompletedPercent = $courseLectureCount > 0 ? ($courseLectureCompletedByUser / $courseLectureCount) * 100 : 0;

        // #10 — Auto-certificate eligibility (2026-05-12). Two paths:
        //   (a) lessons-based: 100% of CourseChapterItem watched (legacy).
        //   (b) attendance-based: course has live classes AND the student's
        //       attendance percent ≥ course.attendance_threshold_percent.
        //       Important for cohorts where the "lessons" are live and the
        //       student never lands on the lesson-completion path.
        // Both gates honour the same threshold the watchlist + student
        // "my attendance" view use, so a student who sees themselves as
        // "on track" can claim their certificate.
        if (!$this->meetsCertificateRequirements($course, $courseCompletedPercent)) {
            return abort(404);
        }

        $studentName = userAuth()->name;
        $dateText = formatDate($completed_date);

        // 2026-07-08 — 'enterprise' (default) is the framed, sealed, verifiable
        // template; 'classic' preserves the legacy drag/background template for
        // coaches who customised it. Missing column ⇒ enterprise.
        $style = $certificate->certificate_style ?? 'enterprise';

        if ($style === 'classic') {
            $html = view('frontend.student-dashboard.certificate.index', compact('certificateItems', 'certificate'))->render();
            // Escape every interpolated value — they end up inside the PDF HTML and
            // Dompdf will execute embedded scripts / styles. Names + course titles
            // are user/admin-supplied and could contain `</td><script>...</script>`.
            $esc = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $html = str_replace('[student_name]', $esc($studentName), $html);
            $html = str_replace('[platform_name]', $esc(Cache::get('setting')->app_name ?? ''), $html);
            $html = str_replace('[course]', $esc($course->title), $html);
            $html = str_replace('[date]', $esc($dateText), $html);
            $html = str_replace('[instructor_name]', $esc($course->instructor?->name ?? 'Unknown'), $html);

            $dompdf = new Dompdf(['enable_remote' => true]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream('certificate.pdf');
            return redirect()->back();
        }

        // ── Enterprise (default) — verifiable credential + branded landscape PDF.
        $credential = \App\Models\CertificateCredential::issueFor((int) userAuth()->id, (int) $course->id, [
            'coach_id'     => $course->instructor_id,
            'student_name' => $studentName,
            'course_title' => $course->title,
            'coach_name'   => $course->instructor?->name,
        ]);

        $html = $this->renderEnterpriseCertificate($course, $certificate, $credential, $studentName, $dateText);

        $dompdf = new Dompdf(['enable_remote' => true, 'isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');    // certificates are landscape (fixes the old 930×600-on-portrait crop)
        $dompdf->render();
        $dompdf->stream('certificate.pdf');
        return redirect()->back();
    }

    /**
     * Build the enterprise certificate HTML: the owning coach's brand colour +
     * logo + signature, a verifiable credential id + QR, on a framed landscape.
     */
    private function renderEnterpriseCertificate($course, $certificate, $credential, string $studentName, string $dateText): string
    {
        $brand = app(\App\Services\BrandResolver::class)->forCoach((int) $course->instructor_id);
        // Accent = per-certificate override if the coach set one, else brand colour.
        $accent = ! empty($certificate->accent_color) ? $certificate->accent_color : ($brand->primaryColor ?? '#0f766e');
        $brandColor = $this->certSafeColor($accent);
        $brandName  = $brand->name ?: (Cache::get('setting')->app_name ?? 'Academy');
        $template   = in_array($certificate->certificate_template ?? 'classic', ['classic', 'modern', 'royal'], true)
            ? ($certificate->certificate_template ?? 'classic') : 'classic';

        // Typography & layout. Font key → DomPDF built-in family (so the exported
        // PDF renders exactly what the builder preview shows — no webfont fallback).
        $fontMap    = ['serif' => 'serif', 'sans' => 'sans-serif', 'mono' => 'monospace'];
        $fontKey    = in_array($certificate->font_family ?? 'serif', ['serif', 'sans', 'mono'], true)
            ? ($certificate->font_family ?? 'serif') : 'serif';
        $fontFamily = $fontMap[$fontKey];
        $textAlign  = in_array($certificate->text_align ?? 'center', ['center', 'left'], true)
            ? ($certificate->text_align ?? 'center') : 'center';
        // Paper: coach override, else the selected design's own default.
        $paperDefaults = ['classic' => '#faf7f0', 'modern' => '#ffffff', 'royal' => '#fbf6ea'];
        $paperColor = ! empty($certificate->paper_color) && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $certificate->paper_color)
            ? $certificate->paper_color : ($paperDefaults[$template] ?? '#faf7f0');

        // Only embed the coach's OWN logo (never leak the platform logo).
        $logoData      = ($brand->ownLogo && $brand->logoPath) ? $this->certFileToDataUri($brand->logoPath) : null;
        $signatureData = $certificate->signature ? $this->certFileToDataUri($certificate->signature) : null;

        $verifyUrl   = url('/verify-certificate/' . $credential->uid);
        $verifyLabel = preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/')) . '/verify';

        return view('frontend.student-dashboard.certificate.enterprise', [
            'template'       => $template,
            'brandColor'     => $brandColor,
            'goldColor'      => '#b08d4f',
            'paperColor'     => $paperColor,
            'fontFamily'     => $fontFamily,
            'textAlign'      => $textAlign,
            'brandName'      => $brandName,
            'logoData'       => $logoData,
            'signatureData'  => $signatureData,
            'studentName'    => $studentName,
            'courseTitle'    => $course->title,
            'dateText'       => $dateText,
            'instructorName' => $course->instructor?->name ?: $brandName,
            'credentialUid'  => $credential->uid,
            'verifyUrl'      => $verifyUrl,
            'verifyLabel'    => $verifyLabel,
            'qrData'         => $this->certQrDataUri($verifyUrl),
        ])->render();
    }

    private function certSafeColor(?string $c): string
    {
        $c = trim((string) $c);
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $c) ? $c : '#0f766e';
    }

    private function certFileToDataUri(?string $path): ?string
    {
        if (! $path) return null;
        try {
            $rel = \Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])
                ? (string) parse_url($path, PHP_URL_PATH)
                : $path;
            $abs = public_path(ltrim($rel, '/'));
            if (! is_file($abs)) return null;
            $mime = @mime_content_type($abs) ?: 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($abs));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function certQrDataUri(string $text): ?string
    {
        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(220, 1),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );
            $svg = (new \BaconQrCode\Writer($renderer))->writeString($text);
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Certificate eligibility gate (#10, 2026-05-12).
     *
     * Returns true if EITHER:
     *   (a) The student has watched 100% of CourseChapterItem rows
     *       in the course (legacy path — works for recorded-video
     *       courses), OR
     *   (b) The course has at least one live class AND the caller's
     *       attendance percent on those live classes meets the
     *       course's attendance_threshold_percent.
     *
     * Live-only cohort courses fail path (a) by construction (no
     * lesson rows to "watch"), so without path (b) those students
     * could never earn a certificate. Threshold=0 disables (b)
     * intentionally — instructor opted out of attendance-based
     * certification.
     */
    private function meetsCertificateRequirements(\App\Models\Course $course, float $lessonPct): bool
    {
        if ($lessonPct >= 100) {
            return true;
        }
        $threshold = (int) ($course->attendance_threshold_percent ?? 0);
        if ($threshold <= 0) {
            return false;
        }
        $userId   = (int) userAuth()->id;
        $courseId = (int) $course->id;

        $totalClasses = (int) \App\Models\CourseLiveClass::where('course_id', $courseId)->count();
        if ($totalClasses === 0) {
            return false;
        }
        $attended = (int) \Illuminate\Support\Facades\DB::table('live_class_attendances as a')
            ->join('course_live_classes as c', 'c.id', '=', 'a.course_live_class_id')
            ->where('c.course_id', $courseId)
            ->where('a.user_id', $userId)
            ->where('a.duration_seconds', '>=', 60)   // same 60s floor as watchlist + my-attendance
            ->distinct('a.course_live_class_id')
            ->count('a.course_live_class_id');

        $attendancePct = ($attended / $totalClasses) * 100;
        return $attendancePct >= $threshold;
    }

    /**
     * 2026-05-21 — multi-coach: student self-removes from a coach's roster.
     *
     * Soft removal — flips coach_student_links.status to 'removed' and
     * stamps removed_at. The student's enrollments / paid orders /
     * progress remain intact (course access doesn't depend on the
     * roster link). The coach simply stops seeing this student in
     * My Students / fee picker / analytics.
     *
     * If the student later buys another of this coach's courses, the
     * order-completion hook in PaymentFulfilmentService re-runs
     * CoachStudentLink::link() which reactivates the row — re-link
     * is automatic, no extra UI needed.
     *
     * Auth: caller must be the student themselves. The link must
     * belong to them — we can't let student A remove student B from
     * a coach's roster.
     */
    public function leaveCoach(\Illuminate\Http\Request $request, int $coachId): \Illuminate\Http\RedirectResponse
    {
        $studentId = (int) userAuth()->id;
        abort_unless(userAuth()->role === 'student', 403, 'Only students can self-remove from a coach.');

        $removed = \App\Models\CoachStudentLink::unlink($coachId, $studentId);

        if (! $removed) {
            return back()->with([
                'messege'    => __('You are not currently on that coach\'s roster.'),
                'alert-type' => 'info',
            ]);
        }

        return back()->with([
            'messege'    => __('You have been removed from that coach\'s roster.'),
            'alert-type' => 'success',
        ]);
    }
}
