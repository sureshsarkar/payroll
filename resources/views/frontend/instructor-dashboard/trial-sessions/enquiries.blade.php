@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<style>
    .ts-head h4 { font-size:20px; font-weight:750; color:#1c1a4a; margin:0; display:flex; align-items:center; gap:9px; }
    .ts-head p { color:#64748b; font-size:13.5px; margin:6px 0 0; }
    .ts-tabs { display:flex; gap:4px; border-bottom:1px solid #e6e8f0; margin:18px 0 22px; }
    .ts-tab { padding:10px 16px; font-size:14px; font-weight:600; color:#64748b; text-decoration:none;
              border-bottom:2px solid transparent; margin-bottom:-1px; }
    .ts-tab:hover { color:#1c1a4a; }
    .ts-tab.is-active { color:#10b981; border-bottom-color:#10b981; }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] .ts-head h4 { color:#e2e8f0; }
html[data-theme="dark"] .ts-head p { color:#94a3b8; }
html[data-theme="dark"] .ts-tab { color:#94a3b8; }
html[data-theme="dark"] .ts-tab:hover { color:#e2e8f0; }
</style>

<div class="corp-page">
    <div class="ts-head">
        <h4><i class="fas fa-calendar-check"></i> {{ __('Trial Session Enquiries') }}</h4>
        <p>{{ __('Visitors who booked a trial session from your website popup.') }}</p>
    </div>

    <nav class="ts-tabs">
        <a href="{{ route('instructor.trial-sessions.index') }}" class="ts-tab">{{ __('Settings') }}</a>
        <a href="{{ route('instructor.trial-sessions.enquiries.index') }}" class="ts-tab is-active">{{ __('Enquiries') }}</a>
        <a href="{{ route('instructor.trial-sessions.payments.index') }}" class="ts-tab">{{ __('Payments') }}</a>
    </nav>

    @if(session('messege'))
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13.5px;">{{ session('messege') }}</div>
    @endif

    <form method="GET" style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;max-width:720px;">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search name / email / mobile…') }}"
               style="flex:1;min-width:200px;border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:14px;">
        <select name="status" style="border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:14px;">
            <option value="">{{ __('All status') }}</option>
            @foreach(['pending'=>'Pending','contacted'=>'Contacted','closed'=>'Closed','cancelled'=>'Cancelled'] as $k=>$v)
                <option value="{{ $k }}" {{ request('status')===$k ? 'selected' : '' }}>{{ __($v) }}</option>
            @endforeach
        </select>
        <select name="payment_status" style="border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:14px;">
            <option value="">{{ __('All payments') }}</option>
            @foreach(['unpaid'=>'Unpaid','paid'=>'Paid','failed'=>'Failed','free'=>'Free'] as $k=>$v)
                <option value="{{ $k }}" {{ request('payment_status')===$k ? 'selected' : '' }}>{{ __($v) }}</option>
            @endforeach
        </select>
        <button class="btn-corp-light"><i class="fas fa-search"></i></button>
    </form>

    @if ($enquiries->count())
        <div class="corp-form-card">
            <div class="corp-form-card__body" style="overflow-x:auto;padding:0;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;color:#64748b;border-bottom:1px solid #eef0f5;">
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Contact') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Plan / Course') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Time slot') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Profile') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Payment') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Student') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Submitted') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enquiries as $e)
                            <tr style="border-bottom:1px solid #f1f5f9;vertical-align:top;">
                                <td style="padding:10px 12px;">
                                    <div style="font-weight:600;color:#1c1a4a;">{{ $e->name }}</div>
                                    <div style="color:#64748b;">{{ $e->email }}</div>
                                    <div style="color:#64748b;">{{ $e->mobile }}</div>
                                </td>
                                <td style="padding:10px 12px;">
                                    <div style="text-transform:capitalize;">{{ $e->plan_type }} · {{ $e->course_type }}</div>
                                    @if($e->reason)<div style="color:#64748b;text-transform:capitalize;">{{ __('Reason') }}: {{ $e->reason }}</div>@endif
                                    @if($e->problem_description)<div style="color:#94a3b8;font-size:12px;">{{ \Illuminate\Support\Str::limit($e->problem_description, 60) }}</div>@endif
                                </td>
                                <td style="padding:10px 12px;">{{ $e->time_slot ?: '—' }}</td>
                                <td style="padding:10px 12px;color:#64748b;">
                                    @if($e->gender){{ ucfirst($e->gender) }}<br>@endif
                                    @if($e->height){{ __('H') }}: {{ $e->height }} @endif
                                    @if($e->weight){{ __('W') }}: {{ $e->weight }}@endif
                                </td>
                                <td style="padding:10px 12px;">
                                    @php
                                        $pm = ['paid'=>['#dcfce7','#166534'],'unpaid'=>['#fef9c3','#854d0e'],'failed'=>['#fee2e2','#991b1b'],'free'=>['#e0e7ff','#3730a3']][$e->payment_status] ?? ['#f1f5f9','#475569'];
                                    @endphp
                                    <span style="background:{{ $pm[0] }};color:{{ $pm[1] }};border-radius:20px;padding:2px 10px;font-size:11.5px;text-transform:capitalize;">{{ $e->payment_status }}</span>
                                    @if((float)$e->price > 0)<div style="color:#64748b;font-size:12px;margin-top:2px;">{{ $e->currency }} {{ number_format((float)$e->price,2) }}</div>@endif
                                </td>
                                <td style="padding:10px 12px;">
                                    @if($e->student_id && $e->student)
                                        @if($e->student_was_new)
                                            <span style="background:#dcfce7;color:#166534;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;white-space:nowrap;">{{ __('New Student') }}</span>
                                        @else
                                            <span style="background:#e0e7ff;color:#3730a3;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;white-space:nowrap;">{{ __('Existing Student') }}</span>
                                        @endif
                                        @if(($e->student->status ?? '') === 'active')
                                            <span style="background:#f0fdf4;color:#16a34a;border-radius:20px;padding:2px 8px;font-size:10.5px;">{{ __('Active') }}</span>
                                        @endif
                                        <div style="color:#64748b;font-size:12px;margin-top:3px;">{{ $e->student->name }}</div>
                                        <a href="{{ route('instructor.my-students.edit', $e->student_id) }}" style="font-size:11.5px;color:#065f46;font-weight:600;text-decoration:none;">{{ __('View profile') }} →</a>
                                    @elseif(in_array($e->payment_status, ['paid','free'], true))
                                        <span style="color:#94a3b8;font-size:12px;">{{ __('Processing…') }}</span>
                                    @else
                                        <span style="color:#cbd5e1;">—</span>
                                    @endif
                                </td>
                                <td style="padding:10px 12px;color:#64748b;white-space:nowrap;">{{ $e->created_at?->format('d M Y, H:i') }}</td>
                                <td style="padding:10px 12px;">
                                    <form method="POST" action="{{ route('instructor.trial-sessions.enquiries.status', $e->id) }}" onchange="this.submit()">
                                        @csrf @method('PUT')
                                        <select name="status" style="border:1px solid #e2e8f0;border-radius:8px;padding:5px 8px;font-size:12.5px;">
                                            @foreach(['pending'=>'Pending','contacted'=>'Contacted','closed'=>'Closed','cancelled'=>'Cancelled'] as $k=>$v)
                                                <option value="{{ $k }}" {{ $e->status===$k ? 'selected' : '' }}>{{ __($v) }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    {{-- 2026-07-11 (Offline Payment Phase 2) — record an offline trial payment. --}}
                                    @if(!in_array($e->payment_status, ['paid','free'], true))
                                        <button type="button" class="btn-corp-light" style="margin-top:8px;font-size:11.5px;white-space:nowrap;"
                                                data-op-trigger
                                                data-url="{{ route('instructor.offline-payments.record-trial', $e->id) }}"
                                                data-name="{{ $e->name }}"
                                                data-price="{{ $e->price }}">
                                            <i class="fas fa-cash-register"></i> {{ __('Record payment') }}
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div style="margin-top:14px;">{{ $enquiries->links() }}</div>
    @else
        <div class="corp-form-card"><div class="corp-form-card__body" style="padding:28px;text-align:center;color:#94a3b8;">{{ __('No trial enquiries yet.') }}</div></div>
    @endif
</div>

{{-- 2026-07-11 (Offline Payment Phase 2) — shared modal to record an offline trial
     payment. Marks the trial paid + provisions/links the student. No gateway. --}}
<div class="modal fade" id="opTrialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;">
            <form method="POST" id="opTrialForm" action="" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title" style="font-weight:600;">{{ __('Record offline payment') }} — <span id="opTrialName"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label style="font-size:12px;color:#64748b;">{{ __('Payment method') }} *</label>
                            <select name="method" class="form-control" required>
                                @foreach (offlineEnabledMethods() as $mKey => $mMeta)
                                    <option value="{{ $mKey }}">{{ __($mMeta[0]) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label style="font-size:12px;color:#64748b;">{{ __('Amount') }}</label>
                            <input type="number" step="0.01" min="0" name="amount" id="opTrialAmount" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label style="font-size:12px;color:#64748b;">{{ __('Payment date') }}</label>
                            <input type="date" name="paid_at" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label style="font-size:12px;color:#64748b;">{{ __('Reference no.') }}</label>
                            <input type="text" name="reference_no" maxlength="128" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label style="font-size:12px;color:#64748b;">{{ __('Receipt / proof') }}</label>
                            <input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label style="font-size:12px;color:#64748b;">{{ __('Notes') }}</label>
                            <input type="text" name="notes" maxlength="1000" class="form-control">
                        </div>
                    </div>
                    <p style="font-size:11px;color:#94a3b8;margin:10px 0 0;">{{ __('Recording this marks the trial paid and creates / links the student account. No online gateway is used.') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('Record payment') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    (function () {
        var modalEl = document.getElementById('opTrialModal');
        if (!modalEl || typeof bootstrap === 'undefined') return;
        var modal = new bootstrap.Modal(modalEl);
        document.querySelectorAll('[data-op-trigger]').forEach(function (b) {
            b.addEventListener('click', function () {
                document.getElementById('opTrialForm').action = b.getAttribute('data-url');
                document.getElementById('opTrialName').textContent = b.getAttribute('data-name') || '';
                document.getElementById('opTrialAmount').value = b.getAttribute('data-price') || '';
                modal.show();
            });
        });
    })();
</script>
@endsection
