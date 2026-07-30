@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@php
    function _live_fmt_duration($s) {
        $s = (int) $s;
        if ($s < 60) return $s . 's';
        if ($s < 3600) return floor($s / 60) . 'm ' . ($s % 60) . 's';
        $h = floor($s / 3600); $m = floor(($s % 3600) / 60);
        return $h . 'h ' . $m . 'm';
    }
@endphp
{{-- 2026-07-04 — corp header (recordings panel / attendance tables / JS below unchanged). --}}
@include('frontend.instructor-dashboard.settings.partials._corporate')
<div class="dashboard__content-wrap">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-user-check" style="color:var(--corp-brand);"></i> {{ __('Attendance') }}</h4>
            <p>{{ $liveClass->lesson?->course?->title ?? '' }} — <strong>{{ $liveClass->lesson?->title ?? '—' }}</strong>
               · {{ __('Scheduled') }}: {{ $liveClass->start_time?->format('M d, Y h:i A') ?? '—' }}
               · {{ __('Meeting') }}: <code>{{ $liveClass->meeting_id }}</code></p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.live-class.attendance.export', ['live_class_id' => $liveClass->id]) }}" class="btn-corp-secondary">
                <i class="fas fa-download"></i> {{ __('Download CSV') }}
            </a>
            <a href="{{ route('instructor.live-classes.index') }}" class="btn-corp-secondary">
                <i class="fas fa-arrow-left"></i> {{ __('Back') }}
            </a>
        </div>
    </div>

    {{-- Cloud recordings panel (#9 — 2026-05-12).
         Surfaces Zoom Cloud recordings synced via the SyncLiveClassRecordings
         command so the instructor (and the audit trail) can find them
         next to the attendance log. The play_url opens Zoom's hosted
         player; download_url is the raw MP4/M4A. --}}
    @if ($liveClass->recordings->isNotEmpty())
        <div class="card mb-4" style="border:1px solid #e5e7eb;border-radius:12px;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    🎥 {{ __('Cloud recordings') }}
                    <span class="badge bg-secondary ms-1">{{ $liveClass->recordings->count() }}</span>
                </h5>
                <small class="text-muted">{{ __('From Zoom Cloud sync') }}</small>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach ($liveClass->recordings as $rec)
                        <div class="col-md-6">
                            <div style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#f8fafc;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong style="font-size:13px;">
                                        {{ strtoupper($rec->file_extension ?? $rec->file_type ?? 'MP4') }}
                                    </strong>
                                    @if ($rec->file_size_human ?? null)
                                        <span class="text-muted small">{{ $rec->file_size_human }}</span>
                                    @endif
                                </div>
                                <div class="text-muted small mb-2">
                                    @if ($rec->duration_seconds)
                                        {{ _live_fmt_duration($rec->duration_seconds) }}
                                    @endif
                                    @if ($rec->recording_start)
                                        · {{ \Carbon\Carbon::parse($rec->recording_start)->format('M d h:i A') }}
                                    @endif
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    @if ($rec->play_url)
                                        <a href="{{ $rec->play_url }}" target="_blank" rel="noopener"
                                           class="btn btn-sm btn-primary">▶ {{ __('Play') }}</a>
                                    @endif
                                    @if ($rec->download_url)
                                        <a href="{{ $rec->download_url }}" target="_blank" rel="noopener"
                                           class="btn btn-sm btn-outline-secondary">⬇ {{ __('Download') }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Manual override (#7 — 2026-05-12).
         For tech-failure cases where the launcher didn't log a student
         who was actually present. Inserts a synthetic attendance row
         with is_manual=true and the typed reason. Only shown when there
         are absentees enrolled in the course. --}}
    @if (isset($absentees) && $absentees->isNotEmpty())
        <div class="card mb-4" style="border:1px dashed #f59e0b;border-radius:12px;background:#fffbeb;">
            <div class="card-body">
                <h6 class="mb-2" style="color:#92400e;">
                    ⚙ {{ __('Mark a student present manually') }}
                </h6>
                <p class="text-muted small mb-3">
                    {{ __('Use this when a tech failure prevented the launcher from recording attendance. The override is logged with your name and the reason.') }}
                </p>
                <form method="POST"
                      action="{{ route('instructor.live-class.attendance.manual-mark', ['live_class_id' => $liveClass->id]) }}"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label for="manual-user" class="form-label small text-muted">{{ __('Student') }}</label>
                        <select id="manual-user" name="user_id" class="form-select" required>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($absentees as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="manual-duration" class="form-label small text-muted">{{ __('Minutes attended') }}</label>
                        <input type="number" id="manual-duration" name="duration_min"
                               class="form-control" value="60" min="1" max="480">
                    </div>
                    <div class="col-md-4">
                        <label for="manual-reason" class="form-label small text-muted">{{ __('Reason (required, 3+ chars)') }}</label>
                        <input type="text" id="manual-reason" name="reason"
                               class="form-control" required minlength="3" maxlength="255"
                               placeholder="{{ __('e.g. audio failed on join — WhatsApp screenshot in chat') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-warning w-100">
                            {{ __('Mark present') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Per-user summary --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('Per-user summary') }}</h5>
            <span class="badge bg-secondary">{{ $perUser->count() }} {{ __('attendees') }}</span>
        </div>
        <div class="card-body p-0">
            @if ($perUser->isEmpty())
                <p class="text-muted text-center p-4">
                    {{ __('No attendance recorded yet. The launcher posts to /live-class/{id}/attendance on connection-change events.') }}
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('User') }}</th>
                                <th>{{ __('Role') }}</th>
                                <th>{{ __('First join') }}</th>
                                <th>{{ __('Sessions') }}</th>
                                <th>{{ __('Total time') }}</th>
                                <th>{{ __('Late by') }}</th>
                                <th>{{ __('Left early by') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($perUser as $u)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $u->user?->name ?? '—' }}</div>
                                        <div class="text-muted small">{{ $u->user?->email ?? '' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $u->role === 'host' ? 'bg-primary' : 'bg-secondary' }}">
                                            {{ ucfirst($u->role) }}
                                        </span>
                                    </td>
                                    <td title="{{ $u->first_joined_at }}">
                                        {{ \Carbon\Carbon::parse($u->first_joined_at)->format('h:i A') }}
                                    </td>
                                    <td>{{ $u->sessions }}</td>
                                    <td>{{ _live_fmt_duration($u->total_seconds) }}</td>
                                    {{-- Late by — colored badge based on how late.
                                         Threshold tiers: 0 = on time (green),
                                         <5min = mild (no color), ≥5min = warning. --}}
                                    <td>
                                        @if ($u->late_seconds === null)
                                            <span class="text-muted small">—</span>
                                        @elseif ($u->late_seconds === 0)
                                            <span class="badge bg-success">{{ __('On time') }}</span>
                                        @elseif ($u->late_seconds < 300)
                                            <span class="text-muted">{{ _live_fmt_duration($u->late_seconds) }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark"
                                                  title="{{ __('Joined :t after start', ['t' => _live_fmt_duration($u->late_seconds)]) }}">
                                                ⚠ {{ _live_fmt_duration($u->late_seconds) }}
                                            </span>
                                        @endif
                                    </td>
                                    {{-- Left early by — same tier system as late. --}}
                                    <td>
                                        @if ($u->early_leave_seconds === null)
                                            <span class="text-muted small">—</span>
                                        @elseif ($u->early_leave_seconds === 0)
                                            <span class="badge bg-success">{{ __('Stayed') }}</span>
                                        @elseif ($u->early_leave_seconds < 300)
                                            <span class="text-muted">{{ _live_fmt_duration($u->early_leave_seconds) }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark"
                                                  title="{{ __('Left :t before scheduled end', ['t' => _live_fmt_duration($u->early_leave_seconds)]) }}">
                                                ⚠ {{ _live_fmt_duration($u->early_leave_seconds) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($u->is_currently_in)
                                            <span class="badge bg-success">{{ __('In meeting') }}</span>
                                        @else
                                            <span class="badge bg-light text-dark">{{ __('Left') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Raw session log — useful for debugging the "did they actually
         join?" question when totals look weird. --}}
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Session log') }}</h5>
        </div>
        <div class="card-body p-0">
            @if ($attendances->isEmpty())
                <p class="text-muted text-center p-4">{{ __('No sessions yet.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('User') }}</th>
                                <th>{{ __('Joined') }}</th>
                                <th>{{ __('Left') }}</th>
                                <th>{{ __('Duration') }}</th>
                                <th>{{ __('IP') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attendances as $a)
                                <tr @class(['table-warning' => $a->is_manual])>
                                    <td>
                                        {{ $a->user?->name ?? '—' }}
                                        @if ($a->is_manual)
                                            <span class="badge bg-warning text-dark ms-1"
                                                  title="{{ $a->manual_reason }}">
                                                ⚙ {{ __('Manual') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td title="{{ $a->joined_at }}">{{ $a->joined_at?->format('M d h:i:s A') }}</td>
                                    <td title="{{ $a->left_at }}">
                                        @if ($a->left_at)
                                            {{ $a->left_at->format('h:i:s A') }}
                                        @else
                                            <span class="text-success">{{ __('still in meeting') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $a->duration_seconds ? _live_fmt_duration($a->duration_seconds) : '—' }}</td>
                                    <td class="text-muted small">{{ $a->client_ip }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
