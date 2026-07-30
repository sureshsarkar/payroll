{{--
    Teacher-tailored dashboard (P1 of teacher panel).
    Rendered by frontend.instructor-dashboard.index when the logged-in
    user is a CoachStaff with role != 'instructor'. Sizes the surface
    to what the teacher actually controls: assigned batches + students
    + their live-class schedule + fee position.

    Required vars:
        $teacherDashboard  array  payload built by InstructorDashboardController::buildTeacherDashboard()
--}}
@include('frontend.instructor-dashboard.settings.partials._corporate')

@php
    $td = $teacherDashboard ?? [];
    $up = $td['upcoming_list'] ?? collect();
    $act = $td['activity'] ?? collect();

    $fmtMoney = function ($v) {
        if (! is_numeric($v)) return '—';
        return '₹' . number_format((float) $v, 0);
    };

    // start_time is stored as a varchar in this schema — best-effort parse.
    $fmtClassTime = function ($raw) {
        if (! $raw) return '—';
        try { return \Carbon\Carbon::parse($raw)->format('D, M j · g:i A'); }
        catch (\Throwable $e) { return (string) $raw; }
    };
@endphp

<div class="corp-page" id="teacherDashboard">

    {{-- Greeting header ──────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>
                {{ __('Welcome back') }},
                <span class="corp-grad-text">{{ userAuth()->name }}</span>
            </h4>
            <p>
                {{ __("Your teaching home. Everything below is scoped to the batches your coach has assigned to you.") }}
            </p>
        </div>
        <div class="corp-header__actions">
            {{-- 2026-07-06 (Role Permission Test doc) — only surface Live Classes to staff who hold it. --}}
            @if (checkPermissionView('live-classes'))
                <a href="{{ route('instructor.live-classes.index') }}" class="btn-corp-primary">
                    <i class="fas fa-video"></i> {{ __('Go to Live Classes') }}
                </a>
            @endif
        </div>
    </div>

    {{-- Headline KPI strip ────────────────────────────────────── --}}
    <div class="corp-kpi">
        <div class="corp-kpi__tile corp-kpi__tile--glow" style="--accent:var(--corp-brand);">
            <div class="corp-kpi__label">{{ __('Assigned Batches') }}</div>
            <div class="corp-kpi__value">{{ number_format($td['assigned_batches'] ?? 0) }}</div>
            <div class="corp-kpi__sub">
                {{ trans_choice('across :n course|across :n courses', $td['assigned_courses'] ?? 0, ['n' => $td['assigned_courses'] ?? 0]) }}
            </div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#10b981;">
            <div class="corp-kpi__label">{{ __('My Students') }}</div>
            <div class="corp-kpi__value">{{ number_format($td['student_count'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('distinct, with active access') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#ef4444;">
            <div class="corp-kpi__label">{{ __("Today's Classes") }}</div>
            <div class="corp-kpi__value" style="color:{{ ($td['today_classes'] ?? 0) > 0 ? '#b91c1c' : '#0f172a' }};">
                {{ number_format($td['today_classes'] ?? 0) }}
            </div>
            <div class="corp-kpi__sub">
                {{ ($td['today_classes'] ?? 0) > 0
                    ? __('check the schedule below')
                    : __('nothing scheduled today') }}
            </div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#f59e0b;">
            <div class="corp-kpi__label">{{ __('Upcoming') }}</div>
            <div class="corp-kpi__value">{{ number_format($td['upcoming_classes'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('not yet ended, today onwards') }}</div>
        </div>
    </div>

    {{-- Secondary KPI strip (counts + money) ───────────────────── --}}
    <div class="corp-kpi" style="margin-top:6px;">
        <div class="corp-kpi__tile" style="--accent:#0ea5e9;">
            <div class="corp-kpi__label">{{ __('Completed Classes') }}</div>
            <div class="corp-kpi__value">{{ number_format($td['completed_classes'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('all-time, your assigned batches') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#92400e;">
            <div class="corp-kpi__label">{{ __('Pending Fees') }}</div>
            <div class="corp-kpi__value" style="color:{{ ($td['pending_fees_amount'] ?? 0) > 0 ? '#92400e' : '#047857' }};">
                {{ $fmtMoney($td['pending_fees_amount'] ?? 0) }}
            </div>
            <div class="corp-kpi__sub">{{ __('outstanding across your batches') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#047857;">
            <div class="corp-kpi__label">{{ __('Collected Fees') }}</div>
            <div class="corp-kpi__value">{{ $fmtMoney($td['collected_fees_amount'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('total received against your batches') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:var(--corp-brand);">
            <div class="corp-kpi__label">{{ __('Quick Actions') }}</div>
            {{-- 2026-07-06 (Role Permission Test doc) — each shortcut only shows if the staff holds the module. --}}
            <div style="margin-top:6px; display:flex; flex-wrap:wrap; gap:6px;">
                @if (checkPermissionView('live-classes'))
                    <a href="{{ route('instructor.live-classes.index') }}"
                       class="corp-pill corp-pill--brand corp-pill--plain"
                       style="text-decoration:none; padding:4px 10px;">
                        <i class="fas fa-bolt" style="font-size:9px;"></i> {{ __('Live') }}
                    </a>
                @endif
                @if (checkPermissionView('announcements'))
                    <a href="{{ route('instructor.announcements.index') }}"
                       class="corp-pill corp-pill--brand corp-pill--plain"
                       style="text-decoration:none; padding:4px 10px;">
                        <i class="fas fa-megaphone" style="font-size:9px;"></i> {{ __('Announce') }}
                    </a>
                @endif
                @if (Route::has('instructor.course-batches.index') && checkPermissionView('course-batches'))
                    <a href="{{ route('instructor.course-batches.index') }}"
                       class="corp-pill corp-pill--brand corp-pill--plain"
                       style="text-decoration:none; padding:4px 10px;">
                        <i class="fas fa-layer-group" style="font-size:9px;"></i> {{ __('Batches') }}
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="corp-2col" style="margin-top:14px;">

        {{-- ───────── Main column ───────── --}}
        <div>
            {{-- Upcoming classes ───────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title">
                        <i class="fas fa-calendar-week" style="color:var(--corp-brand);"></i>
                        {{ __('Upcoming Live Classes') }}
                    </h6>
                    <p class="corp-form-card__sub">
                        {{ __('The next five scheduled classes for your assigned batches. Click through to start or edit.') }}
                    </p>
                </div>
                <div class="corp-table-wrap" style="border:none; border-radius:0;">
                    <table class="corp-table">
                        <thead>
                            <tr>
                                <th>{{ __('When') }}</th>
                                <th>{{ __('Course') }}</th>
                                <th>{{ __('Batch') }}</th>
                                <th>{{ __('Lesson') }}</th>
                                <th style="text-align:right;">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($up as $c)
                                <tr>
                                    <td>
                                        <div style="font-weight:600;">{{ $fmtClassTime($c->start_time) }}</div>
                                        @if (! empty($c->expected_duration_minutes))
                                            <div style="font-size:11px; color:var(--corp-muted);">
                                                {{ $c->expected_duration_minutes }} {{ __('min') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ $c->course_title ?? '—' }}</td>
                                    <td>{{ $c->batch_title ?? '—' }}</td>
                                    <td>{{ $c->lesson_title ?? '—' }}</td>
                                    <td style="text-align:right;">
                                        @if (Route::has('instructor.live-class') && $c->lesson_id)
                                            <a href="{{ route('instructor.live-class', $c->lesson_id) }}"
                                               class="btn-corp-primary"
                                               style="padding:5px 12px; font-size:11px;">
                                                <i class="fas fa-play"></i> {{ __('Start') }}
                                            </a>
                                        @endif
                                        @if (Route::has('instructor.live-class.edit'))
                                            <a href="{{ route('instructor.live-class.edit', $c->id) }}"
                                               class="corp-actions__btn"
                                               title="{{ __('Edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5">
                                    <div class="corp-empty">
                                        <div class="corp-empty__icon"><i class="fas fa-calendar"></i></div>
                                        <div class="corp-empty__title">{{ __('No upcoming classes') }}</div>
                                        <div class="corp-empty__hint">
                                            @if (($td['assigned_batches'] ?? 0) === 0)
                                                {{ __("You don't have any batches assigned yet. Ask your coach to grant access.") }}
                                            @else
                                                {{ __('Schedule a live class from the Live Classes page.') }}
                                            @endif
                                        </div>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recent activity ──────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title">
                        <i class="fas fa-history" style="color:var(--corp-brand);"></i>
                        {{ __('Recent Activity') }}
                    </h6>
                    <p class="corp-form-card__sub">
                        {{ __('Last 8 events across your assigned batches — class ended, fee collected, attendance marked.') }}
                    </p>
                </div>
                <div class="corp-form-card__body">
                    @forelse ($act as $a)
                        <div style="display:flex; gap:12px; padding:10px 0; border-bottom:1px solid var(--corp-line-soft);">
                            <div style="width:34px; height:34px; border-radius:9px; background:var(--corp-brand-bg); color:var(--corp-brand); display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <i class="fas {{ $a->icon ?? 'fa-circle' }}"></i>
                            </div>
                            <div style="flex:1;">
                                <div style="font-size:13px; color:var(--corp-text);">{{ $a->message }}</div>
                                <div style="font-size:11px; color:var(--corp-muted); margin-top:2px;">
                                    @php
                                        $when = $a->when;
                                        try { $when = \Carbon\Carbon::parse($a->when)->diffForHumans(); }
                                        catch (\Throwable $e) { /* leave raw */ }
                                    @endphp
                                    {{ $when }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="corp-empty" style="padding:24px;">
                            <div class="corp-empty__icon"><i class="fas fa-clock"></i></div>
                            <div class="corp-empty__title">{{ __('No activity yet') }}</div>
                            <div class="corp-empty__hint">
                                {{ __('As classes end and fees come in, the latest events will land here.') }}
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ───────── Side rail ───────── --}}
        <aside>
            <div class="corp-context">
                <div class="corp-context__head"><i class="fas fa-info-circle"></i> {{ __('Your scope') }}</div>
                <div class="corp-context__body">
                    <div class="corp-meta-row">
                        <span class="corp-meta-row__k">{{ __('Batches assigned') }}</span>
                        <span class="corp-meta-row__v">{{ number_format($td['assigned_batches'] ?? 0) }}</span>
                    </div>
                    <div class="corp-meta-row">
                        <span class="corp-meta-row__k">{{ __('Courses covered') }}</span>
                        <span class="corp-meta-row__v">{{ number_format($td['assigned_courses'] ?? 0) }}</span>
                    </div>
                    <div class="corp-meta-row">
                        <span class="corp-meta-row__k">{{ __('Distinct students') }}</span>
                        <span class="corp-meta-row__v">{{ number_format($td['student_count'] ?? 0) }}</span>
                    </div>
                    <div class="corp-meta-row">
                        <span class="corp-meta-row__k">{{ __('Classes completed') }}</span>
                        <span class="corp-meta-row__v">{{ number_format($td['completed_classes'] ?? 0) }}</span>
                    </div>
                </div>
            </div>

            @if (($td['assigned_batches'] ?? 0) === 0)
                <div class="corp-context">
                    <div class="corp-context__head" style="color:#92400e;">
                        <i class="fas fa-triangle-exclamation"></i> {{ __('No assignments yet') }}
                    </div>
                    <div class="corp-context__body">
                        <p style="margin:0 0 8px;">
                            {{ __("You haven't been assigned to any batches by your coach yet.") }}
                        </p>
                        <p style="margin:0; font-size:12px; color:var(--corp-muted);">
                            {{ __('Once they grant access, the batches will show here and you can start managing live classes, attendance, and announcements.') }}
                        </p>
                    </div>
                </div>
            @else
                <div class="corp-context">
                    <div class="corp-context__head"><i class="fas fa-lightbulb"></i> {{ __('Tips') }}</div>
                    <div class="corp-context__body">
                        <ul style="margin:0;">
                            <li>{{ __('Schedule a class from the Live Classes page — only your assigned batches show in the dropdown.') }}</li>
                            <li>{{ __('Instant Meeting 1:1 starts a Zoom session immediately for any of your assigned batches.') }}</li>
                            <li>{{ __('Send a quick announcement to all students of one of your batches from Announcements.') }}</li>
                            <li>{{ __('Attendance auto-tracks via the Zoom launcher; you can also mark students manually after class.') }}</li>
                        </ul>
                    </div>
                </div>
            @endif
        </aside>
    </div>
</div>
