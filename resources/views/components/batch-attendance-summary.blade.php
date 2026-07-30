{{-- Audit 2026-05-18 — reusable batch attendance summary cards.

    Used by:
      - Admin batch detail (resources/views/admin/batches/show.blade.php)
      - Coach batch detail (resources/views/frontend/instructor-dashboard/course-batches/show.blade.php)
      - Coach dashboard widget

    Required prop: $summary — array shape from BatchAttendanceService::summaryFor():
      [
        'batch_id'       => int,
        'batch_title'    => string,
        'date'           => 'Y-m-d',
        'total_students' => int,
        'attended'       => int,
        'not_attended'   => int,
        'live_class_ids' => int[],
      ]

    Pure markup. Responsive (4 cards on desktop, stack on mobile).
--}}
@props(['summary'])

@php
    $total = (int) ($summary['total_students'] ?? 0);
    $attended = (int) ($summary['attended'] ?? 0);
    $notAttended = (int) ($summary['not_attended'] ?? 0);
    $attendancePct = $total > 0 ? round(($attended / $total) * 100, 1) : 0;
    $classCount = is_array($summary['live_class_ids'] ?? null) ? count($summary['live_class_ids']) : 0;
@endphp

<div class="batch-attendance-summary mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
        <div>
            <strong>{{ $summary['batch_title'] ?? __('Batch') }}</strong>
            <small class="text-muted ms-2">{{ __('on') }} {{ $summary['date'] ?? '' }}</small>
        </div>
        <small class="text-muted">
            {{ $classCount }} {{ trans_choice('class scheduled|classes scheduled', $classCount) }}
        </small>
    </div>

    <div class="row g-2">
        <div class="col-sm-4 col-12 mb-2">
            <div class="card text-center h-100" style="padding:18px; border-left:4px solid #5751e1;">
                <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600; letter-spacing:0.4px;">
                    {{ __('Total Students') }}
                </div>
                <div style="font-size:28px; font-weight:700; color:#1f2937; margin-top:4px;">
                    {{ number_format($total) }}
                </div>
            </div>
        </div>

        <div class="col-sm-4 col-12 mb-2">
            <div class="card text-center h-100" style="padding:18px; border-left:4px solid #10b981;">
                <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600; letter-spacing:0.4px;">
                    {{ __('Attended Today') }}
                </div>
                <div style="font-size:28px; font-weight:700; color:#10b981; margin-top:4px;">
                    {{ number_format($attended) }}
                </div>
                @if ($total > 0)
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">{{ $attendancePct }}%</div>
                @endif
            </div>
        </div>

        <div class="col-sm-4 col-12 mb-2">
            <div class="card text-center h-100" style="padding:18px; border-left:4px solid #ef4444;">
                <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600; letter-spacing:0.4px;">
                    {{ __('Not Attended Today') }}
                </div>
                <div style="font-size:28px; font-weight:700; color:#ef4444; margin-top:4px;">
                    {{ number_format($notAttended) }}
                </div>
                @if ($total > 0)
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">{{ 100 - $attendancePct }}%</div>
                @endif
            </div>
        </div>
    </div>
</div>
