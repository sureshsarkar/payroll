@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard__content-wrap mt-3">
    <div class="dashboard__content-title">
        <h4 class="title"><i class="fas fa-clipboard-check"></i> {{ __('My Attendance') }}</h4>
    </div>

    {{-- Audit 2026-05-18 Req 1 — at-a-glance counters --}}
    <div class="row g-2 mb-3">
        <div class="col-sm-4 col-12">
            <div class="card text-center" style="padding:14px; border-left:4px solid #10b981;">
                <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Total Sessions') }}</div>
                <div style="font-size:22px; font-weight:700; color:#1f2937;">{{ number_format($totalSessions) }}</div>
            </div>
        </div>
        <div class="col-sm-4 col-12">
            <div class="card text-center" style="padding:14px; border-left:4px solid #10b981;">
                <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Verified') }}</div>
                <div style="font-size:22px; font-weight:700; color:#10b981;">{{ number_format($verifiedCount) }}</div>
            </div>
        </div>
        <div class="col-sm-4 col-12">
            <div class="card text-center" style="padding:14px; border-left:4px solid #f59e0b;">
                <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Total Minutes') }}</div>
                <div style="font-size:22px; font-weight:700; color:#f59e0b;">{{ number_format($totalMinutes) }}</div>
            </div>
        </div>
    </div>

    <div class="dashboard__review-table table-responsive" style="background:#fff; border-radius:8px; padding:8px;">
        <table class="table table-borderless">
            <thead>
                <tr>
                    <th>{{ __('Course') }}</th>
                    <th>{{ __('Lesson') }}</th>
                    <th>{{ __('Joined') }}</th>
                    <th>{{ __('Duration') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    @php
                        $joined = $r->joined_at ? \Carbon\Carbon::parse($r->joined_at) : null;
                        $minutes = (int) round(((int) $r->duration_seconds) / 60);
                        $classEnded = $r->ended_at !== null;
                        if ((int) $r->attendance_verified === 1) {
                            $statusBadge = ['Verified', 'success', 'fa-check-circle'];
                        } elseif ($classEnded) {
                            $statusBadge = ['Partial', 'warning', 'fa-exclamation-triangle'];
                        } else {
                            $statusBadge = ['In Progress', 'info', 'fa-hourglass-half'];
                        }
                    @endphp
                    <tr>
                        <td>
                            @if ($r->course_slug)
                                <a href="{{ route('student.learning.index', $r->course_slug) }}">
                                    {{ \Illuminate\Support\Str::limit($r->course_title ?? '—', 40) }}
                                </a>
                            @else
                                {{ \Illuminate\Support\Str::limit($r->course_title ?? '—', 40) }}
                            @endif
                        </td>
                        <td><small>{{ \Illuminate\Support\Str::limit($r->lesson_title ?? '—', 50) }}</small></td>
                        <td>
                            <small>{{ $joined?->format('Y-m-d H:i') ?? '—' }}</small>
                        </td>
                        <td>
                            <small>{{ $minutes > 0 ? $minutes.' '.__('min') : ($r->left_at ? __('0 min') : __('still active')) }}</small>
                            @if ($r->is_manual)
                                <span class="badge bg-warning text-dark ms-1" title="{{ __('Marked by coach') }}">{{ __('Manual') }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $statusBadge[1] }}" style="color:{{ in_array($statusBadge[1], ['warning'], true) ? '#1f2937' : '#fff' }};">
                                <i class="fas {{ $statusBadge[2] }}"></i> {{ __($statusBadge[0]) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            {{ __('No attendance records yet. Once you attend a live class, it will show up here.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($rows->hasPages())
            <div class="mt-3">{{ $rows->links() }}</div>
        @endif
    </div>

    <div class="mt-3 text-muted">
        <small>
            <i class="fas fa-info-circle"></i>
            {{ __('"Verified" means the system confirmed your participation after the class ended. "Partial" means you joined but did not meet the minimum duration. "In Progress" means the class is still running.') }}
        </small>
    </div>
</div>
@endsection
