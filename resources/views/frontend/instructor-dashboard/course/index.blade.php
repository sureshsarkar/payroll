@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    {{--
        Coach panel — All courses (rebuilt on corp design system 2026-05).
        Uses the same primitives as course-batches and payout (corp-page,
        corp-header, corp-kpi, corp-form-card, btn-corp-primary) so it
        inherits proven responsive behaviour and never overflows the
        col-lg-10 column.

        Functional contract preserved 1:1:
          * $courses paginator with category/instructor relations
          * $totalCoursesCount / $activeCoursesCount / $inactiveCoursesCount / $draftCoursesCount
          * Filters: ?status=active|inactive|is_draft + ?search=
          * Status badges: published / pending / unpublished / draft / rejected
          * Action gates: userAuth()->role == 'instructor' OR checkPermissionView
          * Routes: instructor.courses.create, instructor.courses.edit-view,
                    instructor.courses.attendance-watchlist,
                    instructor.course.delete-request.show
          * `.course-delete-request` class preserved (JS modal trigger lives elsewhere)
          * Pagination via $courses->links()
    --}}

    @include('frontend.instructor-dashboard.settings.partials._corporate')

    <style>
        #instructorCourses {
            --corp-brand:      {{ $brand->primaryColor ?: '#10b981' }};
            --corp-brand-2:    {{ $brand->accentColor  ?: '#059669' }};
            --corp-brand-grad: linear-gradient(135deg, var(--corp-brand) 0%, var(--corp-brand-2) 100%);
        }

        /* ── KPI tile acting as a status filter link ────────────── */
        #instructorCourses .ic-kpi-link {
            text-decoration: none;
            color: inherit;
            transition: transform .14s, box-shadow .14s, border-color .14s;
            display: block;
        }
        #instructorCourses .ic-kpi-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px -8px rgba(15, 23, 42, .14);
            color: inherit;
            text-decoration: none;
        }
        #instructorCourses .corp-kpi__tile.is-active {
            outline: 2px solid var(--accent, var(--corp-brand));
            outline-offset: -2px;
        }

        /* ── Toolbar (filter pills + search) ────────────────────── */
        #instructorCourses .ic-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin: 14px 0 14px;
            min-width: 0;
        }
        #instructorCourses .ic-pills {
            display: flex; gap: 4px; flex-wrap: wrap;
            padding: 4px;
            background: #fff;
            border: 1px solid var(--corp-line);
            border-radius: 10px;
            box-shadow: var(--corp-shadow-sm);
        }
        #instructorCourses .ic-pill {
            font-size: 12.5px; font-weight: 600;
            padding: 6px 14px; border-radius: 7px;
            color: var(--corp-muted); text-decoration: none;
            transition: all .15s var(--corp-ease, ease);
        }
        #instructorCourses .ic-pill:hover {
            color: var(--corp-text); background: var(--corp-line-soft);
            text-decoration: none;
        }
        #instructorCourses .ic-pill.is-on {
            background: var(--corp-brand); color: #fff;
        }
        #instructorCourses .ic-pill.is-on:hover { color: #fff; }

        #instructorCourses .ic-search {
            display: flex; align-items: center; gap: 8px;
            padding: 6px 12px; background: #fff;
            border: 1px solid var(--corp-line);
            border-radius: 10px;
            flex: 1; min-width: 200px;
            max-width: 360px; margin-left: auto;
            box-shadow: var(--corp-shadow-sm);
            transition: border-color .15s, box-shadow .15s;
        }
        #instructorCourses .ic-search:focus-within {
            border-color: var(--corp-brand);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--corp-brand) 18%, transparent);
        }
        #instructorCourses .ic-search i { color: var(--corp-subtle); font-size: 13px; }
        #instructorCourses .ic-search input {
            flex: 1; border: 0; outline: none; min-width: 0;
            font-size: 13.5px; color: var(--corp-text);
            font-family: inherit; background: transparent;
        }
        #instructorCourses .ic-search input::placeholder { color: var(--corp-subtle); }

        /* ── Course card grid ──────────────────────────────────── */
        #instructorCourses .ic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px;
            margin-bottom: 14px;
        }
        #instructorCourses .ic-card {
            background: #fff;
            border: 1px solid var(--corp-line);
            border-radius: 14px;
            overflow: hidden;
            display: flex; flex-direction: column;
            box-shadow: var(--corp-shadow-sm);
            transition: transform .15s, box-shadow .15s, border-color .15s;
        }
        #instructorCourses .ic-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--corp-shadow-md);
            border-color: color-mix(in srgb, var(--corp-brand) 30%, var(--corp-line));
        }
        #instructorCourses .ic-thumb {
            position: relative; aspect-ratio: 16/9;
            background: var(--corp-line-soft); overflow: hidden;
        }
        #instructorCourses .ic-thumb img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform .4s;
        }
        #instructorCourses .ic-card:hover .ic-thumb img { transform: scale(1.04); }
        #instructorCourses .ic-thumb__badge {
            position: absolute; top: 10px; right: 10px;
            font-size: 10px; font-weight: 700;
            padding: 4px 10px; border-radius: 999px;
            letter-spacing: 0.04em; text-transform: uppercase;
        }
        #instructorCourses .ic-badge--published   { background: rgba(16,185,129,.95); color:#fff; }
        #instructorCourses .ic-badge--pending     { background: rgba(245,158,11,.95); color:#fff; }
        #instructorCourses .ic-badge--unpublished { background: rgba(107,114,128,.92); color:#fff; }
        #instructorCourses .ic-badge--draft       { background: rgba(16, 185, 129,.92); color:#fff; }
        #instructorCourses .ic-badge--rejected    { background: rgba(239,68,68,.95); color:#fff; }

        #instructorCourses .ic-body {
            padding: 14px 16px 12px; flex: 1;
            display: flex; flex-direction: column;
        }
        #instructorCourses .ic-top {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; margin-bottom: 8px;
        }
        #instructorCourses .ic-cat {
            font-size: 10.5px; font-weight: 700; letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 3px 9px; border-radius: 5px;
            background: var(--corp-line-soft); color: var(--corp-muted);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            min-width: 0;
        }
        #instructorCourses .ic-title {
            font-size: 14px; font-weight: 700; color: var(--corp-text);
            line-height: 1.4; margin: 0 0 10px;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden; letter-spacing: -0.01em;
            min-height: 40px;
        }
        #instructorCourses .ic-instr {
            display: flex; align-items: center; justify-content: space-between;
            gap: 8px; margin-bottom: 10px;
        }
        #instructorCourses .ic-instr__l { display: flex; align-items: center; gap: 8px; min-width: 0; }
        #instructorCourses .ic-instr__avatar {
            width: 24px; height: 24px; border-radius: 50%; object-fit: cover;
            border: 1px solid var(--corp-line); flex-shrink: 0;
        }
        #instructorCourses .ic-instr__name {
            font-size: 12px; font-weight: 600; color: var(--corp-text-2);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        #instructorCourses .ic-rating {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 11.5px; font-weight: 700; color: #92400e;
            background: #fffbeb; padding: 3px 9px; border-radius: 999px;
            border: 1px solid #fde68a; flex-shrink: 0;
            font-variant-numeric: tabular-nums;
        }
        #instructorCourses .ic-rating i { color: #f59e0b; font-size: 10px; }

        #instructorCourses .ic-stats {
            display: flex; align-items: center; gap: 12px;
            padding-top: 10px; border-top: 1px solid var(--corp-line-soft);
            flex-wrap: wrap;
        }
        #instructorCourses .ic-stat {
            display: flex; align-items: center; gap: 5px;
            font-size: 11.5px; color: var(--corp-muted); font-weight: 500;
        }
        #instructorCourses .ic-stat i { font-size: 11px; color: var(--corp-subtle); }
        #instructorCourses .ic-stat strong { color: var(--corp-text-2); font-weight: 700; font-variant-numeric: tabular-nums; }

        #instructorCourses .ic-actions {
            display: flex; gap: 4px; flex-shrink: 0;
        }
        #instructorCourses .ic-action {
            width: 28px; height: 28px; border-radius: 6px;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--corp-line-soft); color: var(--corp-muted);
            text-decoration: none; font-size: 11px;
            transition: all .15s;
        }
        #instructorCourses .ic-action:hover {
            background: color-mix(in srgb, var(--corp-brand) 12%, #fff);
            color: var(--corp-brand); text-decoration: none;
        }
        #instructorCourses .ic-action--watch:hover { background: color-mix(in srgb, #059669 12%, #fff); color: #059669; }
        #instructorCourses .ic-action--delete:hover { background: #fef2f2; color: #dc2626; }
    </style>

    @php
        $canAdd    = userAuth()->role == 'instructor' || (!in_array(strtolower(trim(userAuth()->role)), ['instructor','student']) && checkPermissionView('courses-create'));
        $canEdit   = userAuth()->role == 'instructor' || (!in_array(strtolower(trim(userAuth()->role)), ['instructor','student']) && checkPermissionView('courses-edit'));
        $canDelete = userAuth()->role == 'instructor' || (!in_array(strtolower(trim(userAuth()->role)), ['instructor','student']) && checkPermissionView('courses-delete'));
        $currentStatus = request('status', '');
    @endphp

    <div class="corp-page" id="instructorCourses">

        {{-- Header --}}
        <div class="corp-header">
            <div class="corp-header__title">
                <h4>{{ __('All courses') }}</h4>
                <p>{{ __('Manage, edit, and track every course you publish on the platform.') }}</p>
            </div>
            <div class="corp-header__actions">
                @if ($canAdd)
                    <a href="{{ route('instructor.courses.create') }}" class="btn-corp-primary">
                        <i class="fas fa-plus"></i> {{ __('Add new course') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- KPI icon chip styles (scoped) --}}
        <style>
            #instructorCourses .corp-kpi__tile { padding-left: 70px; min-height: 96px; }
            #instructorCourses .corp-kpi__tile .ic-kpi-icon {
                position: absolute; top: 18px; left: 18px;
                width: 38px; height: 38px; border-radius: 10px;
                display: inline-flex; align-items: center; justify-content: center;
                background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #ffffff);
                color: var(--accent, var(--corp-brand));
                border: 1px solid color-mix(in srgb, var(--accent, var(--corp-brand)) 18%, transparent);
                font-size: 15px;
            }
        </style>

        <style>
            /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
            html[data-theme="dark"] #instructorCourses .ic-pills  { background: #1e293b; }
            html[data-theme="dark"] #instructorCourses .ic-search { background: #1e293b; }
            html[data-theme="dark"] #instructorCourses .ic-card   { background: #1e293b; }
        </style>

        {{-- KPI strip — each tile is a status filter link --}}
        <div class="corp-kpi">
            <a href="{{ route('instructor.courses.index') }}"
               class="corp-kpi__tile ic-kpi-link {{ $currentStatus === '' ? 'is-active' : '' }}"
               style="--accent:var(--corp-brand);">
                <span class="ic-kpi-icon"><i class="fas fa-book-open"></i></span>
                <div class="corp-kpi__label">{{ __('Total') }}</div>
                <div class="corp-kpi__value">{{ number_format($totalCoursesCount) }}</div>
                <div class="corp-kpi__sub">{{ __('all courses you offer') }}</div>
            </a>
            <a href="?status=active"
               class="corp-kpi__tile ic-kpi-link {{ $currentStatus === 'active' ? 'is-active' : '' }}"
               style="--accent:#10b981;">
                <span class="ic-kpi-icon"><i class="fas fa-check-circle"></i></span>
                <div class="corp-kpi__label">{{ __('Published') }}</div>
                <div class="corp-kpi__value" style="color:#047857;">{{ number_format($activeCoursesCount) }}</div>
                <div class="corp-kpi__sub">{{ __('live on the site') }}</div>
            </a>
            <a href="?status=inactive"
               class="corp-kpi__tile ic-kpi-link {{ $currentStatus === 'inactive' ? 'is-active' : '' }}"
               style="--accent:#f59e0b;">
                <span class="ic-kpi-icon"><i class="fas fa-hourglass-half"></i></span>
                <div class="corp-kpi__label">{{ __('Pending') }}</div>
                <div class="corp-kpi__value" style="color:{{ $inactiveCoursesCount > 0 ? '#92400e' : 'var(--corp-text)' }};">
                    {{ number_format($inactiveCoursesCount) }}
                </div>
                <div class="corp-kpi__sub">{{ __('awaiting review') }}</div>
            </a>
            <a href="?status=is_draft"
               class="corp-kpi__tile ic-kpi-link {{ $currentStatus === 'is_draft' ? 'is-active' : '' }}"
               style="--accent:#ef4444;">
                <span class="ic-kpi-icon"><i class="fas fa-file-alt"></i></span>
                <div class="corp-kpi__label">{{ __('Drafts') }}</div>
                <div class="corp-kpi__value">{{ number_format($draftCoursesCount) }}</div>
                <div class="corp-kpi__sub">{{ __('not yet submitted') }}</div>
            </a>
        </div>

        {{-- Toolbar: filter pills + search --}}
        <div class="ic-toolbar">
            <div class="ic-pills">
                <a href="{{ route('instructor.courses.index') }}"
                   class="ic-pill {{ $currentStatus === '' ? 'is-on' : '' }}">{{ __('All') }}</a>
                <a href="?status=active"
                   class="ic-pill {{ $currentStatus === 'active' ? 'is-on' : '' }}">{{ __('Published') }}</a>
                <a href="?status=inactive"
                   class="ic-pill {{ $currentStatus === 'inactive' ? 'is-on' : '' }}">{{ __('Pending') }}</a>
                <a href="?status=is_draft"
                   class="ic-pill {{ $currentStatus === 'is_draft' ? 'is-on' : '' }}">{{ __('Draft') }}</a>
            </div>
            <form method="get" class="ic-search">
                <i class="fas fa-search"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('Search courses or category…') }}"
                       onchange="this.form.submit()">
                @if ($currentStatus !== '')
                    <input type="hidden" name="status" value="{{ $currentStatus }}">
                @endif
            </form>
        </div>

        {{-- Course grid --}}
        @if ($courses->count() > 0)
            <div class="ic-grid">
                @foreach ($courses as $course)
                    @php
                        $lectureCount = App\Models\CourseChapterItem::whereHas('chapter', function ($q) use ($course) {
                            $q->where('course_id', $course->id);
                        })->count();

                        if ($course->is_approved == 'rejected') {
                            $badgeClass = 'ic-badge--rejected';  $badgeLabel = __('Rejected');
                        } elseif ($course->status == 'active') {
                            $badgeClass = 'ic-badge--published'; $badgeLabel = __('Published');
                        } elseif ($course->status == 'inactive') {
                            $badgeClass = 'ic-badge--unpublished'; $badgeLabel = __('Unpublished');
                        } elseif ($course->status == 'is_draft') {
                            $badgeClass = 'ic-badge--draft'; $badgeLabel = __('Draft');
                        } elseif ($course->is_approved == 'pending') {
                            $badgeClass = 'ic-badge--pending'; $badgeLabel = __('Pending');
                        } else {
                            $badgeClass = 'ic-badge--unpublished'; $badgeLabel = ucfirst((string) $course->status);
                        }
                    @endphp

                    <div class="ic-card">
                        <div class="ic-thumb">
                            <img src="{{ asset($course->thumbnail) }}" alt="{{ $course->title }}">
                            <span class="ic-thumb__badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                        </div>

                        <div class="ic-body">
                            <div class="ic-top">
                                <span class="ic-cat">
                                    {{ $course->category->translation->name ?? __('Uncategorised') }}
                                </span>
                                <div class="ic-actions">
                                    @if ($canEdit)
                                        <a href="{{ route('instructor.courses.edit-view', $course->id) }}"
                                           class="ic-action" title="{{ __('Edit') }}">
                                            <i class="far fa-edit"></i>
                                        </a>
                                        <a href="{{ route('instructor.courses.attendance-watchlist', $course->id) }}"
                                           class="ic-action ic-action--watch"
                                           title="{{ __('Attendance watchlist') }}">
                                            <i class="far fa-clipboard"></i>
                                        </a>
                                    @endif
                                    @if ($canDelete)
                                        <a href="{{ route('instructor.course.delete-request.show', $course->id) }}"
                                           class="ic-action ic-action--delete course-delete-request"
                                           title="{{ __('Delete') }}">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <h5 class="ic-title">{{ $course->title }}</h5>

                            <div class="ic-instr">
                                <div class="ic-instr__l">
                                    <img class="ic-instr__avatar"
                                         src="{{ asset($course->instructor->image) }}"
                                         alt="{{ $course->instructor->name }}">
                                    <span class="ic-instr__name">{{ $course->instructor->name }}</span>
                                </div>
                                <span class="ic-rating">
                                    <i class="fas fa-star"></i>
                                    {{ number_format($course->reviews()->avg('rating') ?? 0, 1) }}
                                </span>
                            </div>

                            <div class="ic-stats">
                                <div class="ic-stat">
                                    <i class="fas fa-layer-group"></i>
                                    <strong>{{ $lectureCount }}</strong> {{ __('lectures') }}
                                </div>
                                <div class="ic-stat">
                                    <i class="fas fa-clock"></i>
                                    <span style="font-variant-numeric:tabular-nums;">{{ minutesToHours($course->duration) }}</span>
                                </div>
                                <div class="ic-stat">
                                    <i class="fas fa-user-graduate"></i>
                                    <strong>{{ $course->enrollments()->count() }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if ($courses->hasPages())
                <div style="display:flex; justify-content:center; margin-top:18px;">
                    {{ $courses->links() }}
                </div>
            @endif
        @else
            <div class="corp-form-card">
                <div class="corp-form-card__body">
                    <div class="corp-empty">
                        <div class="corp-empty__icon"><i class="fas fa-book-open"></i></div>
                        <div class="corp-empty__title">
                            @if (request('search') || $currentStatus !== '')
                                {{ __('No courses match your filters') }}
                            @else
                                {{ __('No courses yet') }}
                            @endif
                        </div>
                        <div class="corp-empty__hint">
                            @if (request('search') || $currentStatus !== '')
                                {{ __('Try clearing the search box or switching filter.') }}
                            @else
                                {{ __("You haven't created any courses yet. Start by adding your first course.") }}
                            @endif
                        </div>
                        @if ($canAdd)
                            <a href="{{ route('instructor.courses.create') }}" class="btn-corp-primary" style="margin-top:14px;">
                                <i class="fas fa-plus"></i> {{ __('Create your first course') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

    </div>
@endsection
