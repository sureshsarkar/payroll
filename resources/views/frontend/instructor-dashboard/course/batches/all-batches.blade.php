@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    @include('frontend.instructor-dashboard.settings.partials._corporate')

    {{-- 2026-05-20 — rebuilt to match the corporate design system used
     across Teacher Batches, Permission Catalog, and the new Teacher
     Dashboard. The original .batch-card / .custom-table / .badge-day
     styling was inconsistent with everything else the user has
     seen since the corp-page rollout.

     The Add/Edit Batch MODAL (its own green-themed CSS block below)
     and the AJAX handlers at the bottom are preserved byte-identical
     — they work, and they're decoupled from the listing UI. --}}

    <style>
        /* Per-course section card spacing in the corporate skin. The
           generic .corp-form-card already handles colors, borders and
           inner padding; we just stack them with breathing room. */
        .cb-course-card {
            margin-bottom: 14px;
        }

        .cb-course-card__head-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .cb-course-card__count {
            margin-left: auto;
            font-size: 11px;
            font-weight: 600;
            color: var(--corp-muted);
            text-transform: none;
            letter-spacing: 0;
        }

        /* Schedule cell — two stacked time spans. */
        .cb-sched {
            font-weight: 600;
            color: var(--corp-text);
        }

        .cb-sched-sub {
            display: block;
            font-size: 11px;
            font-weight: 500;
            color: var(--corp-muted);
            margin-top: 2px;
        }

        .cb-days {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        /* Empty state inside a card with batches=0 */
        .cb-empty-row {
            padding: 24px;
            text-align: center;
            color: var(--corp-muted);
            font-size: 13px;
            font-style: italic;
        }

        /* Phone: collapse the table into stacked rows so a single batch
           fits in viewport. Same data, no horizontal scroll. */
        @media (max-width: 640px) {
            .cb-course-card .corp-table thead {
                display: none;
            }

            .cb-course-card .corp-table tbody tr {
                display: block;
                border-bottom: 1px solid var(--corp-line);
                padding: 6px 0;
            }

            .cb-course-card .corp-table tbody tr:last-child {
                border-bottom: none;
            }

            .cb-course-card .corp-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 14px;
                border-bottom: 1px solid var(--corp-line-soft);
                font-size: 12px;
            }

            .cb-course-card .corp-table tbody td:last-child {
                border-bottom: none;
            }

            .cb-course-card .corp-table tbody td::before {
                content: attr(data-label);
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .5px;
                color: var(--corp-muted);
            }

            .cb-course-card .cb-days {
                justify-content: flex-end;
            }
        }
    </style>


    {{-- ══════════════════════════════════════════════
     Add / Edit Batch Modal  –  Coach Panel
     Theme: Light Green  |  Font: DM Sans + Fraunces
     ══════════════════════════════════════════════ --}}

    <style>
        /* ─── CSS Variables ─────────────────────────────────── */
        :root {
            --g50: #f0faf4;
            --g100: #d6f5e3;
            --g200: #aeeac8;
            --g400: #4fbe80;
            --g500: #29a65c;
            --g600: #1f8a4a;
            --n50: #f8f9fa;
            --n100: #f1f3f5;
            --n200: #e4e8ec;
            --n400: #9ca3af;
            --n600: #4b5563;
            --n800: #1f2937;
            --shadow-xl: 0 20px 60px rgba(41, 166, 92, .18), 0 8px 24px rgba(0, 0, 0, .10);
            --tr: .22s cubic-bezier(.4, 0, .2, 1);
            --ff-body: 'DM Sans', sans-serif;
            --ff-display: 'Fraunces', serif;
        }

        /* ─── Modal wrapper ─────────────────────────────────── */
        #batchModal .modal-content {
            max-height: 90vh;
            display: flex;
            border: none;
            border-radius: 24px;
            /* overflow-y: scroll; */
            box-shadow: var(--shadow-xl);
            font-family: var(--ff-body);
            animation: batchSlideUp .38s cubic-bezier(.22, 1, .36, 1) both;
        }

        @keyframes batchSlideUp {
            from {
                opacity: 0;
                transform: translateY(24px) scale(.97);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        /* ─── Header ────────────────────────────────────────── */
        #batchModal .modal-header {
            background: linear-gradient(135deg, var(--g500) 0%, var(--g400) 100%);
            border: none;
            padding: 28px 32px 24px;
            position: relative;
            overflow: hidden;
        }

        #batchModal .modal-header::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .1);
        }

        #batchModal .modal-header::after {
            content: '';
            position: absolute;
            bottom: -60px;
            left: 40px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07);
        }

        .batch-header-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255, 255, 255, .2);
            border: 1px solid rgba(255, 255, 255, .3);
            border-radius: 100px;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 500;
            color: #fff;
            letter-spacing: .5px;
            text-transform: uppercase;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        #batchModal .modal-title {
            font-family: var(--ff-display);
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            position: relative;
            z-index: 1;
        }

        .batch-header-sub {
            color: rgba(255, 255, 255, .8);
            font-size: 13.5px;
            margin-top: 4px;
            position: relative;
            z-index: 1;
        }

        #batchModal .btn-close {
            position: absolute;
            top: 18px;
            right: 22px;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .2);
            border: 1px solid rgba(255, 255, 255, .3);
            opacity: 1;
            filter: none;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            transition: var(--tr);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath stroke='%23fff' stroke-width='1.8' d='M2 2l12 12M14 2L2 14'/%3E%3C/svg%3E");
            background-size: 14px;
            background-repeat: no-repeat;
            background-position: center;
        }

        #batchModal .btn-close:hover {
            background-color: rgba(255, 255, 255, .35);
            transform: scale(1.1);
        }

        /* ─── Body ──────────────────────────────────────────── */
        #batchModal .modal-body {
            padding: 8px 32px;
            background: #fff;
            overflow-y: scroll;
            flex: 1;
        }

        /* ─── Section labels ────────────────────────────────── */
        .batch-section-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .8px;
            text-transform: uppercase;
            color: var(--g500);
            margin: 10px 0 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .batch-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--g100);
        }

        /* ─── Form fields ───────────────────────────────────── */
        #batchModal .form-group {
            margin-bottom: 0;
        }

        #batchModal .form-group label {
            font-size: 13px;
            font-weight: 500;
            color: var(--n800);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .req-dot {
            width: 6px;
            height: 6px;
            background: var(--g500);
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        #batchModal .form-control {
            font-family: var(--ff-body);
            font-size: 14px;
            color: var(--n800);
            background: var(--n50);
            border: 1.5px solid var(--n200);
            border-radius: 10px;
            padding: 11px 14px;
            transition: var(--tr);
            height: auto;
        }

        #batchModal .form-control:focus {
            border-color: var(--g400);
            background: var(--g50);
            box-shadow: 0 0 0 3px rgba(79, 190, 128, .15);
        }

        #batchModal select.form-control {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath fill='%239ca3af' d='M5.5 8l4.5 4.5L14.5 8z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
            padding-right: 36px;
        }

        /* ─── Day chips ─────────────────────────────────────── */
        .batch-days-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 4px;
        }

        .day-chip-item {
            position: relative;
        }

        .day-chip-item input[type=checkbox] {
            display: none;
        }

        .day-chip-item label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 40px;
            border-radius: 10px;
            background: var(--n100);
            border: 1.5px solid var(--n200);
            font-size: 13px;
            font-weight: 500;
            color: var(--n600);
            cursor: pointer;
            transition: var(--tr);
            user-select: none;
            margin: 0;
        }

        .day-chip-item label:hover {
            background: var(--g100);
            border-color: var(--g400);
            color: var(--g600);
        }

        .day-chip-item input:checked+label {
            background: var(--g500);
            border-color: var(--g500);
            color: #fff;
            box-shadow: 0 4px 12px rgba(41, 166, 92, .35);
            transform: translateY(-1px);
        }

        /* ─── Footer ────────────────────────────────────────── */
        #batchModal .modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 5;
            padding: 20px 32px 28px;
            border-top: 1px solid var(--n100);
            background: #fff;
            gap: 12px;
        }

        #batchModal .btn-secondary {
            font-family: var(--ff-body);
            font-size: 14px;
            font-weight: 500;
            padding: 6px 18px;
            border-radius: 100px;
            background: var(--n100);
            color: var(--n600);
            border: 1.5px solid var(--n200);
            transition: var(--tr);
        }

        #batchModal .btn-secondary:hover {
            background: var(--n200);
            color: var(--n800);
        }

        #batchModal .btn-primary {
            font-family: var(--ff-body);
            font-size: 14px;
            font-weight: 500;
            padding: 6px 18px;
            border-radius: 100px;
            background: #fff;
            color: #10b981;
            border: none;
            transition: var(--tr);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #batchModal .btn-primary:hover {
            transform: translateY(-2px);
            background: #e4fff6f3;
        }

        #batchModal .btn-primary:active {
            transform: translateY(0);
        }

        @media(max-width:540px) {
            #batchModal .modal-header {
                padding: 22px 20px 18px;
            }

            #batchModal .modal-body {
                padding: 20px 20px 8px;
            }

            #batchModal .modal-footer {
                padding: 16px 20px 22px;
            }
        }
    </style>
    @php
        // KPI roll-up over the (already coach/teacher-scoped) $courses
        // collection. Computed in-view because the upstream controller
        // returns the eager-loaded collection straight — no need for a
        // schema/controller change just to surface 4 counters.
        $kpiCoursesWithBatches = $courses->filter(fn($c) => $c->batches->count() > 0)->count();
        $kpiTotalBatches = $courses->sum(fn($c) => $c->batches->count());
        $kpiActiveBatches = $courses->sum(fn($c) => $c->batches->where('status', 'active')->count());
        $kpiTotalCapacity = $courses->sum(fn($c) => $c->batches->sum(fn($b) => (int) ($b->capacity ?? 0)));
        $canCreate =
            userAuth()->role == 'instructor' ||
            (!in_array(strtolower(trim(userAuth()->role)), ['instructor', 'student']) &&
                checkPermissionView('course-batches-create'));
    @endphp

    <div class="corp-page" id="courseBatches">

        {{-- Header ────────────────────────────────────────────────── --}}
        <div class="corp-header">
            <div class="corp-header__title">
                <h4>{{ __('Course Batches') }}</h4>
                <p>{{ __('Every batch grouped by its course. Each batch holds a schedule, a day-pattern, and a capacity — manage the live cohort here.') }}
                </p>
            </div>
            <div class="corp-header__actions">
                @if ($canCreate)
                    <button type="button" class="btn-corp-primary" data-bs-toggle="modal" data-bs-target="#batchModal">
                        <i class="fas fa-plus"></i> {{ __('Add New Batch') }}
                    </button>
                @endif
                <a href="{{ route('instructor.courses.index') }}" class="btn-corp-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to Courses') }}
                </a>
            </div>
        </div>

        {{-- KPI icon chips — scoped here so adding a unique icon to each tile
         doesn't touch the shared corp-kpi primitive. --}}
        <style>
            #courseBatches .corp-kpi__tile {
                padding-left: 70px;
                min-height: 96px;
            }

            #courseBatches .corp-kpi__tile .cb-kpi-icon {
                position: absolute;
                top: 18px;
                left: 18px;
                width: 38px;
                height: 38px;
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #ffffff);
                color: var(--accent, var(--corp-brand));
                border: 1px solid color-mix(in srgb, var(--accent, var(--corp-brand)) 18%, transparent);
                font-size: 15px;
            }
        </style>

        {{-- KPI strip ──────────────────────────────────────────────── --}}
        <div class="corp-kpi">
            <div class="corp-kpi__tile" style="--accent:var(--corp-brand);">
                <span class="cb-kpi-icon"><i class="fas fa-graduation-cap"></i></span>
                <div class="corp-kpi__label">{{ __('Courses with Batches') }}</div>
                <div class="corp-kpi__value">{{ number_format($kpiCoursesWithBatches) }}</div>
                <div class="corp-kpi__sub">
                    {{ $courses->count() }} {{ trans_choice('course total|courses total', $courses->count()) }}
                </div>
            </div>
            <div class="corp-kpi__tile" style="--accent:#0ea5e9;">
                <span class="cb-kpi-icon"><i class="fas fa-layer-group"></i></span>
                <div class="corp-kpi__label">{{ __('Total Batches') }}</div>
                <div class="corp-kpi__value">{{ number_format($kpiTotalBatches) }}</div>
                <div class="corp-kpi__sub">{{ __('across all your courses') }}</div>
            </div>
            <div class="corp-kpi__tile" style="--accent:#10b981;">
                <span class="cb-kpi-icon"><i class="fas fa-check-circle"></i></span>
                <div class="corp-kpi__label">{{ __('Active') }}</div>
                <div class="corp-kpi__value" style="color:#047857;">{{ number_format($kpiActiveBatches) }}</div>
                <div class="corp-kpi__sub">
                    @if ($kpiTotalBatches > 0)
                        {{ round(($kpiActiveBatches / $kpiTotalBatches) * 100) }}% {{ __('of total') }}
                    @else
                        {{ __('no batches yet') }}
                    @endif
                </div>
            </div>
            <div class="corp-kpi__tile" style="--accent:#f59e0b;">
                <span class="cb-kpi-icon"><i class="fas fa-users"></i></span>
                <div class="corp-kpi__label">{{ __('Total Capacity') }}</div>
                <div class="corp-kpi__value">{{ $kpiTotalCapacity > 0 ? number_format($kpiTotalCapacity) : '∞' }}</div>
                <div class="corp-kpi__sub">{{ __('seats across active batches') }}</div>
            </div>
        </div>

        {{-- Filters + search (2026-07-09) ─────────────────────────── --}}
        <style>
            #courseBatches .cb-filterbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center;
                background:#fff; border:1px solid var(--corp-border, #e5e7eb); border-radius:12px; padding:12px 14px; margin-bottom:18px; }
            #courseBatches .cb-filter__search { position:relative; flex:1 1 260px; min-width:200px; }
            #courseBatches .cb-filter__search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:13px; }
            #courseBatches .cb-filter__search input { width:100%; padding:9px 12px 9px 32px; border:1px solid #e2e8f0; border-radius:9px; font-size:13.5px; }
            #courseBatches .cb-filter__select, #courseBatches .cb-filter__date { padding:9px 12px; border:1px solid #e2e8f0; border-radius:9px; font-size:13.5px; background:#fff; color:#334155; }
            #courseBatches .cb-filterbar input:focus, #courseBatches .cb-filterbar select:focus { outline:none; border-color:var(--corp-brand); box-shadow:0 0 0 3px color-mix(in srgb, var(--corp-brand) 15%, transparent); }
            #courseBatches .cb-filter__btn, #courseBatches .cb-filter__clear { white-space:nowrap; }
        </style>

        <style>
            /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
            /* Page-local neutral vars used by the green batch modal — re-declared for dark. */
            html[data-theme="dark"] {
                --n50:  #17233a;
                --n100: #22304a;
                --n200: #2a3a55;
                --n400: #94a3b8;
                --n600: #94a3b8;
                --n800: #e2e8f0;
            }
            html[data-theme="dark"] #batchModal .modal-body { background: #1e293b; }
            html[data-theme="dark"] #batchModal .modal-footer { background: #1e293b; }
            html[data-theme="dark"] #batchModal .btn-primary { background: #1e293b; }
            html[data-theme="dark"] #courseBatches .corp-kpi__tile .cb-kpi-icon {
                background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #1e293b);
            }
            html[data-theme="dark"] #courseBatches .cb-filterbar { background: #1e293b; }
            html[data-theme="dark"] #courseBatches .cb-filter__search i { color: #94a3b8; }
            html[data-theme="dark"] #courseBatches .cb-filter__search input { background:#1e293b; border-color:#2a3a55; color:#e2e8f0; }
            html[data-theme="dark"] #courseBatches .cb-filter__select,
            html[data-theme="dark"] #courseBatches .cb-filter__date { background:#1e293b; border-color:#2a3a55; color:#e2e8f0; }
        </style>
        <form method="GET" action="{{ route('instructor.course-batches.index') }}" class="cb-filterbar">
            <span class="cb-filter__search">
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search by course or batch name…') }}" autocomplete="off">
            </span>
            <select name="status" class="cb-filter__select" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
            </select>
            <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="cb-filter__date" title="{{ __('Filter by creation date') }}">
            <button type="submit" class="btn-corp-primary cb-filter__btn"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
            @if (!empty($filters['active']))
                <a href="{{ route('instructor.course-batches.index') }}" class="btn-corp-secondary cb-filter__clear"><i class="fas fa-times"></i> {{ __('Clear') }}</a>
            @endif
        </form>

        {{-- Per-course cards ──────────────────────────────────────── --}}
        @forelse ($courses as $course)
            <div class="corp-form-card cb-course-card">
                <div class="corp-form-card__head">
                    <div class="cb-course-card__head-row">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-graduation-cap" style="color:var(--corp-brand);"></i>
                            {{ $course->title }}
                        </h6>
                        <span class="cb-course-card__count">
                            {{ $course->batches->count() }}
                            {{ trans_choice('batch|batches', $course->batches->count()) }}
                        </span>
                    </div>
                </div>

                @if ($course->batches->count() > 0)
                    <div class="corp-table-wrap" style="border:none; border-radius:0;">
                        <table class="corp-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Schedule') }}</th>
                                    <th>{{ __('Days') }}</th>
                                    <th>{{ __('Capacity') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th style="width:140px; text-align:right;">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($course->batches as $batch)
                                    <tr>
                                        <td data-label="{{ __('Title') }}">
                                            <div style="font-weight:600; color:var(--corp-text);">{{ $batch->title }}</div>
                                            <div style="font-size:11px; color:var(--corp-muted); margin-top:2px;">
                                                {{ \Carbon\Carbon::parse($batch->start_date)->format('d M') }}
                                                —
                                                {{ \Carbon\Carbon::parse($batch->end_date)->format('d M Y') }}
                                            </div>
                                        </td>

                                        <td data-label="{{ __('Schedule') }}">
                                            <span class="cb-sched">
                                                {{ \Carbon\Carbon::parse($batch->start_time)->format('h:i A') }}
                                                —
                                                {{ \Carbon\Carbon::parse($batch->end_time)->format('h:i A') }}
                                            </span>
                                        </td>

                                        <td data-label="{{ __('Days') }}">
                                            <div class="cb-days">
                                                @foreach (($batch->days ?? []) as $day)
                                                    <span
                                                        class="corp-pill corp-pill--brand corp-pill--plain">{{ $day }}</span>
                                                @endforeach
                                            </div>
                                        </td>

                                        <td data-label="{{ __('Capacity') }}">
                                            <strong>{{ $batch->capacity ?? '∞' }}</strong>
                                        </td>

                                        <td data-label="{{ __('Status') }}">
                                            @if ($batch->status == 'active')
                                                <span class="corp-pill corp-pill--success">{{ __('Active') }}</span>
                                            @else
                                                <span class="corp-pill corp-pill--danger">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>

                                        <td data-label="{{ __('Actions') }}" style="text-align:right;">
                                            <div class="corp-actions" style="justify-content:flex-end;">
                                                {{-- Audit 2026-05-18 — quick link to attendance summary --}}
                                                <a class="corp-actions__btn"
                                                    href="{{ route('instructor.batch-attendance.show', ['batch' => $batch->id]) }}"
                                                    title="{{ __("View today's attendance") }}">
                                                    <i class="fas fa-users"></i>
                                                </a>

                                                {{-- 2026-07-06 (Role Permission Test doc, issue C) — edit/delete
                                                     gated by granular permission (was always shown). --}}
                                                @if (checkPermissionView('course-batches-edit'))
                                                <button type="button" class="corp-actions__btn edit-batch"
                                                    title="{{ __('Edit') }}" data-id="{{ $batch->id }}"
                                                    data-course_id="{{ $batch->course_id }}"
                                                    data-title="{{ $batch->title }}"
                                                    data-start_date="{{ $batch->start_date }}"
                                                    data-end_date="{{ $batch->end_date }}"
                                                    data-start_time="{{ $batch->start_time }}"
                                                    data-end_time="{{ $batch->end_time }}"
                                                    data-days='@json($batch->days)'
                                                    data-capacity="{{ $batch->capacity }}"
                                                    data-status="{{ $batch->status }}"
                                                    data-attendance_min_percent="{{ $batch->attendance_min_percent }}">
                                                    <i class="far fa-edit"></i>
                                                </button>
                                                @endif

                                                @if (checkPermissionView('course-batches-delete'))
                                                <button type="button"
                                                    class="corp-actions__btn corp-actions__btn--danger delete-batch"
                                                    title="{{ __('Delete') }}" data-id="{{ $batch->id }}">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="cb-empty-row">{{ __('No batches in this course yet.') }}</div>
                @endif
            </div>
        @empty
            <div class="corp-form-card">
                <div class="corp-form-card__body">
                    <div class="corp-empty">
                        <div class="corp-empty__icon"><i class="fas fa-{{ !empty($filters['active']) ? 'search' : 'layer-group' }}"></i></div>
                        @if (!empty($filters['active']))
                            <div class="corp-empty__title">{{ __('No batches match your filters') }}</div>
                            <div class="corp-empty__hint">
                                {{ __('Try a different search term, status or date.') }}
                                <a href="{{ route('instructor.course-batches.index') }}">{{ __('Clear filters') }}</a>
                            </div>
                        @else
                            <div class="corp-empty__title">{{ __('No courses with batches yet') }}</div>
                            <div class="corp-empty__hint">
                                {{ __('Create a course first, then add batches to organise live cohorts and schedules.') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection


<!-- Add/Edit Batch Modal -->
<div class="modal fade" id="batchModal" tabindex="-1" aria-labelledby="batchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header">
                <div>
                    <div class="batch-header-badge">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.2">
                            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                        </svg>
                        {{-- Coach Panel --}}
                        <h5 class="modal-title" id="batchModalLabel">{{ __('Add New Batch') }}</h5>
                    </div>
                    <p class="batch-header-sub">Fill in the details to schedule a new batch session.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="batchForm">
                @csrf
                <input type="hidden" name="batch_id" id="batch_id">

                <!-- Body -->
                <div class="modal-body">

                    <!-- ── Batch Info ── -->
                    <div class="batch-section-label">Batch Info</div>
                    <div class="row g-3">

                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="course_id">
                                    {{ __('Course') }} <span class="req-dot"></span>
                                </label>
                                <select name="course_id" id="course_id" class="form-control" required>
                                    <option value="">{{ __('Select a course…') }}</option>
                                    {{-- 2026-07-10: only batch-eligible courses (live/hybrid); for staff, only ones they created. --}}
                                    @foreach (($batchCourses ?? $courses) as $course)
                                        <option value="{{ $course->id }}">{{ $course->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="title">
                                    {{ __('Batch Title') }} <span class="req-dot"></span>
                                </label>
                                <input type="text" name="title" id="title" class="form-control"
                                    placeholder="e.g. Morning Cohort – July 2025" required>
                            </div>
                        </div>

                    </div>

                    <!-- ── Schedule ── -->
                    <div class="batch-section-label">Schedule</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date">
                                    {{ __('Start Date') }} <span class="req-dot"></span>
                                </label>
                                <input type="date" name="start_date" id="start_date" class="form-control"
                                    required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date">
                                    {{ __('End Date') }} <span class="req-dot"></span>
                                </label>
                                <input type="date" name="end_date" id="end_date" class="form-control" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_time">
                                    {{ __('Start Time') }} <span class="req-dot"></span>
                                </label>
                                <input type="time" name="start_time" id="start_time" class="form-control"
                                    required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_time">
                                    {{ __('End Time') }} <span class="req-dot"></span>
                                </label>
                                <input type="time" name="end_time" id="end_time" class="form-control" required>
                            </div>
                        </div>

                    </div>

                    <!-- ── Days ── -->
                    <div class="batch-section-label">Days</div>
                    <div class="batch-days-wrap">
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                            <span class="day-chip-item">
                                <input class="form-check-input day-checkbox" type="checkbox" name="days[]"
                                    value="{{ $day }}" id="day_{{ $day }}">
                                <label for="day_{{ $day }}">{{ $day }}</label>
                            </span>
                        @endforeach
                    </div>

                    <!-- ── Settings ── -->
                    <div class="batch-section-label">Settings</div>
                    <div class="row g-3">

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="capacity">{{ __('Capacity') }}</label>
                                <input type="number" name="capacity" id="capacity" class="form-control"
                                    min="1" placeholder="{{ __('Leave blank for unlimited') }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status">{{ __('Status') }}</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="active">{{ __('Active') }}</option>
                                    <option value="inactive">{{ __('Inactive') }}</option>
                                </select>
                            </div>
                        </div>

                        {{-- Audit 2026-05-18 phase 5 — per-batch attendance threshold override --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="attendance_min_percent">
                                    {{ __('Attendance threshold (%)') }}
                                    <small class="text-muted">{{ __('blank = use global default') }}</small>
                                </label>
                                <input type="number" name="attendance_min_percent" id="attendance_min_percent"
                                    class="form-control" min="1" max="100"
                                    placeholder="{{ __('e.g. 50') }}">
                            </div>
                        </div>

                    </div>

                </div><!-- /modal-body -->

                <!-- Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.4">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        {{ __('Save Batch') }}
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
<!-- /Batch Modal -->



@push('scripts')
    <script>
        $(document).ready(function() {
            // Reset form when modal opens
            $('#batchModal').on('show.bs.modal', function(e) {
                if (!$(e.relatedTarget).hasClass('edit-batch')) {
                    $('#batchForm')[0].reset();
                    $('#batch_id').val('');
                    $('.day-checkbox').prop('checked', false);
                    $('#batchModalLabel').text('{{ __('Add New Batch') }}');
                }
            });

            $('.edit-batch').on('click', function() {


                // Bootstrap 5 correct show method
                var modal = new bootstrap.Modal(document.getElementById('batchModal'));
                modal.show();

                let days = $(this).attr('data-days'); // IMPORTANT CHANGE

                // convert to array safely
                if (typeof days === 'string') {
                    try {
                        days = JSON.parse(days);
                    } catch (e) {
                        days = [];
                    }
                }



                $('#batch_id').val($(this).data('id'));
                $('#course_id').val($(this).data('course_id'));
                $('#title').val($(this).data('title'));
                let startDate = $(this).data('start_date');
                let endDate = $(this).data('end_date');
                startDate = startDate.split(' ')[0];
                endDate = endDate.split(' ')[0];

                $('#start_date').val(startDate);
                $('#end_date').val(endDate);
                $('#start_time').val($(this).data('start_time'));
                $('#end_time').val($(this).data('end_time'));
                $('#capacity').val($(this).data('capacity'));
                $('#status').val($(this).data('status'));
                // Audit 2026-05-18 phase 5 — restore per-batch override on edit
                $('#attendance_min_percent').val($(this).data('attendance_min_percent') || '');

                $('.day-checkbox').prop('checked', false);

                if (days.length) {
                    days.forEach(function(day) {
                        $('.day-checkbox[value="' + day + '"]').prop('checked', true);
                    });
                }
                $('#batchModalLabel').text("Edit Batch");


            });



            // Save batch (Create or Update)
            // 2026-06-12 — DOUBLE-SUBMIT GUARD. Clicking Save several times
            // quickly used to fire one AJAX POST per click, creating duplicate
            // batches. We now (a) ignore re-entrant submits while one is in
            // flight and (b) disable the Save button + show a spinner, so only
            // ONE request leaves the browser per submission. Re-enabled on error
            // so the coach can fix a validation issue and retry. (Backend
            // batchesStore() also de-dups via firstOrCreate as a belt.)
            var batchSubmitting = false;
            $('#batchForm').on('submit', function(e) {
                e.preventDefault();
                if (batchSubmitting) return;
                batchSubmitting = true;

                var $form = $(this);
                var $submitBtn = $form.find('button[type="submit"]');
                var originalBtnHtml = $submitBtn.html();
                $submitBtn.prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin"></i> {{ __('Saving…') }}');

                var releaseBtn = function () {
                    batchSubmitting = false;
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                };

                var batchId = $("#batch_id").val();
                var url = batchId ? "{{ route('instructor.course-batches.update', ':id') }}".replace(':id',
                    batchId) : "{{ route('instructor.course-batches.store') }}";
                var method = batchId ? "PUT" : "POST";

                $.ajax({
                    url: url,
                    type: method,
                    data: $form.serialize(),
                    success: function(response) {
                        if (response.status === "success") {
                            toastr.success(response.message);
                            $('#batchModal').modal("hide");
                            location.reload();
                            return; // keep disabled — the page is reloading
                        }
                        // Unexpected non-success — re-enable so we're not stuck.
                        releaseBtn();
                        if (response.message) toastr.error(response.message);
                    },
                    error: function(xhr) {
                        releaseBtn();
                        var errors = xhr.responseJSON && xhr.responseJSON.errors;
                        if (errors) {
                            for (var key in errors) {
                                toastr.error(errors[key][0]);
                            }
                        } else {
                            toastr.error("{{ __('Something went wrong. Please try again.') }}");
                        }
                    }
                });
            });

            // Delete batch
            $(".delete-batch").on("click", function() {
                var batchId = $(this).data("id");
                if (confirm("{{ __('Are you sure you want to delete this batch?') }}")) {
                    $.ajax({
                        url: "{{ route('instructor.course-batches.destroy', ':id') }}".replace(
                            ":id", batchId),
                        type: "DELETE",
                        data: {
                            _token: "{{ csrf_token() }}"
                        },
                        success: function(response) {
                            if (response.status === "success") {
                                toastr.success(response.message);
                                location.reload();
                            }
                        }
                    });
                }
            });
        });
    </script>
@endpush
