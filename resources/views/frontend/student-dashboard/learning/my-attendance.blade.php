@extends('frontend.student-dashboard.layouts.master')

@php
    // Local helper — matches the format used on the per-lesson instructor
    // page so the same duration reads consistently across the LMS.
    function _myatt_fmt_duration($s) {
        $s = (int) $s;
        if ($s <= 0)    return '—';
        if ($s < 60)    return $s . 's';
        if ($s < 3600)  return floor($s / 60) . 'm ' . ($s % 60) . 's';
        $h = floor($s / 3600); $m = floor(($s % 3600) / 60);
        return $h . 'h ' . $m . 'm';
    }
@endphp

@section('dashboard-contents')
<div class="dashboard__content-wrap">
    <div class="dashboard__content-title d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="title">{{ __('My attendance') }}</h4>
            <p class="text-muted mb-0">{{ $course->title }}</p>
        </div>
        <a href="{{ route('student.enrolled-courses') }}" class="btn">
            ← {{ __('Back to courses') }}
        </a>
    </div>

    {{-- Headline summary — single read tells the student where they stand.
         Four mutually-exclusive states:
           (a) threshold = 0      → feature disabled by instructor
           (b) no live classes    → nothing to attend yet
           (c) atRisk = true      → below threshold; red banner
           (d) atRisk = false     → on track; green banner --}}
    @if ($threshold === 0)
        <div class="alert alert-secondary">
            ℹ {{ __('The instructor has not set an attendance requirement for this course.') }}
        </div>
    @elseif ($totalClasses === 0)
        <div class="alert alert-info">
            ℹ {{ __('No live classes have been scheduled for this course yet.') }}
        </div>
    @elseif ($atRisk)
        <div class="alert alert-danger">
            ⚠ {{ __('You are below the') }} <strong>{{ $threshold }}%</strong>
            {{ __('attendance threshold for this course.') }}
            {{ __('You\'ve attended :a of :t live classes (:p%).', [
                'a' => $attendedCount, 't' => $totalClasses, 'p' => $percent,
            ]) }}
        </div>
    @else
        <div class="alert alert-success">
            ✓ {{ __('You are on track.') }}
            {{ __('You\'ve attended :a of :t live classes (:p%), meeting the :t_pct% threshold.', [
                'a'      => $attendedCount,
                't'      => $totalClasses,
                'p'      => $percent,
                't_pct'  => $threshold,
            ]) }}
        </div>
    @endif

    {{-- Quick-glance stat row. Mirrors the instructor watchlist header
         layout so screenshots are visually consistent. --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <div class="text-muted small">{{ __('Total classes') }}</div>
                    <div class="fs-3 fw-bold">{{ $totalClasses }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <div class="text-muted small">{{ __('Attended') }}</div>
                    <div class="fs-3 fw-bold">{{ $attendedCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <div class="text-muted small">{{ __('Attendance') }}</div>
                    <div class="fs-3 fw-bold">{{ $percent }}%</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <div class="text-muted small">{{ __('Threshold') }}</div>
                    <div class="fs-3 fw-bold">{{ $threshold }}%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Class-by-class breakdown') }}</h5>
        </div>
        <div class="card-body p-0">
            @if ($rows->isEmpty())
                <p class="text-muted text-center p-4">
                    {{ __('No live classes have been scheduled yet.') }}
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Live class') }}</th>
                                <th>{{ __('Scheduled') }}</th>
                                <th class="text-center">{{ __('Sessions') }}</th>
                                <th class="text-center">{{ __('Time in class') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $r->lesson_title }}</div>
                                    </td>
                                    <td>
                                        @if ($r->start_time)
                                            <span title="{{ \Illuminate\Support\Carbon::parse($r->start_time)->format('Y-m-d H:i') }}">
                                                {{ \Illuminate\Support\Carbon::parse($r->start_time)->format('M d, Y h:i A') }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $r->sessions ?: 0 }}</td>
                                    <td class="text-center">{{ _myatt_fmt_duration($r->duration_seconds) }}</td>
                                    <td>
                                        @if ($r->attended)
                                            <span class="badge bg-success">{{ __('Attended') }}</span>
                                        @elseif ($r->sessions > 0)
                                            {{-- Joined but didn't stay ≥ 60s. Surfaced so the
                                                 student understands why a class they "joined"
                                                 doesn't count. --}}
                                            <span class="badge bg-warning text-dark">
                                                {{ __('Joined briefly') }}
                                            </span>
                                        @else
                                            <span class="badge bg-light text-dark">{{ __('Missed') }}</span>
                                        @endif
                                        {{-- #9 cloud recording link (2026-05-12) — for any
                                             class with a recording, give the student a one-click
                                             catch-up regardless of attendance status. Most
                                             valuable on "Missed" rows. --}}
                                        @if ($r->recording_url)
                                            <a href="{{ $r->recording_url }}" target="_blank" rel="noopener"
                                               class="badge"
                                               style="background:#10b981;color:#fff;text-decoration:none;margin-left:4px;"
                                               title="{{ __('Watch recording') }}">
                                                ▶ {{ __('Recording') }}
                                            </a>
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
</div>
@endsection
