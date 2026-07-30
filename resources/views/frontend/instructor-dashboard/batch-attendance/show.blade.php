@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
{{-- 2026-07-04 — corp header (summary cards / attendance table / JS below unchanged). --}}
@include('frontend.instructor-dashboard.settings.partials._corporate')
<div class="dashboard__content-wrap mt-3">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-users" style="color:var(--corp-brand);"></i> {{ __('Attendance') }} — {{ $batch->title }}</h4>
        </div>
        <div class="corp-header__actions">
            <form method="GET" style="display:flex;align-items:center;gap:8px;margin:0;">
                <small style="color:var(--corp-muted);">{{ __('Date') }}:</small>
                <input type="date" name="date" class="form-control corp-input" style="height:38px;width:auto;" value="{{ $summary['date'] }}">
                <button class="btn-corp-primary" style="height:38px;">{{ __('Update') }}</button>
            </form>
        </div>
    </div>

    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap">
        <small class="text-muted">
            {{ __('Course') }}: {{ optional($batch->course)->title ?? '—' }}
            &middot;
            {{ optional($batch->start_date)->format('Y-m-d') }} →
            {{ optional($batch->end_date)->format('Y-m-d') }}
        </small>
        <a href="{{ route('instructor.batch-attendance.export', ['batch' => $batch->id, 'from' => $summary['date'], 'to' => $summary['date']]) }}"
           class="btn btn-sm btn-outline-secondary" title="{{ __('Download CSV for the visible date') }}">
            <i class="fas fa-file-csv"></i> {{ __('Export CSV') }}
        </a>
    </div>

    {{-- Audit 2026-05-18 phase 4 — threshold explainer --}}
    @php
        $minPct = (int) (cache()->get('setting')?->attendance_min_percent ?? 50);
    @endphp
    <div class="alert alert-info mb-2" style="padding:8px 12px; font-size:12px;">
        <i class="fas fa-info-circle"></i>
        {{ __('A student is marked Verified only after the class ends AND their total attendance duration is at least :pct% of the class length. Joining briefly does not count.', ['pct' => $minPct]) }}
    </div>

    {{-- Summary cards (reusable component) --}}
    <x-batch-attendance-summary :summary="$summary" />

    {{-- 7-day trend mini-chart --}}
    <div class="dashboard__content-wrap mt-3 p-3" style="background:#fff; border-radius:8px;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>{{ __('7-day attendance trend') }}</strong>
            <small class="text-muted">{{ __('Bars show % of batch attended each day') }}</small>
        </div>
        <div class="batch-trend" style="display:flex; gap:8px; align-items:flex-end; height:120px; padding:8px 0;">
            @foreach ($trend as $day)
                @php
                    $h = max(4, (int) round(($day['percent'] / 100) * 100));
                    $color = $day['percent'] >= 75 ? '#10b981' : ($day['percent'] >= 40 ? '#f59e0b' : '#ef4444');
                    $isToday = $day['date'] === $summary['date'];
                @endphp
                <div style="flex:1; text-align:center; cursor:pointer;"
                     title="{{ $day['attended'] }} / {{ $day['total'] }} on {{ $day['date'] }}"
                     onclick="window.location.href='{{ route('instructor.batch-attendance.show', ['batch' => $batch->id, 'date' => $day['date']]) }}'">
                    <div style="display:flex; align-items:flex-end; justify-content:center; height:88px;">
                        <div style="width:32px; background:{{ $color }}; height:{{ $h }}%; border-radius:4px 4px 0 0; {{ $isToday ? 'outline:2px solid #1c1a4a; outline-offset:1px;' : '' }}"></div>
                    </div>
                    <div style="font-size:10px; color:#6b7280; margin-top:4px;">{{ $day['label'] }}</div>
                    <div style="font-size:11px; font-weight:600; color:#1f2937;">{{ $day['percent'] }}%</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Student roster with bulk actions --}}
    <div class="dashboard__content-wrap mt-3 p-3" style="background:#fff; border-radius:8px;">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <strong>{{ __('Student Roster') }} <small class="text-muted">({{ $roster->count() }} {{ trans_choice('student|students', $roster->count()) }})</small></strong>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                @php $filterUrl = url()->current(); @endphp
                <a href="{{ route('instructor.batch-attendance.show', ['batch' => $batch->id, 'date' => $summary['date']]) }}"
                   class="btn btn-sm {{ !$statusFilter ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('All') }}</a>
                <a href="{{ route('instructor.batch-attendance.show', ['batch' => $batch->id, 'date' => $summary['date'], 'attendance' => 'attended']) }}"
                   class="btn btn-sm {{ $statusFilter === 'attended' ? 'btn-success' : 'btn-outline-success' }}"
                   title="{{ __('Verified — duration met threshold') }}">{{ __('Verified') }} ({{ $summary['attended'] }})</a>
                <a href="{{ route('instructor.batch-attendance.show', ['batch' => $batch->id, 'date' => $summary['date'], 'attendance' => 'absent']) }}"
                   class="btn btn-sm {{ $statusFilter === 'absent' ? 'btn-danger' : 'btn-outline-danger' }}"
                   title="{{ __('Absent + Partial (joined briefly but did not meet threshold)') }}">{{ __('Not Verified') }} ({{ $summary['not_attended'] }})</a>
            </div>
        </div>

        @if ($roster->isEmpty())
            <p class="text-muted text-center py-4 mb-0">{{ __('No students match this filter.') }}</p>
        @else
            <form id="bulk-mark-form" data-bulk-url="{{ route('instructor.batch-attendance.bulk-mark', $batch->id) }}">
                @csrf
                <input type="hidden" name="date" value="{{ $summary['date'] }}">

                <div class="table-responsive">
                    <table class="table table-borderless align-middle">
                        <thead>
                            <tr style="background:#f9fafb;">
                                <th width="40">
                                    <input type="checkbox" id="check-all" title="{{ __('Select all') }}">
                                </th>
                                <th>{{ __('Student') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Joined') }}</th>
                                <th>{{ __('Duration') }}</th>
                                <th>{{ __('Source') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roster as $s)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="user_ids[]" value="{{ $s['user_id'] }}" class="js-row-check">
                                    </td>
                                    <td>
                                        <strong>{{ $s['name'] }}</strong><br>
                                        <small class="text-muted">{{ $s['email'] }}</small>
                                    </td>
                                    <td>
                                        {{-- Audit 2026-05-18 phase 4 — 4 distinct verification states. --}}
                                        @switch($s['verification_status'] ?? ($s['attended'] ? 'verified' : 'absent'))
                                            @case('verified')
                                                <span class="badge bg-success" title="{{ __('Verified — duration met threshold') }}">
                                                    <i class="fas fa-check-circle"></i> {{ __('Verified') }}
                                                </span>
                                                @break
                                            @case('partial')
                                                <span class="badge bg-warning text-dark" title="{{ __('Joined but did not meet the minimum attendance duration') }}">
                                                    <i class="fas fa-exclamation-triangle"></i> {{ __('Partial') }}
                                                </span>
                                                @break
                                            @case('pending')
                                                <span class="badge bg-info text-white" title="{{ __('Class has not finished yet') }}">
                                                    <i class="fas fa-hourglass-half"></i> {{ __('In Progress') }}
                                                </span>
                                                @break
                                            @default
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times-circle"></i> {{ __('Absent') }}
                                                </span>
                                        @endswitch
                                    </td>
                                    <td>
                                        <small>{{ $s['joined_at'] ? \Carbon\Carbon::parse($s['joined_at'])->format('H:i') : '—' }}</small>
                                    </td>
                                    <td>
                                        <small>{{ $s['duration_minutes'] > 0 ? $s['duration_minutes'].' '.__('min') : '—' }}</small>
                                    </td>
                                    <td>
                                        @if ($s['is_manual'])
                                            <span class="badge bg-warning text-dark" title="{{ __('Marked manually by coach — auto-verified') }}">
                                                <i class="fas fa-hand-paper"></i> {{ __('Manual') }}
                                            </span>
                                        @elseif (($s['verification_status'] ?? '') !== 'absent')
                                            <span class="badge bg-info" title="{{ __('From Zoom join') }}">
                                                <i class="fas fa-video"></i> {{ __('Auto') }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap" id="bulk-actions" style="display:none;">
                    <div>
                        <span id="bulk-count">0</span> {{ __('selected') }}
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <input type="text" name="reason" id="bulk-reason" class="form-control form-control-sm"
                               placeholder="{{ __('Reason (optional)') }}" style="max-width:240px;">
                        <button type="button" class="btn btn-success btn-sm" id="bulk-mark-attended">
                            <i class="fas fa-check"></i> {{ __('Mark Attended') }}
                        </button>
                        <button type="button" class="btn btn-outline-warning btn-sm" id="bulk-unmark">
                            <i class="fas fa-undo"></i> {{ __('Remove Manual Mark') }}
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>

    @if (!empty($summary['live_class_ids']))
        <p class="text-muted mt-2">
            <small>
                {{ count($summary['live_class_ids']) }}
                {{ trans_choice('live class scheduled today|live classes scheduled today', count($summary['live_class_ids'])) }}.
            </small>
        </p>
    @else
        <p class="text-muted mt-2"><small>{{ __('No live class scheduled today for this batch.') }}</small></p>
    @endif
</div>

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const form = document.getElementById('bulk-mark-form');
    if (!form) return;
    const url = form.dataset.bulkUrl;
    const bulkActions = document.getElementById('bulk-actions');
    const bulkCountEl = document.getElementById('bulk-count');
    const checkAll = document.getElementById('check-all');

    function updateCount() {
        const n = form.querySelectorAll('.js-row-check:checked').length;
        bulkCountEl.textContent = n;
        bulkActions.style.display = n > 0 ? 'flex' : 'none';
    }

    checkAll?.addEventListener('change', () => {
        form.querySelectorAll('.js-row-check').forEach(cb => cb.checked = checkAll.checked);
        updateCount();
    });
    form.querySelectorAll('.js-row-check').forEach(cb => cb.addEventListener('change', updateCount));

    async function submitBulk(action) {
        const fd = new FormData(form);
        fd.set('action', action);
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.message || 'Failed');
                return;
            }
            if (window.toastr) window.toastr.success(data.message || 'Done');
            location.reload();
        } catch (e) {
            alert('Network error: ' + e.message);
        }
    }
    document.getElementById('bulk-mark-attended')?.addEventListener('click', () => submitBulk('mark_attended'));
    document.getElementById('bulk-unmark')?.addEventListener('click', () => submitBulk('unmark'));
})();
</script>
@endsection
