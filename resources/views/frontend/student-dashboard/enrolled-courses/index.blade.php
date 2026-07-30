@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
    {{--
        Student enrolled courses — corporate redesign (2026-05).

        Same data shape, same routes. Inter font, brand-aware per
        coach via $brand (auto-injected). Replaces the previous
        purple-themed layout (#6e69f7 hardcoded, lavender card
        background #f9eaff, Plus Jakarta Sans) with the platform
        corp design tokens.

        Functional contract preserved 1:1:
          * $enrolls paginator with course relation
          * Per-course lecture count + completion percentage from
            CourseChapterItem + CourseProgress
          * Status branching: Completed / In progress / Not started
          * Route per state:
              - Completed   → student.download-certificate
              - In progress → student.learning.index (Continue)
              - Not started → student.learning.index (Start)
              - All         → student.learning.my-attendance
          * Pagination via $enrolls->links()
          * minutesToHours() + asset() helpers
    --}}

    @php
        // Stats computed once over the paginator — same logic as legacy.
        $totalEnrolled  = $enrolls->total();
        $totalCompleted = 0;
        $totalMinutes   = 0;
        $totalPct       = 0;
        $enrollCount    = $enrolls->count();
        foreach ($enrolls as $e) {
            $lCount = App\Models\CourseChapterItem::whereHas('chapter', fn($q) => $q->where('course_id', $e->course->id))->count();
            $lDone  = App\Models\CourseProgress::where('user_id', userAuth()->id)
                ->where('course_id', $e->course->id)
                ->where('watched', 1)->count();
            $pct = $lCount > 0 ? ($lDone / $lCount) * 100 : 0;
            if ($pct >= 100) $totalCompleted++;
            $totalPct     += $pct;
            $totalMinutes += $e->course->duration ?? 0;
        }
        $avgPct = $enrollCount > 0 ? $totalPct / $enrollCount : 0;
    @endphp

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    <style>
        .ec-page {
            --ec-brand:       {{ $brand->primaryColor ?: '#10b981' }};
            --ec-brand-2:     {{ $brand->accentColor  ?: '#059669' }};
            --ec-text:        #0b1220;
            --ec-text-2:      #1f2937;
            --ec-muted:       #6b7280;
            --ec-subtle:      #9ca3af;
            --ec-line:        #e5e7eb;
            --ec-line-soft:   #f1f3f5;
            --ec-bg:          #fafbfc;
            --ec-card:        #ffffff;
            --ec-shadow-sm:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 2px 6px -2px rgba(15, 23, 42, 0.04);
            --ec-shadow-md:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 6px 18px -6px rgba(15, 23, 42, 0.08),
                0 12px 36px -12px rgba(15, 23, 42, 0.08);
            --ec-ease: cubic-bezier(.22, 1, .36, 1);

            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            padding: 4px 8px 28px 0;       /* right gutter clears the floating top-right toolbar */
        }
        @media (max-width: 768px) { .ec-page { padding: 4px 0 20px; } }
        .ec-page *,
        .ec-page *::before,
        .ec-page *::after { box-sizing: border-box; }
        .ec-page .ec-tabular {
            font-variant-numeric: tabular-nums; font-feature-settings: 'tnum';
        }

        /* Page header */
        .ec-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            gap: 16px; flex-wrap: wrap; margin-bottom: 22px;
        }
        .ec-eyebrow {
            font-size: 11px; font-weight: 700; color: var(--ec-brand);
            text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 6px;
        }
        .ec-header h1 {
            font-size: 24px; font-weight: 800; color: var(--ec-text);
            margin: 0 0 4px; letter-spacing: -0.028em; line-height: 1.2;
        }
        .ec-header__sub {
            font-size: 13.5px; color: var(--ec-muted); margin: 0; line-height: 1.5;
        }

        /* KPI grid */
        .ec-kpi-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 14px; margin-bottom: 24px;
        }
        @media (max-width: 1100px) { .ec-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px)  { .ec-kpi-grid { grid-template-columns: 1fr; } }

        .ec-kpi {
            background: var(--ec-card); border: 1px solid var(--ec-line);
            border-radius: 14px; padding: 16px 18px;
            box-shadow: var(--ec-shadow-sm);
            display: flex; flex-direction: column; gap: 4px;
            position: relative;
        }
        .ec-kpi::before {
            content: ''; position: absolute; top: 0; left: 16px; right: 16px;
            height: 2px; border-radius: 0 0 2px 2px;
            background: var(--ec-accent, var(--ec-brand)); opacity: 0.85;
        }
        .ec-kpi__head {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;
        }
        .ec-kpi__label {
            font-size: 11px; font-weight: 700; color: var(--ec-muted);
            text-transform: uppercase; letter-spacing: 0.08em;
        }
        .ec-kpi__icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: color-mix(in srgb, var(--ec-accent, var(--ec-brand)) 12%, #fff);
            color: var(--ec-accent, var(--ec-brand));
            display: flex; align-items: center; justify-content: center; font-size: 13px;
            border: 1px solid color-mix(in srgb, var(--ec-accent, var(--ec-brand)) 18%, transparent);
        }
        .ec-kpi__value {
            font-size: 24px; font-weight: 800; color: var(--ec-text);
            line-height: 1.1; letter-spacing: -0.025em;
        }
        .ec-kpi__sub {
            font-size: 11.5px; color: var(--ec-muted); font-weight: 500;
        }

        /* Section head */
        .ec-section-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; margin-bottom: 14px;
        }
        .ec-section-head__title {
            display: flex; align-items: center; gap: 8px;
            font-size: 15px; font-weight: 700; color: var(--ec-text);
            letter-spacing: -0.015em;
        }
        .ec-section-head__title i { color: var(--ec-brand); font-size: 13px; }
        .ec-section-head__count {
            font-size: 11.5px; color: var(--ec-muted); font-weight: 500;
        }

        /* Course list */
        .ec-list {
            display: flex; flex-direction: column; gap: 14px;
        }

        .ec-card {
            background: var(--ec-card); border: 1px solid var(--ec-line);
            border-radius: 14px; overflow: hidden;
            display: flex; align-items: stretch;
            box-shadow: var(--ec-shadow-sm);
            transition: transform .15s var(--ec-ease),
                        box-shadow .15s var(--ec-ease),
                        border-color .15s var(--ec-ease);
        }
        .ec-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--ec-shadow-md);
            border-color: color-mix(in srgb, var(--ec-state, var(--ec-brand)) 35%, var(--ec-line));
        }
        .ec-card::before {
            content: ''; width: 3px; background: var(--ec-state, var(--ec-brand)); flex-shrink: 0;
        }

        /* Thumbnail */
        .ec-thumb {
            position: relative; width: 200px; min-width: 200px;
            overflow: hidden; flex-shrink: 0;
            background: var(--ec-line-soft);
        }
        .ec-thumb img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform .35s var(--ec-ease);
        }
        .ec-card:hover .ec-thumb img { transform: scale(1.04); }
        .ec-thumb__dim {
            position: absolute; inset: 0;
            background: linear-gradient(180deg, transparent 50%, rgba(11, 18, 32, 0.45) 100%);
        }
        .ec-thumb__badge {
            position: absolute; top: 10px; left: 10px;
            font-size: 10.5px; font-weight: 700;
            padding: 3px 10px; border-radius: 999px;
            letter-spacing: 0.04em; text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        .ec-thumb__badge--done    { background: rgba(16, 185, 129, 0.95); color: #fff; }
        .ec-thumb__badge--active  { background: color-mix(in srgb, var(--ec-brand) 92%, transparent); color: #fff; }
        .ec-thumb__badge--new     { background: rgba(245, 158, 11, 0.95); color: #fff; }
        .ec-thumb__play {
            position: absolute; bottom: 10px; right: 10px;
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            display: flex; align-items: center; justify-content: center;
            color: var(--ec-brand); font-size: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        /* Body */
        .ec-body {
            flex: 1; padding: 16px 20px; min-width: 0;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .ec-top-line {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 12px; margin-bottom: 8px;
        }
        .ec-cat {
            font-size: 10px; font-weight: 700; letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 3px 9px; border-radius: 6px;
            background: var(--ec-line-soft); color: var(--ec-muted);
            white-space: nowrap;
        }
        .ec-rating {
            display: flex; align-items: center; gap: 4px;
            font-size: 11.5px; font-weight: 700; color: #92400e;
            background: #fffbeb; padding: 3px 10px; border-radius: 999px;
            border: 1px solid #fde68a;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .ec-rating i { color: #f59e0b; font-size: 10px; }

        .ec-title {
            font-size: 14.5px; font-weight: 700; color: var(--ec-text);
            line-height: 1.4; margin-bottom: 10px;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden; letter-spacing: -0.01em;
        }
        .ec-title a { color: inherit; text-decoration: none; }
        .ec-title a:hover { color: var(--ec-brand); }

        .ec-instr {
            display: flex; align-items: center; gap: 8px; margin-bottom: 14px;
            font-size: 12px; color: var(--ec-muted);
        }
        .ec-instr__avatar {
            width: 24px; height: 24px; border-radius: 50%; object-fit: cover;
            border: 1px solid var(--ec-line);
        }
        .ec-instr__name { font-weight: 600; color: var(--ec-text-2); }
        .ec-instr__dot {
            width: 3px; height: 3px; border-radius: 50%;
            background: var(--ec-line); display: inline-block;
        }

        /* Progress */
        .ec-prog { margin-bottom: 12px; }
        .ec-prog__head {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 5px;
        }
        .ec-prog__label {
            font-size: 11.5px; font-weight: 600; color: var(--ec-muted);
            display: flex; align-items: center; gap: 6px;
        }
        .ec-prog__pip {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--ec-state, var(--ec-brand));
        }
        .ec-prog__pct {
            font-size: 12.5px; font-weight: 700; color: var(--ec-text);
        }
        .ec-prog__track {
            height: 5px; background: var(--ec-line-soft);
            border-radius: 999px; overflow: hidden;
        }
        .ec-prog__bar {
            height: 100%; border-radius: 999px;
            background: var(--ec-state, var(--ec-brand));
            transition: width .6s var(--ec-ease);
        }

        /* Footer */
        .ec-footer {
            display: flex; align-items: center; gap: 14px;
            padding-top: 12px; border-top: 1px solid var(--ec-line-soft);
            flex-wrap: wrap;
        }
        .ec-meta {
            display: flex; align-items: center; gap: 14px;
            flex: 1; min-width: 0;
        }
        .ec-meta__item {
            display: flex; align-items: center; gap: 5px;
            font-size: 11.5px; color: var(--ec-muted); font-weight: 500;
        }
        .ec-meta__item i { font-size: 11px; color: var(--ec-subtle); }

        .ec-actions {
            display: flex; align-items: center; gap: 6px;
            flex-wrap: wrap;
        }
        .ec-btn {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11.5px; font-weight: 600;
            padding: 7px 13px; border-radius: 8px;
            text-decoration: none; white-space: nowrap;
            transition: all .15s var(--ec-ease);
        }
        .ec-btn--primary {
            background: var(--ec-brand); color: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }
        .ec-btn--primary:hover {
            background: color-mix(in srgb, var(--ec-brand) 88%, #000);
            color: #fff;
            text-decoration: none;
        }
        .ec-btn--ghost {
            background: color-mix(in srgb, var(--ec-brand) 8%, #fff);
            color: var(--ec-brand);
            border: 1px solid color-mix(in srgb, var(--ec-brand) 22%, transparent);
        }
        .ec-btn--ghost:hover {
            background: color-mix(in srgb, var(--ec-brand) 14%, #fff);
            color: var(--ec-brand); text-decoration: none;
        }
        .ec-btn--success {
            background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;
        }
        .ec-btn--success:hover {
            background: #d1fae5; color: #065f46; text-decoration: none;
        }
        .ec-btn--start {
            background: #fffbeb; color: #92400e; border: 1px solid #fde68a;
        }
        .ec-btn--start:hover {
            background: #fef3c7; color: #78350f; text-decoration: none;
        }
        .ec-btn i { font-size: 10px; }

        /* Mobile */
        @media (max-width: 640px) {
            .ec-card { flex-direction: column; }
            .ec-thumb { width: 100%; min-width: unset; height: 180px; }
            .ec-card::before { width: 100%; height: 3px; }
        }

        /* Empty */
        .ec-empty {
            text-align: center; padding: 56px 20px;
            background: var(--ec-card); border: 1px solid var(--ec-line);
            border-radius: 14px; box-shadow: var(--ec-shadow-sm);
        }
        .ec-empty__icon {
            width: 56px; height: 56px; margin: 0 auto 14px;
            border-radius: 14px;
            background: color-mix(in srgb, var(--ec-brand) 10%, #fff);
            color: var(--ec-brand);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            border: 1px solid color-mix(in srgb, var(--ec-brand) 18%, transparent);
        }
        .ec-empty__title {
            font-size: 16px; font-weight: 700; color: var(--ec-text);
            margin-bottom: 6px; letter-spacing: -0.015em;
        }
        .ec-empty__text {
            font-size: 13.5px; color: var(--ec-muted); margin-bottom: 16px;
            line-height: 1.5;
        }
        .ec-empty__cta {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 18px; border-radius: 9px;
            background: var(--ec-brand); color: #fff;
            font-size: 13px; font-weight: 600; text-decoration: none;
        }
        .ec-empty__cta:hover {
            background: color-mix(in srgb, var(--ec-brand) 88%, #000); color: #fff;
        }

        /* Pagination */
        .ec-pagination {
            margin-top: 22px; display: flex; justify-content: center;
        }
        .ec-pagination nav { display: flex; }
        .ec-pagination .pagination { gap: 4px; margin: 0; }
        .ec-pagination .page-item .page-link {
            background: var(--ec-card);
            border: 1px solid var(--ec-line);
            color: var(--ec-text-2);
            border-radius: 8px !important;
            padding: 7px 13px; font-size: 12.5px; font-weight: 600;
            font-family: inherit;
            transition: all .15s var(--ec-ease);
        }
        .ec-pagination .page-item .page-link:hover {
            border-color: var(--ec-brand);
            color: var(--ec-brand);
        }
        .ec-pagination .page-item.active .page-link {
            background: var(--ec-brand);
            border-color: var(--ec-brand);
            color: #fff;
        }
        .ec-pagination .page-item.disabled .page-link {
            color: var(--ec-subtle);
            background: var(--ec-line-soft);
        }
    </style>

    <style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] .ec-page {
        --ec-text:      #e2e8f0;
        --ec-text-2:    #cbd5e1;
        --ec-muted:     #94a3b8;
        --ec-subtle:    #64748b;
        --ec-line:      #2a3a55;
        --ec-line-soft: #22304a;
        --ec-bg:        #17233a;
        --ec-card:      #1e293b;
        --ec-shadow-sm: none;
        --ec-shadow-md: none;
    }
    </style>

    <div class="ec-page">

        {{-- Header --}}
        <header class="ec-header">
            <div>
                <div class="ec-eyebrow">{{ __('My Learning') }}</div>
                <h1>{{ __('Enrolled courses') }}</h1>
                <p class="ec-header__sub">
                    {{ __('Pick up where you left off, download certificates, and track your attendance.') }}
                </p>
            </div>
        </header>

        {{-- KPI grid --}}
        <div class="ec-kpi-grid">
            <div class="ec-kpi" style="--ec-accent: var(--ec-brand);">
                <div class="ec-kpi__head">
                    <span class="ec-kpi__label">{{ __('Enrolled') }}</span>
                    <span class="ec-kpi__icon"><i class="fas fa-book-open"></i></span>
                </div>
                <div class="ec-kpi__value ec-tabular">{{ $totalEnrolled }}</div>
                <div class="ec-kpi__sub">{{ __('total courses') }}</div>
            </div>
            <div class="ec-kpi" style="--ec-accent: #10b981;">
                <div class="ec-kpi__head">
                    <span class="ec-kpi__label">{{ __('Completed') }}</span>
                    <span class="ec-kpi__icon"><i class="fas fa-trophy"></i></span>
                </div>
                <div class="ec-kpi__value ec-tabular">{{ $totalCompleted }}</div>
                <div class="ec-kpi__sub">{{ __('certificates earned') }}</div>
            </div>
            <div class="ec-kpi" style="--ec-accent: #f59e0b;">
                <div class="ec-kpi__head">
                    <span class="ec-kpi__label">{{ __('Watch time') }}</span>
                    <span class="ec-kpi__icon"><i class="fas fa-clock"></i></span>
                </div>
                <div class="ec-kpi__value ec-tabular">{{ minutesToHours($totalMinutes) }}</div>
                <div class="ec-kpi__sub">{{ __('total content') }}</div>
            </div>
            <div class="ec-kpi" style="--ec-accent: #3b82f6;">
                <div class="ec-kpi__head">
                    <span class="ec-kpi__label">{{ __('Avg progress') }}</span>
                    <span class="ec-kpi__icon"><i class="fas fa-chart-line"></i></span>
                </div>
                <div class="ec-kpi__value ec-tabular">{{ number_format($avgPct, 0) }}%</div>
                <div class="ec-kpi__sub">{{ __('across all courses') }}</div>
            </div>
        </div>

        {{-- ─────────────────────────────────────────────────────────────
             Payment-pending courses (2026-06-12).
             A coach-assigned (or self-checkout) course creates a PENDING order
             with NO access. We surface it here immediately with a "Complete
             Payment" CTA so the student doesn't have to hunt through Order
             History. Content stays LOCKED — no learning link, and
             LearningController gates on a has_access enrollment that only
             markPaid creates. Driven by the student's own pending orders →
             works for every coach. --}}
        @if (!empty($pendingCourses) && count($pendingCourses) > 0)
            <div class="ec-section-head">
                <span class="ec-section-head__title">
                    <i class="fas fa-lock" style="color:#f59e0b;"></i>
                    {{ __('Awaiting payment') }}
                </span>
                <span class="ec-section-head__count ec-tabular">
                    {{ count($pendingCourses) }} {{ __('locked') }}
                </span>
            </div>

            <div class="ec-list" style="margin-bottom:26px;">
                @foreach ($pendingCourses as $p)
                    @php
                        $pc = $p->course;
                        $curIcon = \Illuminate\Support\Facades\Session::get('currency_icon', '$');
                    @endphp
                    <div class="ec-card" style="--ec-state: #f59e0b;">

                        {{-- Thumb (locked — no learning link) --}}
                        <div class="ec-thumb">
                            <img src="{{ asset($pc->thumbnail) }}" alt="{{ $pc->title }}">
                            <div class="ec-thumb__dim"></div>
                            <span class="ec-thumb__badge ec-thumb__badge--new">{{ __('Payment pending') }}</span>
                            <span class="ec-thumb__play" style="background:rgba(245,158,11,.92);">
                                <i class="fas fa-lock"></i>
                            </span>
                        </div>

                        {{-- Body --}}
                        <div class="ec-body">
                            <div>
                                <div class="ec-top-line">
                                    <span class="ec-cat">
                                        {{ optional(optional($pc->category)->translation)->name ?? __('Course') }}
                                    </span>
                                </div>

                                <div class="ec-title">
                                    <span>{{ $pc->title }}</span>
                                </div>

                                @if ($pc->instructor)
                                    <div class="ec-instr">
                                        <img class="ec-instr__avatar"
                                             src="{{ asset($pc->instructor->image) }}"
                                             alt="{{ $pc->instructor->name }}">
                                        <span class="ec-instr__name">{{ $pc->instructor->name }}</span>
                                    </div>
                                @endif

                                <div style="font-size:12px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 11px;margin-top:10px;display:flex;align-items:center;gap:7px;line-height:1.4;">
                                    <i class="fas fa-circle-info"></i>
                                    {{ __('Complete the payment to unlock the full course content.') }}
                                </div>
                            </div>

                            <div class="ec-footer">
                                <div class="ec-meta">
                                    <div class="ec-meta__item">
                                        <i class="fas fa-tag"></i>
                                        <span class="ec-tabular">{{ $curIcon }}{{ number_format((float) $p->price, 0) }}</span>
                                    </div>
                                </div>

                                <div class="ec-actions">
                                    <a class="ec-btn ec-btn--primary"
                                       href="{{ route('payment', ['invoice_id' => $p->invoice_id]) }}">
                                        <i class="fas fa-lock-open"></i>
                                        {{ __('Complete Payment') }}
                                    </a>
                                    <a class="ec-btn ec-btn--ghost"
                                       href="{{ route('student.order.show', $p->order_id) }}">
                                        <i class="fas fa-receipt"></i>
                                        {{ __('Order') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>
        @endif

        {{-- Section head --}}
        <div class="ec-section-head">
            <span class="ec-section-head__title">
                <i class="fas fa-book-open"></i>
                {{ __('Your courses') }}
            </span>
            @if ($totalEnrolled > 0)
                <span class="ec-section-head__count ec-tabular">
                    {{ __('Showing') }} {{ $enrolls->firstItem() }}–{{ $enrolls->lastItem() }} {{ __('of') }} {{ $totalEnrolled }}
                </span>
            @endif
        </div>

        {{-- Course list --}}
        <div class="ec-list">
            @forelse ($enrolls as $enroll)
                @php
                    $lectureCount = App\Models\CourseChapterItem::whereHas(
                        'chapter', fn($q) => $q->where('course_id', $enroll->course->id))->count();
                    $lectureDone  = App\Models\CourseProgress::where('user_id', userAuth()->id)
                        ->where('course_id', $enroll->course->id)
                        ->where('watched', 1)->count();
                    $pct = $lectureCount > 0 ? ($lectureDone / $lectureCount) * 100 : 0;

                    $isCompleted  = $pct >= 100;
                    $isInProgress = !$isCompleted && $lectureDone > 0;

                    // State drives both the accent stripe and the badge style.
                    $stateColor = $isCompleted ? '#10b981' : ($isInProgress ? 'var(--ec-brand)' : '#f59e0b');
                    $badgeCls   = $isCompleted ? 'ec-thumb__badge--done' : ($isInProgress ? 'ec-thumb__badge--active' : 'ec-thumb__badge--new');
                    $statusLabel = $isCompleted ? __('Completed') : ($isInProgress ? __('In progress') : __('Not started'));
                    $avgRating   = number_format($enroll->course->reviews()->avg('rating') ?? 0, 1);
                @endphp

                <div class="ec-card" style="--ec-state: {{ $stateColor }};">

                    {{-- Thumb --}}
                    <div class="ec-thumb">
                        <a href="{{ route('student.learning.index', $enroll->course->slug) }}">
                            <img src="{{ asset($enroll->course->thumbnail) }}" alt="{{ $enroll->course->title }}">
                            <div class="ec-thumb__dim"></div>
                            <span class="ec-thumb__badge {{ $badgeCls }}">{{ $statusLabel }}</span>
                            <span class="ec-thumb__play"><i class="fas fa-play"></i></span>
                        </a>
                    </div>

                    {{-- Body --}}
                    <div class="ec-body">
                        <div>
                            <div class="ec-top-line">
                                <span class="ec-cat">
                                    {{ $enroll->course->category->translation->name ?? __('Course') }}
                                </span>
                                <span class="ec-rating">
                                    <i class="fas fa-star"></i> {{ $avgRating }}
                                </span>
                            </div>

                            <div class="ec-title">
                                <a href="{{ route('student.learning.index', $enroll->course->slug) }}">
                                    {{ $enroll->course->title }}
                                </a>
                            </div>

                            <div class="ec-instr">
                                <img class="ec-instr__avatar"
                                     src="{{ asset($enroll->course->instructor->image) }}"
                                     alt="{{ $enroll->course->instructor->name }}">
                                <span class="ec-instr__name">{{ $enroll->course->instructor->name }}</span>
                                <span class="ec-instr__dot"></span>
                                <span class="ec-tabular">
                                    {{ $enroll->course->enrollments()->count() }} {{ __('students') }}
                                </span>
                            </div>

                            <div class="ec-prog">
                                <div class="ec-prog__head">
                                    <span class="ec-prog__label">
                                        <span class="ec-prog__pip"></span>
                                        {{ $statusLabel }}
                                    </span>
                                    <span class="ec-prog__pct ec-tabular">{{ number_format($pct, 0) }}%</span>
                                </div>
                                <div class="ec-prog__track">
                                    <div class="ec-prog__bar" style="width: {{ number_format($pct, 1) }}%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="ec-footer">
                            <div class="ec-meta">
                                <div class="ec-meta__item">
                                    <i class="fas fa-book"></i>
                                    <span class="ec-tabular">{{ $lectureCount }}</span>
                                    {{ __('lessons') }}
                                </div>
                                <div class="ec-meta__item">
                                    <i class="fas fa-clock"></i>
                                    <span class="ec-tabular">{{ minutesToHours($enroll->course->duration) }}</span>
                                </div>
                            </div>

                            <div class="ec-actions">
                                @if ($isCompleted)
                                    <a class="ec-btn ec-btn--success"
                                       href="{{ route('student.download-certificate', $enroll->course->id) }}">
                                        <i class="fas fa-download"></i>
                                        {{ __('Certificate') }}
                                    </a>
                                @elseif ($isInProgress)
                                    <a class="ec-btn ec-btn--primary"
                                       href="{{ route('student.learning.index', $enroll->course->slug) }}">
                                        {{ __('Continue') }} <i class="fas fa-arrow-right"></i>
                                    </a>
                                @else
                                    <a class="ec-btn ec-btn--start"
                                       href="{{ route('student.learning.index', $enroll->course->slug) }}">
                                        {{ __('Start course') }} <i class="fas fa-arrow-right"></i>
                                    </a>
                                @endif
                                <a class="ec-btn ec-btn--ghost"
                                   href="{{ route('student.learning.my-attendance', $enroll->course->slug) }}">
                                    <i class="fas fa-clipboard-check"></i>
                                    {{ __('Attendance') }}
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            @empty
                <div class="ec-empty">
                    <div class="ec-empty__icon"><i class="fas fa-book-open"></i></div>
                    <div class="ec-empty__title">{{ __("You haven't enrolled in any courses yet") }}</div>
                    <div class="ec-empty__text">{{ __('Browse the catalogue to start your learning journey today.') }}</div>
                    <a class="ec-empty__cta" href="{{ url('/courses') }}">
                        <i class="fas fa-search" style="font-size:11px;"></i>
                        {{ __('Browse courses') }}
                    </a>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if ($enrolls->hasPages())
            <div class="ec-pagination">
                {{ $enrolls->links() }}
            </div>
        @endif

    </div>
@endsection
