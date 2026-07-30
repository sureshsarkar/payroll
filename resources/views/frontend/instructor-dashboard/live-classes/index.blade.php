@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    {{--
        Coach live classes — corporate redesign (2026-05).

        Only the LIST is rebuilt (header + table + pagination). Both
        modals below this block (#batchModal "Add Live Class" + #instantModal
        "Instant Live Class") are LEFT UNTOUCHED because they carry
        complex form bindings + JS (Zoom integration, batch dropdown
        cascading, validation). Restyling them would risk breaking
        already-working flows.

        Functional contract preserved 1:1:
          * $liveClasses paginator with lesson+course relations
          * currently_in_count → live-attendance-pulse badge with the
            same data-live-class-id / .live-attendance-count hooks
            (auto-refreshed every 30s by the JS at the bottom of this
            view)
          * route('instructor.live-class', $lesson_id) when today
          * route('instructor.live-class.attendance', $id)
          * route('instructor.live-class.edit', $id)
          * The two modal trigger buttons keep their Bootstrap data-bs-*
            attributes and IDs (#instantModal, #batchModal)
    --}}

    {{-- Corp design system — same primitive used by course-batches, payout,
         and every other corp-styled page. Gives us .corp-page, .corp-header,
         .corp-form-card, .corp-table, .corp-pill, .corp-actions, .corp-empty,
         .btn-corp-primary, .btn-corp-secondary — all battle-tested. --}}
    @include('frontend.instructor-dashboard.settings.partials._corporate')

    <style>
        /* Override corp brand vars to track the coach's brand on white-label. */
        #liveClasses {
            --corp-brand:      {{ $brand->primaryColor ?: '#10b981' }};
            --corp-brand-2:    {{ $brand->accentColor  ?: '#059669' }};
            --corp-brand-grad: linear-gradient(135deg, var(--corp-brand) 0%, var(--corp-brand-2) 100%);
        }

        /* ── Page-specific add-ons (only what corp-page does NOT provide) ── */

        /* LIVE badge — pulsing red pill that links to the host page */
        #liveClasses .lc-live {
            display: inline-flex; align-items: center;     gap: 3px;
            padding: 3px 5px;
            border-radius: 999px;
            background: #11b578; color: #fff;
            font-size: 10.5px; font-weight: 700;
            letter-spacing: 0.06em; text-decoration: none;
            box-shadow: 0 2px 8px rgb(20 171 95 / 51%);
        }
        #liveClasses .lc-live:hover { color: #fff; opacity: 0.92; text-decoration: none; }
        #liveClasses .lc-live::before {
            content: ''; width: 6px; height: 6px; border-radius: 50%;
            background: #fff;
            animation: lc-pulse 1.6s infinite ease-in-out;
        }
        @keyframes lc-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: .35; transform: scale(.7); }
        }

        /* In-meeting pulse (preserves JS hooks — class names must stay) */
        #liveClasses .live-attendance-pulse {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: 999px;
            background: #ecfdf5; color: #047857;
            font-size: 10.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
            margin-left: 6px;
        }
        #liveClasses .live-attendance-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45);
            animation: lc-att-pulse 1.6s infinite;
        }
        @keyframes lc-att-pulse {
            0%   { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45); }
            70%  { box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* Truncate long titles in cells */
        #liveClasses .lc-title-cell {
            max-width: 220px;
            display: inline-block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>

    <div class="corp-page" id="liveClasses">

        {{-- Header — corp primitive, identical to course-batches --}}
        <div class="corp-header">
            <div class="corp-header__title">
                <h4>{{ __('Live Classes') }}</h4>
                <p>{{ __('Schedule sessions, start instant Zoom meetings, and track who is in the room right now.') }}</p>
            </div>
            <div class="corp-header__actions">
                {{-- 2026-07-03 — 1:1 private meeting with a single student (separate
                     from batch/group live classes). Styled to match the header. --}}
                <a href="{{ route('instructor.instant-meetings.index') }}" class="btn-corp-secondary"
                   style="text-decoration:none;"
                   title="{{ __('Start a private 1:1 meeting with one student') }}">
                    <i class="fas fa-user-friends" style="color:#10b981;"></i> {{ __('Instant Meeting 1:1') }}
                </a>
                <button class="btn-corp-secondary" type="button"
                        data-bs-toggle="modal" data-bs-target="#instantModal"
                        title="{{ __('Start a Zoom session right now without scheduling') }}">
                    <i class="fas fa-bolt" style="color:#10b981;"></i> {{ __('Instant Live Class') }}
                </button>
                <button class="btn-corp-primary" type="button"
                        data-bs-toggle="modal" data-bs-target="#batchModal">
                    <i class="fas fa-plus"></i> {{ __('Add Live Class') }}
                </button>
            </div>
        </div>

        {{-- 2026-05-26 (bug-doc C5) — proactive UX hint when Zoom isn't
             configured yet. Without this, the coach only discovers the
             missing credentials when "Add Live Class" submit fails. --}}
        @if(isset($zoomConfigured) && ! $zoomConfigured)
            <div style="background:#FEF3C7;border-left:4px solid #F59E0B;padding:14px 18px;border-radius:8px;margin-bottom:18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                <i class="fas fa-exclamation-triangle" style="color:#B45309;font-size:18px;"></i>
                <div style="flex:1;min-width:240px;">
                    <strong style="display:block;color:#92400E;font-size:14px;margin-bottom:2px;">
                        {{ __('Zoom is not configured yet.') }}
                    </strong>
                    <span style="color:#78350F;font-size:13px;">
                        {{ __('Add your Zoom Server-to-Server OAuth credentials so you can create live classes.') }}
                    </span>
                </div>
                <a href="{{ route('instructor.zoom-setting.index') }}" class="btn-corp-primary" style="text-decoration:none;">
                    <i class="fas fa-cog"></i> {{ __('Open Zoom Settings') }}
                </a>
            </div>
        @endif

        {{-- List card — corp-form-card with corp-table inside --}}
        <div class="corp-form-card">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title">
                    <i class="fas fa-video"></i>
                    {{ __('All live classes') }}
                    <span style="margin-left:auto; font-weight:500; color:var(--corp-muted); text-transform:none; letter-spacing:0; font-size:11px;">
                        {{ $liveClasses->total() }} {{ trans_choice('class|classes', $liveClasses->total()) }}
                    </span>
                </h6>
            </div>

            {{-- Filters + search (2026-07-09) --}}
            <style>
                #liveClasses .lc-filterbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:12px 16px; border-bottom:1px solid var(--corp-border, #e5e7eb); }
                #liveClasses .lc-filter__search { position:relative; flex:1 1 260px; min-width:200px; }
                #liveClasses .lc-filter__search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:13px; }
                #liveClasses .lc-filter__search input { width:100%; padding:9px 12px 9px 32px; border:1px solid #e2e8f0; border-radius:9px; font-size:13.5px; }
                #liveClasses .lc-filter__select, #liveClasses .lc-filter__date { padding:9px 12px; border:1px solid #e2e8f0; border-radius:9px; font-size:13.5px; background:#fff; color:#334155; }
                #liveClasses .lc-filterbar input:focus, #liveClasses .lc-filterbar select:focus { outline:none; border-color:var(--corp-brand); box-shadow:0 0 0 3px color-mix(in srgb, var(--corp-brand) 15%, transparent); }
            </style>
            <form method="GET" action="{{ route('instructor.live-classes.index') }}" class="lc-filterbar">
                <span class="lc-filter__search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search by course or live class title…') }}" autocomplete="off">
                </span>
                <select name="status" class="lc-filter__select" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="scheduled" @selected(($filters['status'] ?? '') === 'scheduled')>{{ __('Scheduled') }}</option>
                    <option value="live" @selected(($filters['status'] ?? '') === 'live')>{{ __('Live') }}</option>
                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>{{ __('Completed') }}</option>
                </select>
                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="lc-filter__date" title="{{ __('Filter by date') }}">
                <button type="submit" class="btn-corp-primary"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
                @if (!empty($filters['active']))
                    <a href="{{ route('instructor.live-classes.index') }}" class="btn-corp-secondary"><i class="fas fa-times"></i> {{ __('Clear') }}</a>
                @endif
            </form>

            @if ($liveClasses->count() > 0)
                <div class="corp-table-wrap" style="border:none; border-radius:0;">
                    <table class="corp-table live-class-table">
                        <thead>
                            <tr>
                                <th style="width:60px;">#</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Course') }}</th>
                                <th>{{ __('Lesson') }}</th>
                                <th>{{ __('Start time') }}</th>
                                <th style="width:120px; text-align:right;">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($liveClasses as $index => $liveClass)
                                @php
                                    $rowNum  = ($liveClasses->currentPage() - 1) * $liveClasses->perPage() + $index + 1;
                                    $hasAtt  = ($liveClass->currently_in_count ?? 0) > 0;
                                    // 2026-06-03 (#9) — a finished class (completed / batch
                                    // ended) is no longer joinable, by the coach either. Use the
                                    // definitive signal so a class running past its slot is never
                                    // treated as over. Mirrors ZoomSignatureController@issue (host).
                                    $isFinished = $liveClass->isFinished();
                                    // 2026-06-23 — "LIVE" must mean ACTUALLY running, not merely
                                    // scheduled for today. A class is LIVE only once its start time
                                    // is reached and it is not over/finished; a future class (even
                                    // later today) is "Scheduled". Computed inline (Carbon only) so
                                    // it never depends on model-method availability.
                                    $hasStarted = false;
                                    $isOver     = false;
                                    $isToday    = false; // kept: the Join button below uses it
                                    try {
                                        $lcStart = $liveClass->start_time
                                            ? \Carbon\Carbon::parse($liveClass->start_time) : null;
                                        if ($lcStart) {
                                            $isToday    = $lcStart->isToday();
                                            $hasStarted = $lcStart->lessThanOrEqualTo(now());
                                            $overAt = $lcStart->copy()
                                                ->addMinutes((int) ($liveClass->expected_duration_minutes ?: 60))
                                                ->addMinutes(5);
                                            $isOver = now()->greaterThanOrEqualTo($overAt);
                                        }
                                    } catch (\Throwable $e) {
                                        $hasStarted = false; $isOver = false; $isToday = false;
                                    }
                                @endphp
                                <tr>
                                    <td data-label="#">{{ $rowNum }}</td>
                                    <td data-label="{{ __('Status') }}">
                                        @if ($isFinished || $isOver)
                                            <span class="corp-pill corp-pill--success">
                                                <i class="fas fa-check"></i> {{ __('Completed') }}
                                            </span>
                                        @elseif ($hasStarted)
                                            <a href="{{ route('instructor.live-class', $liveClass->lesson_id) }}"
                                               class="lc-live live-class1">
                                                <!--<span class="live-dot"></span>-->
                                                {{ __('LIVE') }}
                                            </a>
                                        @else
                                            <span class="corp-pill corp-pill--warning">{{ __('Scheduled') }}</span>
                                        @endif

                                        @if ($hasAtt)
                                            <span class="live-attendance-pulse" data-live-class-id="{{ $liveClass->id }}"
                                                  title="{{ __('Currently in the meeting (auto-refreshes every 30s)') }}">
                                                <span class="live-attendance-dot"></span>
                                                <span class="live-attendance-count">{{ $liveClass->currently_in_count }}</span>
                                                {{ __('in meeting') }}
                                            </span>
                                        @else
                                            <span class="live-attendance-pulse d-none" data-live-class-id="{{ $liveClass->id }}">
                                                <span class="live-attendance-dot"></span>
                                                <span class="live-attendance-count">0</span>
                                                {{ __('in meeting') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td data-label="{{ __('Course') }}">
                                        <span class="lc-title-cell" title="{{ $liveClass->lesson?->course?->title ?? '' }}">
                                            {{ $liveClass->lesson?->course?->title ?? __('N/A') }}
                                        </span>
                                    </td>
                                    <td data-label="{{ __('Lesson') }}">
                                        <span class="lc-title-cell" title="{{ $liveClass->lesson->title ?? '' }}">
                                            {{ $liveClass->lesson->title ?? __('N/A') }}
                                        </span>
                                    </td>
                                    <td data-label="{{ __('Start time') }}">
                                        <span class="corp-pill corp-pill--success">
                                            <i class="far fa-clock"></i>
                                            {{ \Carbon\Carbon::parse($liveClass->start_time)->format('h:i A, d M Y') }}
                                        </span>
                                    </td>
                                    <td data-label="{{ __('Actions') }}" style="text-align:right;">
                                        <div class="corp-actions" style="justify-content:flex-end;gap:6px;">
                                            {{-- 2026-05-26 (bug-doc C7) — explicit Join button for
                                                 today's classes, styled as a primary CTA instead of
                                                 hiding the action under the small "LIVE" status pill.
                                                 Non-today rows fall through to Attendance + Edit.
                                                 2026-06-03 (#9) — also hidden once finished. --}}
                                            @if ($isToday && ! $isFinished)
                                                <a class="corp-actions__btn"
                                                   href="{{ route('instructor.live-class', $liveClass->lesson_id) }}"
                                                   style="background:linear-gradient(135deg,#16A34A,#10B981);color:#fff;border:0;padding:7px 14px;border-radius:8px;font-weight:600;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 6px rgba(22,163,74,0.35);"
                                                   title="{{ __('Join the class now') }}">
                                                    <i class="fas fa-video"></i> {{ __('Join') }}
                                                </a>
                                            @endif
                                            <a class="corp-actions__btn"
                                               href="{{ route('instructor.live-class.attendance', $liveClass->id) }}"
                                               title="{{ __('Attendance') }}">
                                                <i class="fas fa-users"></i>
                                            </a>
                                            <a class="corp-actions__btn"
                                               href="{{ route('instructor.live-class.edit', $liveClass->id) }}"
                                               title="{{ __('Edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($liveClasses->hasPages())
                    <div style="padding:14px 18px; border-top:1px solid var(--corp-line-soft); display:flex; justify-content:center;">
                        {{ $liveClasses->links() }}
                    </div>
                @endif
            @else
                <div class="corp-form-card__body">
                    <div class="corp-empty">
                        <div class="corp-empty__icon"><i class="fas fa-{{ !empty($filters['active']) ? 'search' : 'video' }}"></i></div>
                        @if (!empty($filters['active']))
                            <div class="corp-empty__title">{{ __('No live classes match your filters') }}</div>
                            <div class="corp-empty__hint">
                                {{ __('Try a different search term, status or date.') }}
                                <a href="{{ route('instructor.live-classes.index') }}">{{ __('Clear filters') }}</a>
                            </div>
                        @else
                            <div class="corp-empty__title">{{ __('No live classes scheduled') }}</div>
                            <div class="corp-empty__hint">
                                {{ __('Click "Add Live Class" to schedule one or "Instant Live Class" to start a Zoom meeting now.') }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

    </div>


    {{-- ========================================================
     ADD LIVE CLASS MODAL
======================================================== --}}
    <div class="modal fade" id="batchModal" tabindex="-1" aria-labelledby="batchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content lc-modal">

                {{-- Header --}}
                <div class="modal-header lc-modal__header">
                    <div class="lc-modal__header-inner">
                        <div class="lc-modal__icon-wrap">
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                <rect x="1" y="3" width="14" height="10" rx="2" stroke="#185FA5"
                                    stroke-width="1.4" />
                                <path d="M6 6.5l4 2-4 2V6.5z" fill="#185FA5" />
                            </svg>
                        </div>
                        <div>
                            <h5 class="modal-title lc-modal__title" id="batchModalLabel">
                                {{ __('Add New Live Class') }}
                            </h5>
                            <p class="lc-modal__subtitle">
                                {{ __('Schedule a session for your students') }}
                            </p>
                        </div>
                    </div>
                    <button type="button" class="lc-modal__close btn-close" data-bs-dismiss="modal"
                        aria-label="{{ __('Close') }}">X</button>
                </div>

                {{-- Form --}}
                <form id="batchForm" novalidate>
                    @csrf

                    <div class="modal-body lc-modal__body">

                        {{-- Row 1: Title & Course --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="live_class_title" class="lc-field__label">
                                    {{ __('Live Class Title') }}
                                    <span class="lc-field__required">*</span>
                                </label>
                                <input type="text" name="live_class_title" id="live_class_title"
                                    class="form-control lc-field__input"
                                    placeholder="{{ __('e.g. Morning Momentum Session') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="course_id" class="lc-field__label">
                                    {{ __('Course') }}
                                    <span class="lc-field__required">*</span>
                                </label>
                                <select name="course_id" id="course_id" class="form-control lc-field__input" required>
                                    <option value="">{{ __('Select a course') }}</option>
                                    @foreach ($courses as $course)
                                        <option value="{{ $course->id }}">{{ $course->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Row 2: Batch (full width)
                             Audit 2026-05-19 phase 4 — SRS-CLC-001:
                             Chapter field removed from the UI. Live class
                             is anchored to Course + Batch + Zoom only.
                             Server auto-resolves a default chapter for
                             the supporting lesson row (LiveClassController::
                             resolveDefaultChapter). --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label for="batch_id" class="lc-field__label">
                                    {{ __('Batch') }}
                                    <span class="lc-field__required">*</span>
                                </label>
                                <select name="batch_id" id="batch_id" class="form-control lc-field__input" required
                                    disabled>
                                    <option value="">{{ __('Select a batch') }}</option>
                                </select>
                            </div>
                        </div>

                        {{-- Session Details Card --}}
                        <div class="lc-session-card mb-3">
                            <p class="lc-session-card__label">{{ __('Session Details') }}</p>
                            <div class="row g-3">

                                <div class="col-md-4">
                                    <label for="live_type" class="lc-field__label">
                                        {{ __('Live Platform') }}
                                        <span class="lc-field__required">*</span>
                                    </label>
                                    <select name="live_type" id="live_type" class="form-select lc-field__input"
                                        onchange="updatePlatformBadge(this.value)">
                                        @foreach (config('course.live_types') as $key => $value)
                                            <option value="{{ $key }}">{{ $value }}</option>
                                        @endforeach
                                    </select>
                                    <span class="lc-platform-badge" id="platform-badge">
                                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                            <circle cx="5" cy="5" r="4.5" stroke="#185FA5"
                                                stroke-width="1" />
                                            <path d="M3 5l1.5 1.5L7.5 3" stroke="#185FA5" stroke-width="1.2"
                                                stroke-linecap="round" />
                                        </svg>
                                        <span id="platform-badge-text">{{ __('Platform selected') }}</span>
                                    </span>
                                </div>

                                <div class="col-md-4">
                                    <label for="start_time" class="lc-field__label">
                                        {{ __('Start Time') }}
                                        <span class="lc-field__required">*</span>
                                    </label>
                                    <input type="datetime-local" name="start_time" id="start_time"
                                        class="form-control lc-field__input" required>
                                </div>

                                <div class="col-md-4">
                                    <label for="duration" class="lc-field__label">
                                        {{ __('Duration') }}
                                        <span class="lc-field__required">*</span>
                                    </label>
                                    <div class="lc-duration">
                                        <input type="number" name="duration" id="duration"
                                            class="form-control lc-field__input" placeholder="60" min="5"
                                            required>
                                        <span class="lc-duration__suffix">min</span>
                                    </div>
                                </div>

                            </div>
                        </div>

                        {{-- Description --}}
                        <div class="mb-3">
                            <label for="description" class="lc-field__label">
                                {{ __('Description') }}
                                <span class="lc-field__optional">({{ __('optional') }})</span>
                            </label>
                            <input type="text" name="description" id="description"
                                class="form-control lc-field__input"
                                placeholder="{{ __('Briefly describe what students will cover in this session...') }}">
                        </div>

                        {{-- Notify Students --}}
                        <div class="lc-notify-card">
                            <input type="checkbox" class="form-check-input lc-notify-card__checkbox"
                                name="student_mail_sent" id="student_mail_sent">
                            <label for="student_mail_sent" class="lc-notify-card__label">
                                <span class="lc-notify-card__title">
                                    {{ __('Notify all enrolled students') }}
                                </span>
                                <span class="lc-notify-card__desc">
                                    {{ __('An email invite with the session link will be sent immediately after saving.') }}
                                </span>
                            </label>
                        </div>

                    </div>

                    {{-- Footer --}}
                    <div class="modal-footer lc-modal__footer">
                        <span class="lc-modal__footer-hint">
                            {{ __('Fields marked') }}
                            <span class="lc-field__required">*</span>
                            {{ __('are required') }}
                        </span>
                        <div class="lc-modal__footer-actions">
                            <button type="button" class="lc-btn lc-btn--cancel" data-bs-dismiss="modal">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="lc-btn lc-btn--save">
                                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                    <path d="M2 6.5l3.5 3.5 5.5-6" stroke="#fff" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ __('Save Class') }}
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>

    {{-- ============================================================
         Instant Live Class Modal — audit 2026-05-19 phase 4
         Minimal form: Course + Batch + (optional) duration.
         No title, no date, no chapter. POSTs to live-class.instant.
         ============================================================ --}}
    <div class="modal fade" id="instantModal" tabindex="-1" aria-labelledby="instantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content lc-modal">
                <div class="modal-header lc-modal__header" style="border-left:4px solid #10b981;">
                    <div class="d-flex align-items-center gap-2">
                        <span style="font-size:24px; color:#10b981;"><i class="fas fa-bolt"></i></span>
                        <div>
                            <h5 class="modal-title mb-0" id="instantModalLabel">{{ __('Start Instant Live Class') }}</h5>
                            <small class="text-muted">{{ __('Coach + Batch → Zoom meeting → start') }}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="instantForm" method="POST" action="{{ route('instructor.live-class.instant') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="instant_course_id" class="lc-field__label">
                                    {{ __('Course') }} <span class="lc-field__required">*</span>
                                </label>
                                <select name="course_id" id="instant_course_id" class="form-control lc-field__input" required>
                                    <option value="">{{ __('Select a course') }}</option>
                                    @foreach ($courses as $course)
                                        <option value="{{ $course->id }}">{{ $course->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label for="instant_batch_id" class="lc-field__label">
                                    {{ __('Batch') }} <span class="lc-field__required">*</span>
                                </label>
                                <select name="batch_id" id="instant_batch_id" class="form-control lc-field__input" required disabled>
                                    <option value="">{{ __('Select a course first') }}</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label for="instant_duration" class="lc-field__label">
                                    {{ __('Planned duration (minutes)') }}
                                </label>
                                <input type="number" name="duration" id="instant_duration"
                                       class="form-control lc-field__input" value="60" min="5" max="480">
                                <small class="text-muted">{{ __('You can always end the meeting earlier.') }}</small>
                            </div>
                        </div>

                        <div class="alert alert-success mt-3 mb-0" style="font-size:12px;">
                            <i class="fas fa-info-circle"></i>
                            {{ __("As soon as you click 'Start Now', Zoom will create a meeting and you'll be redirected to host it.") }}
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" id="instantSubmit" class="btn btn-success">
                            <i class="fas fa-bolt"></i> {{ __('Start Now') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection


{{-- ========================================================
     STYLES
======================================================== --}}
@push('styles')
    <style>
        /* live button css   */
        /* LIVE pulse badge */
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 6px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: #1ca86b;
            background: #e8fff3;
            border: 1px solid #9ee6c5;
        }

        /* red live dot */
        .live-dot {
            width: 8px;
            height: 8px;
            background: #1ca86b;
            border-radius: 50%;
            position: relative;
        }

        /* pulse animation */
        .live-dot::after {
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #1ca86b;
            top: 0;
            left: 0;
            animation: livePulse 1.5s infinite;
        }

        @keyframes livePulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            70% {
                transform: scale(3);
                opacity: 0;
            }

            100% {
                opacity: 0;
            }
        }

        /* "X in meeting" attendance pulse — shown next to LIVE badge
           when at least one user has an open attendance row. */
        .live-attendance-pulse {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ecfdf5;
            color: #065f46;
            font-weight: 600;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 999px;
            margin-left: 6px;
            border: 1px solid #a7f3d0;
        }
        .live-attendance-dot {
            width: 6px; height: 6px;
            background: #16a34a;
            border-radius: 50%;
            position: relative;
        }
        .live-attendance-dot::after {
            content: '';
            position: absolute;
            inset: 0;
            background: #16a34a;
            border-radius: 50%;
            animation: livePulse 1.5s infinite;
        }
        .live-attendance-count {
            font-weight: 700;
            font-size: 12px;
            color: #047857;
        }

        /* ── Edit button (table) ─────────────────────────── */
        .edit-btn-style {
            border: 2px solid #4fdc9a;
            background: #ffffff !important;
            border-radius: 16px;
            padding: 8px 8px;
            color: #36c3ad;
            margin-left: 3px;
        }

        .edit-btn-style i {
            color: #36c3ad;
        }

        .edit-btn-style:hover {
            color: #0ccdb0 !important;
        }

        .live-class-table a {
            line-height: 14px !important;
        }

        .live-class {
            width: auto !important;
        }

        /* ── Modal shell ─────────────────────────────────── */
        .lc-modal {
            border-radius: 16px;
            border: none;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        /* ── Modal header ────────────────────────────────── */
        .lc-modal__header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            background: #fff;
            align-items: flex-start;
        }

        .lc-modal__header-inner {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lc-modal__icon-wrap {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #00c896, #0e9de8);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(0, 200, 150, 0.25);
            flex-shrink: 0;
        }

        .lc-modal__title {
            font-size: 15px;
            font-weight: 600;
            margin: 0;
            color: #111;
        }

        .lc-modal__subtitle {
            font-size: 12px;
            color: #888;
            margin: 0;
        }

        .lc-modal__close {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid #e5e5e5;
            background: #f7f7f7;
            opacity: 1;
            font-size: 12px;
            flex-shrink: 0;
            margin-left: auto;
        }

        /* ── Modal body ──────────────────────────────────── */
        .lc-modal__body {
            padding: 1.5rem;
            background: #fff;
        }

        /* ── Field labels ────────────────────────────────── */
        .lc-field__label {
            font-size: 12px;
            font-weight: 500;
            color: #666;
            margin-bottom: 5px;
            display: block;
        }

        .lc-field__required {
            color: #D85A30;
        }

        .lc-field__optional {
            font-size: 11px;
            color: #aaa;
            font-weight: 400;
        }

        /* ── Inputs & selects ────────────────────────────── */
        .lc-field__input {
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid #e5e5e5;
            padding: 9px 12px;
            color: #111;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .lc-field__input:focus {
            border-color: #185FA5;
            box-shadow: 0 0 0 3px rgba(24, 95, 165, 0.1);
            outline: none;
        }

        .lc-field__input::placeholder {
            color: #bbb;
            font-size: 13px;
        }

        .lc-field__input:disabled {
            background: #f8f9fa;
            color: #aaa;
            cursor: not-allowed;
        }

        /* ── Duration wrapper ────────────────────────────── */
        .lc-duration {
            position: relative;
        }

        .lc-duration .lc-field__input {
            padding-right: 42px;
        }

        .lc-duration__suffix {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 11px;
            color: #aaa;
            pointer-events: none;
            font-weight: 500;
        }

        /* ── Session details card ────────────────────────── */
        .lc-session-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem 1.25rem;
        }

        .lc-session-card__label {
            font-size: 11px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 0 0 12px;
        }

        /* ── Platform badge ──────────────────────────────── */
        .lc-platform-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 500;
            background: #E6F1FB;
            color: #185FA5;
            padding: 3px 8px;
            border-radius: 20px;
            margin-top: 6px;
        }

        /* ── Notify card ─────────────────────────────────── */
        .lc-notify-card {
            background: #FAEEDA;
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .lc-notify-card__checkbox {
            margin-top: 2px;
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #BA7517;
            flex-shrink: 0;
        }

        .lc-notify-card__label {
            cursor: pointer;
            margin: 0;
        }

        .lc-notify-card__title {
            font-size: 13px;
            font-weight: 600;
            color: #633806;
            display: block;
        }

        .lc-notify-card__desc {
            font-size: 12px;
            color: #854F0B;
            line-height: 1.4;
            display: block;
            margin-top: 1px;
        }

        /* ── Modal footer ────────────────────────────────── */
        .lc-modal__footer {
            padding: 1rem 1.5rem;
            background: #fff;
            border-top: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .lc-modal__footer-hint {
            font-size: 12px;
            color: #aaa;
        }

        .lc-modal__footer-actions {
            display: flex;
            gap: 8px;
        }

        /* ── Buttons ─────────────────────────────────────── */
        .lc-btn {
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: background 0.2s ease;
        }

        .lc-btn--cancel {
            border: 1px solid #e5e5e5;
            background: #f7f7f7;
            color: #444;
        }

        .lc-btn--cancel:hover {
            background: #efefef;
        }

        .lc-btn--save {
            background: linear-gradient(135deg, #04cb99, #00b085);
            color: #fff;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
        }

        .lc-btn--save:hover {
            background: linear-gradient(135deg, #04cb99, #00b085);
        }

        .lc-btn--save button:hover {
            color: #fff !important;
        }

        .lc-btn--save:disabled {
            background: #7aaadb;
            cursor: not-allowed;
        }

        /* ── Validation error text ───────────────────────── */
        .lc-error-text {
            font-size: 12px;
            color: #D85A30;
            display: block;
            margin-top: 4px;
        }

        /* ── Suppress Bootstrap modal backdrop ───────────── */
        .modal-backdrop {
            display: none !important;
        }
    </style>

    <style>
        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
        /* Filter bar */
        html[data-theme="dark"] #liveClasses .lc-filter__search i { color:#94a3b8; }
        html[data-theme="dark"] #liveClasses .lc-filter__search input { border-color:#2a3a55; }
        html[data-theme="dark"] #liveClasses .lc-filter__select,
        html[data-theme="dark"] #liveClasses .lc-filter__date { border-color:#2a3a55; background:#1e293b; color:#e2e8f0; }
        /* Table edit button */
        html[data-theme="dark"] .edit-btn-style { background:#1e293b !important; }
        /* Modal shell + fields */
        html[data-theme="dark"] .lc-modal__header { background:#1e293b; border-bottom-color:#2a3a55; }
        html[data-theme="dark"] .lc-modal__title { color:#e2e8f0; }
        html[data-theme="dark"] .lc-modal__subtitle { color:#94a3b8; }
        html[data-theme="dark"] .lc-modal__close { border-color:#2a3a55; background:#22304a; }
        html[data-theme="dark"] .lc-modal__body { background:#1e293b; }
        html[data-theme="dark"] .lc-field__label { color:#94a3b8; }
        html[data-theme="dark"] .lc-field__optional { color:#94a3b8; }
        html[data-theme="dark"] .lc-field__input { border-color:#2a3a55; color:#e2e8f0; }
        html[data-theme="dark"] .lc-field__input::placeholder { color:#94a3b8; }
        html[data-theme="dark"] .lc-field__input:disabled { background:#17233a; color:#94a3b8; }
        html[data-theme="dark"] .lc-duration__suffix { color:#94a3b8; }
        html[data-theme="dark"] .lc-session-card { background:#17233a; }
        html[data-theme="dark"] .lc-session-card__label { color:#94a3b8; }
        html[data-theme="dark"] .lc-modal__footer { background:#1e293b; border-top-color:#2a3a55; }
        html[data-theme="dark"] .lc-modal__footer-hint { color:#94a3b8; }
        html[data-theme="dark"] .lc-btn--cancel { border-color:#2a3a55; background:#22304a; color:#e2e8f0; }
        html[data-theme="dark"] .lc-btn--cancel:hover { background:#2a3a55; }
    </style>
@endpush


{{-- ========================================================
     SCRIPTS
======================================================== --}}
@push('scripts')
    <script>
        // Live-attendance pulse poller — every 30s fetches the
        // currently-in count for each live class on this page and
        // updates the inline pulse badges. Doesn't reload the table
        // or paginator; pure progressive enhancement so the page is
        // still useful if this script fails or is blocked.
        (function () {
            var endpoint = @json(route('instructor.live-classes.live-counts'));
            var INTERVAL_MS = 30000;
            var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function refresh() {
                fetch(endpoint, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf || '',
                    },
                }).then(function (r) { return r.ok ? r.json() : null; })
                  .then(function (data) {
                    if (!data || !data.counts) return;
                    document.querySelectorAll('.live-attendance-pulse').forEach(function (el) {
                        var id = el.getAttribute('data-live-class-id');
                        var count = parseInt(data.counts[id] || 0, 10);
                        var countEl = el.querySelector('.live-attendance-count');
                        if (countEl) countEl.textContent = String(count);
                        if (count > 0) el.classList.remove('d-none');
                        else           el.classList.add('d-none');
                    });
                  })
                  .catch(function () { /* silent — polling is best-effort */ });
            }

            // First refresh immediately so server-rendered count + DOM stay in
            // sync, then on an interval. Stop polling when the tab is hidden
            // so we don't burn server requests for an unattended dashboard.
            var timer = null;
            function start() {
                refresh();
                timer = setInterval(refresh, INTERVAL_MS);
            }
            function stop() {
                if (timer) { clearInterval(timer); timer = null; }
            }
            document.addEventListener('visibilitychange', function () {
                document.hidden ? stop() : start();
            });
            if (!document.hidden) start();
        })();

        $(document).ready(function() {

            // ── Helpers ─────────────────────────────────────────────
            function showFieldError($input, message) {
                $input.addClass('is-invalid');
                $input.closest('.col-md-4, .col-md-6, .col-md-12, .lc-duration')
                    .append('<span class="lc-error-text">' + message + '</span>');
            }

            function clearFormErrors() {
                $('#batchForm .lc-error-text').remove();
                $('#batchForm .is-invalid').removeClass('is-invalid');
            }

            function setLoadingState(isLoading) {
                const $btn = $('#batchForm .lc-btn--save');
                if (isLoading) {
                    $btn.prop('disabled', true)
                        .data('original-html', $btn.html())
                        .html(
                            '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> {{ __('Saving...') }}'
                            );
                } else {
                    $btn.prop('disabled', false)
                        .html($btn.data('original-html'));
                }
            }

            function resetModal() {
                clearFormErrors();
                $('#batchForm')[0].reset();
                // Audit 2026-05-19 phase 4 — Chapter dropdown removed,
                // only Batch needs resetting now.
                $('#batch_id').html('<option value="">{{ __('Select a batch') }}</option>').prop('disabled', true);
                updatePlatformBadge($('#live_type').val());
            }

            // ── Platform badge ───────────────────────────────────────
            window.updatePlatformBadge = function(val) {
                const labels = {
                    zoom: '{{ __('Zoom connected') }}',
                    meet: '{{ __('Google Meet connected') }}',
                    teams: '{{ __('MS Teams connected') }}',
                };
                const $el = $('#platform-badge-text');
                $el.text(labels[val] ?? '{{ __('Platform selected') }}');
            };

            // Init badge on load
            updatePlatformBadge($('#live_type').val());

            // ── Course → Batches (one request) ───────────────────────
            // Audit 2026-05-19 phase 4 — Chapter dropdown removed per
            // SRS-CLC-001. We still hit the same endpoint (which also
            // returns `chapters` in the payload) but only consume
            // `batches`. The chapter is resolved server-side at submit.
            $('#course_id').on('change', function() {
                const courseId = $(this).val();
                const $batch = $('#batch_id');

                $batch.html('<option value="">{{ __('Loading...') }}</option>').prop('disabled', true);

                if (!courseId) {
                    $batch.html('<option value="">{{ __('Select a batch') }}</option>');
                    return;
                }

                $.ajax({
                    url: "{{ route('instructor.get-batches-by-course', '') }}/" + courseId,
                    method: 'GET',
                    success: function(data) {
                        // Batches only — chapters payload intentionally ignored.
                        let batchOptions =
                            '<option value="">{{ __('Select a batch') }}</option>';
                        if (data.batches && data.batches.length) {
                            $.each(data.batches, function(i, batch) {
                                // 2026-07-06 — carry the batch start datetime so
                                // selecting a batch can auto-fill Start Time.
                                batchOptions += '<option value="' + batch.id + '" data-start="' +
                                    (batch.start_datetime_local || '') + '">' +
                                    batch.title + '</option>';
                            });
                            $batch.prop('disabled', false);
                        } else {
                            batchOptions =
                                '<option value="">{{ __('No batches found') }}</option>';
                        }
                        $batch.html(batchOptions);
                    },
                    error: function() {
                        $batch.html('<option value="">{{ __('Failed to load') }}</option>');
                        toastr.error(
                            '{{ __('Could not load batches. Please try again.') }}'
                            );
                    }
                });
            });

            // 2026-07-06 (Role Permission Test doc) — auto-fill Start Time from
            // the selected batch. The field stays fully editable; we only
            // pre-fill it (never overwrite a time the user already typed).
            $('#batch_id').on('change', function() {
                const start = $(this).find('option:selected').attr('data-start');
                const $start = $('#start_time');
                if (start && !$start.val()) {
                    $start.val(start);
                }
            });

            // ── Form submit ──────────────────────────────────────────
            $('#batchForm').on('submit', function(e) {
                e.preventDefault();

                // Client-side duration guard
                const duration = parseInt($('#duration').val(), 10);
                if (isNaN(duration) || duration < 1) {
                    showFieldError($('#duration'), '{{ __('Duration must be a positive number.') }}');
                    return;
                }

                clearFormErrors();
                setLoadingState(true);

                $.ajax({
                    url: "{{ route('instructor.live-class.store') }}",
                    method: 'POST',
                    data: new FormData(this),
                    processData: false,
                    contentType: false,

                    success: function(response) {
                        if (response.status === 'success') {
                            toastr.success(response.message);
                            $('#batchModal').modal('hide');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            toastr.error(response.message ||
                                '{{ __('Something went wrong.') }}');
                        }
                    },

                    error: function(xhr) {
                        switch (xhr.status) {
                            case 422:
                                const errors = xhr.responseJSON?.errors ?? {};
                                if (!Object.keys(errors).length) {
                                    // No field errors → surface the backend's
                                    // own message (e.g. the time-slot conflict
                                    // notice) before the generic fallback.
                                    toastr.error(
                                        xhr.responseJSON?.message
                                        || '{{ __('Validation failed. Please check your inputs.') }}',
                                        '', { timeOut: 8000, extendedTimeOut: 3000 }
                                        );
                                    break;
                                }
                                $.each(errors, function(key, messages) {
                                    showFieldError($('[name="' + key + '"]'), messages[
                                        0]);
                                });
                                // Scroll to first invalid field
                                const $first = $('#batchForm .is-invalid').first();
                                if ($first.length) {
                                    $first[0].scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'center'
                                    });
                                }
                                break;

                            case 403:
                                toastr.error(
                                    '{{ __('You are not authorized to perform this action.') }}'
                                    );
                                break;

                            case 419:
                                toastr.error(
                                    '{{ __('Session expired. Please refresh the page.') }}'
                                    );
                                break;

                            case 500:
                                toastr.error(
                                    '{{ __('Server error. Please try again later.') }}');
                                break;

                            case 400:
                                // 2026-05-29 Doc-C-LiveClassZoomCreds:
                                // backend returns a specific message for
                                // missing Zoom credentials + similar wiring
                                // issues. Surface it as-is so the coach
                                // knows exactly what to fix. Add an inline
                                // CTA to the Zoom Settings if the message
                                // looks like a credentials problem.
                                {
                                    const msg = xhr.responseJSON?.error
                                        || xhr.responseJSON?.message
                                        || '{{ __('Bad request. Please verify your inputs.') }}';
                                    toastr.error(msg, '', { timeOut: 8000, extendedTimeOut: 3000 });
                                    if (/zoom credential|configure zoom/i.test(msg)) {
                                        // Bonus: offer a quick-link to the
                                        // settings page so the operator
                                        // doesn't have to hunt for it.
                                        setTimeout(function () {
                                            toastr.info(
                                                '<a href="{{ route('instructor.zoom-setting.index') }}" style="color:#fff;font-weight:600;text-decoration:underline;">{{ __('Open Zoom Settings →') }}</a>',
                                                '', { allowHtml: true, timeOut: 12000, closeButton: true }
                                            );
                                        }, 400);
                                    }
                                }
                                break;

                            default:
                                // Default: surface the backend-supplied
                                // message before falling back to generic
                                // text. This catches 400/409/etc that the
                                // controller may use with a specific error.
                                {
                                    const msg = xhr.responseJSON?.error
                                        || xhr.responseJSON?.message
                                        || '{{ __('An unexpected error occurred.') }}';
                                    toastr.error(msg);
                                }
                        }
                    },

                    complete: function() {
                        setLoadingState(false);
                    }
                });
            });

            // ── Reset on modal close ─────────────────────────────────
            $('#batchModal').on('hidden.bs.modal', function() {
                resetModal();
            });

            // ── Instant Live Class flow ──────────────────────────────
            // Audit 2026-05-19 phase 4 (post-SRS feedback).
            // Course → Batch reuses the same endpoint as the scheduled
            // modal. Submit POSTs to /instructor/live-class/instant and
            // redirects on success.
            $('#instant_course_id').on('change', function() {
                const courseId = $(this).val();
                const $batch = $('#instant_batch_id');
                $batch.prop('disabled', true)
                    .html('<option value="">{{ __('Loading...') }}</option>');
                if (!courseId) {
                    $batch.html('<option value="">{{ __('Select a course first') }}</option>');
                    return;
                }
                $.ajax({
                    url: "{{ route('instructor.get-batches-by-course', '') }}/" + courseId,
                    method: 'GET',
                    success: function(data) {
                        let opts = '<option value="">{{ __('Select a batch') }}</option>';
                        (data.batches || []).forEach(function(b) {
                            opts += '<option value="' + b.id + '">' + b.title + '</option>';
                        });
                        $batch.html(opts).prop('disabled', false);
                    },
                    error: function() {
                        $batch.html('<option value="">{{ __('Failed to load') }}</option>');
                    }
                });
            });

            $('#instantForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#instantSubmit');
                const original = $btn.html();
                $btn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-1"></span> {{ __("Starting Zoom...") }}'
                );

                $.ajax({
                    url: $(this).attr('action'),
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(res) {
                        if (res.redirect_url) {
                            // Hand off to the existing coach Zoom host page.
                            window.location.href = res.redirect_url;
                        } else {
                            toastr && toastr.success(res.message || '{{ __('Started') }}');
                        }
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).html(original);
                        const data = xhr.responseJSON || {};
                        // Surface the backend's own message first — e.g. the
                        // time-slot conflict notice (409) — then field errors,
                        // then a generic fallback. Keeps the operator informed
                        // of exactly why the start was declined.
                        const firstFieldError = data.errors
                            ? (Object.values(data.errors)[0] || [])[0]
                            : null;
                        const msg = data.message
                            || data.error
                            || firstFieldError
                            || '{{ __('Could not start instant class. Please try again.') }}';
                        toastr && toastr.error(msg, '', { timeOut: 8000, extendedTimeOut: 3000 });
                    }
                });
            });

            // Reset Instant modal on close
            $('#instantModal').on('hidden.bs.modal', function() {
                $('#instantForm')[0].reset();
                $('#instant_batch_id').prop('disabled', true)
                    .html('<option value="">{{ __('Select a course first') }}</option>');
                $('#instantSubmit').prop('disabled', false)
                    .html('<i class="fas fa-bolt"></i> {{ __('Start Now') }}');
            });

        });
    </script>
@endpush
