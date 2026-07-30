@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
{{-- ============================================================
     Fee Management — coach dashboard
     Phase 4B foundation. Razorpay collection lands in a separate phase.
     ============================================================ --}}
<div class="dashboard__content-wrap">

    {{-- Header ───────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h4 class="title mb-1" style="color:#1c1a4a; font-weight:700;">
                <i class="fas fa-coins me-1" style="color:#f59e0b;"></i> {{ __('Fee Management') }}
            </h4>
            <small class="text-muted">{{ __('Raise fee demands against batches, track collections, record refunds.') }}</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('instructor.fees.transactions') }}" class="btn btn-light btn-hight-basic">
                <i class="fas fa-receipt me-1"></i> {{ __('Transactions') }}
            </a>
            <button type="button" class="btn btn-primary btn-hight-basic"
                    data-bs-toggle="modal" data-bs-target="#createDemandModal">
                <i class="fas fa-plus me-1"></i> {{ __('Create Fee Demand') }}
            </button>
        </div>
    </div>

    {{-- Razorpay readiness banner ────────────────────────────────
         Phase 5b — show coaches whether students can actually pay
         via gateway, or whether the admin still needs to finish
         credential setup. --}}
    @if (isset($razorpayStatus) && $razorpayStatus['state'] !== 'ready')
        @php
            $isMissing = $razorpayStatus['state'] === 'missing';
            $bg = $isMissing ? '#fef2f2' : '#fffbeb';
            $border = $isMissing ? '#fca5a5' : '#fcd34d';
            $fg = $isMissing ? '#991b1b' : '#92400e';
            $icon = $isMissing ? 'fa-exclamation-triangle' : 'fa-exclamation-circle';
            $titleText = $isMissing
                ? __('Razorpay not configured — students cannot pay online yet')
                : __('Razorpay partially configured — finish setup to accept gateway payments');
        @endphp
        <div style="background:{{ $bg }}; border:1px solid {{ $border }};
                    border-radius:10px; padding:14px 16px; margin-bottom:12px;
                    display:flex; gap:12px; align-items:flex-start;">
            <div style="font-size:22px; color:{{ $fg }};">
                <i class="fas {{ $icon }}"></i>
            </div>
            <div style="flex:1;">
                <div style="font-weight:600; color:{{ $fg }}; font-size:13px;">
                    {{ $titleText }}
                </div>
                <div style="font-size:11px; color:#6b7280; margin-top:4px;">
                    {{ __('Until setup is complete, you can still raise fee demands and record manual / cash / cheque payments. Online checkout will activate as soon as the platform admin fixes:') }}
                </div>
                <ul style="font-size:11px; color:{{ $fg }}; margin:6px 0 0 18px; padding:0;">
                    @foreach ($razorpayStatus['issues'] as $issue)
                        <li>{{ $issue }}</li>
                    @endforeach
                </ul>
                <div style="font-size:11px; color:#6b7280; margin-top:6px;">
                    <i class="fas fa-info-circle"></i>
                    {{ __('Need help? See docs/RAZORPAY_SETUP.md or run') }}
                    <code style="background:#fff; padding:1px 6px; border-radius:4px; color:var(--corp-brand);">php artisan razorpay:test</code>
                </div>
            </div>
        </div>
    @endif

    {{-- KPI icon chip styles (scoped) --}}
    <style>
        .fees-kpi-card {
            padding: 14px 18px 14px 70px !important;
            position: relative;
            min-height: 96px;
            border-left: 0 !important;
        }
        .fees-kpi-card::before {
            content: ''; position: absolute;
            top: 0; bottom: 0; left: 0;
            width: 3px;
            background: var(--accent, var(--corp-brand));
        }
        .fees-kpi-card .fees-kpi-icon {
            position: absolute; top: 14px; left: 18px;
            width: 38px; height: 38px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #ffffff);
            color: var(--accent, var(--corp-brand));
            border: 1px solid color-mix(in srgb, var(--accent, var(--corp-brand)) 18%, transparent);
            font-size: 15px;
        }
    </style>

    <style>
        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components.
           The page's own <style> block only defines the KPI-card accent bar + icon chip via
           brand color-mix()/vars (kept, per spec). All other surfaces on this screen are set
           through inline style="" attributes, which CSS cannot override — so there are no
           hardcoded light rules here to counter. Global tokens handle .card / .table. */
    </style>

    {{-- KPI Strip ────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-sm-6">
            <div class="card fees-kpi-card" style="--accent:#10b981; border-radius:10px;">
                <span class="fees-kpi-icon"><i class="fas fa-coins"></i></span>
                <div style="font-size:10px; text-transform:uppercase; color:#9ca3af; font-weight:600; letter-spacing:.4px;">
                    {{ __('Total Collected') }}
                </div>
                <div style="font-size:24px; font-weight:800; color:#1c1a4a; margin-top:4px;">
                    {{ currency($kpi['total_collected']) }}
                </div>
                <div style="font-size:11px; color:#6b7280;">
                    {{ $kpi['paid_count'] }} {{ __('payments') }}
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card fees-kpi-card" style="--accent:var(--corp-brand); border-radius:10px;">
                <span class="fees-kpi-icon"><i class="fas fa-check-circle"></i></span>
                <div style="font-size:10px; text-transform:uppercase; color:#9ca3af; font-weight:600; letter-spacing:.4px;">
                    {{ __('Successful') }}
                </div>
                <div style="font-size:24px; font-weight:800; color:#1c1a4a; margin-top:4px;">
                    {{ number_format($kpi['paid_count']) }}
                </div>
                <div style="font-size:11px; color:#6b7280;">{{ __('paid transactions') }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card fees-kpi-card" style="--accent:#ef4444; border-radius:10px;">
                <span class="fees-kpi-icon"><i class="fas fa-exclamation-triangle"></i></span>
                <div style="font-size:10px; text-transform:uppercase; color:#9ca3af; font-weight:600; letter-spacing:.4px;">
                    {{ __('Failed') }}
                </div>
                <div style="font-size:24px; font-weight:800; color:#1c1a4a; margin-top:4px;">
                    {{ number_format($kpi['failed_count']) }}
                </div>
                <div style="font-size:11px; color:#6b7280;">{{ __('gateway errors') }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card fees-kpi-card" style="--accent:#9ca3af; border-radius:10px;">
                <span class="fees-kpi-icon"><i class="fas fa-undo"></i></span>
                <div style="font-size:10px; text-transform:uppercase; color:#9ca3af; font-weight:600; letter-spacing:.4px;">
                    {{ __('Refunded') }}
                </div>
                <div style="font-size:24px; font-weight:800; color:#1c1a4a; margin-top:4px;">
                    {{ number_format($kpi['refunded_count']) }}
                </div>
                <div style="font-size:11px; color:#6b7280;">
                    {{ currency($kpi['refunded_amount']) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Demands Table ─────────────────────────────────── --}}
    <div class="dashboard__review-table table-responsive"
         style="background:#fff; border-radius:10px; padding:8px;">
        <div class="d-flex justify-content-between align-items-center px-2 pt-2 pb-3">
            <h6 class="mb-0" style="color:#1c1a4a; font-weight:600;">{{ __('Recent Fee Demands') }}</h6>
            @if ($demands->isNotEmpty())
                <small class="text-muted">{{ $demands->count() }} {{ __('demands shown') }}</small>
            @endif
        </div>

        <table class="table table-borderless align-middle">
            <thead>
                <tr style="font-size:12px; color:#6b7280;">
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Batch') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th>
                    <th>{{ __('Due') }}</th>
                    <th class="text-end">{{ __('Collected') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($demands as $d)
                    @php
                        $courseTitle = $d->batch?->course?->title ?? '—';
                        $collected = (float) ($d->collected ?? 0);
                        $coverage = $d->amount > 0 ? min(100, round(($collected / $d->amount) * 100)) : 0;
                    @endphp
                    <tr style="border-top:1px solid #f3f4f6;">
                        <td>
                            <div style="font-weight:600; color:#1f2937;">
                                {{ \Illuminate\Support\Str::limit($d->title, 36) }}
                            </div>
                            @if ($d->notes)
                                <small class="text-muted">{{ \Illuminate\Support\Str::limit($d->notes, 50) }}</small>
                            @endif
                        </td>
                        <td>
                            <div style="font-size:13px;">{{ \Illuminate\Support\Str::limit($d->batch?->title ?? '—', 24) }}</div>
                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($courseTitle, 28) }}</small>
                        </td>
                        <td class="text-end" style="font-weight:600;">{{ currency($d->amount) }}</td>
                        <td>
                            @if ($d->due_date)
                                <small>{{ $d->due_date->format('M j, Y') }}</small>
                                @if ($d->due_date->isPast())
                                    <span class="badge bg-danger-subtle text-danger ms-1" style="font-size:9px;">{{ __('overdue') }}</span>
                                @endif
                            @else
                                <small class="text-muted">—</small>
                            @endif
                        </td>
                        <td class="text-end">
                            <div style="font-weight:600; color:#10b981;">{{ currency($collected) }}</div>
                            <small class="text-muted">{{ $d->paid_count ?? 0 }} {{ __('paid') }}</small>
                        </td>
                        <td>
                            @php
                                $pillBg = '#ecfdf5'; $pillFg = '#047857'; $pillTxt = __('Published');
                                if ($d->status === 'draft')   { $pillBg = '#f3f4f6'; $pillFg = '#4b5563'; $pillTxt = __('Draft'); }
                                if ($d->status === 'closed')  { $pillBg = '#eef1ff'; $pillFg = '#3730a3'; $pillTxt = __('Closed'); }
                            @endphp
                            <span style="background:{{ $pillBg }}; color:{{ $pillFg }};
                                         padding:3px 10px; border-radius:999px;
                                         font-size:10px; font-weight:600;">
                                {{ $pillTxt }}
                            </span>
                        </td>
                        <td class="text-end">
                            {{-- 2026-07-10 — Record an OFFLINE payment (cash/UPI/cheque/bank)
                                 against this demand. Opens the modal below; JS loads the
                                 batch's students + prefills the outstanding amount. Hidden
                                 once fully collected. --}}
                            @php $outstanding = max(0, (float) $d->amount - $collected); @endphp
                            @if ($d->status !== 'closed' && $outstanding > 0)
                                <button type="button"
                                        class="btn btn-sm js-record-pay"
                                        style="border:1.5px solid #10b981; color:#059669; background:transparent; border-radius:8px; font-weight:600; font-size:12px; white-space:nowrap;"
                                        data-demand-id="{{ $d->id }}"
                                        data-title="{{ $d->title }}"
                                        data-batch="{{ $d->batch?->title }}"
                                        data-amount="{{ $d->amount }}"
                                        data-outstanding="{{ $outstanding }}"
                                        data-students-url="{{ route('instructor.fees.demands.students', $d->id) }}"
                                        data-action-url="{{ route('instructor.fees.payments.store', $d->id) }}">
                                    <i class="fas fa-receipt me-1"></i> {{ __('Record payment') }}
                                </button>
                            @else
                                <small class="text-muted">{{ __('Fully paid') }}</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="fas fa-receipt fa-2x mb-2" style="color:#d1d5db;"></i>
                            <div>{{ __('No fee demands yet.') }}</div>
                            <small>{{ __('Click “Create Fee Demand” to raise your first bill.') }}</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ============================================================
     Create Fee Demand Modal
     ============================================================ --}}
<div class="modal fade" id="createDemandModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;">
            <form method="POST" action="{{ route('instructor.fees.demands.store') }}">
                @csrf

                <div class="modal-header" style="border-bottom:1px solid #eef0f3;">
                    <h5 class="modal-title" style="color:#1c1a4a; font-weight:600;">
                        <i class="fas fa-coins me-1" style="color:#f59e0b;"></i> {{ __('Create Fee Demand') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    {{-- Title --}}
                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; font-size:11px; text-transform:uppercase; color:#6b7280;">
                            {{ __('Fee Title') }} *
                        </label>
                        <input type="text" name="title" class="form-control" required maxlength="255"
                               placeholder="{{ __('e.g. Term 1 Fee — Jan 2026') }}" value="{{ old('title') }}">
                    </div>

                    <div class="row g-3">
                        {{-- Batch --}}
                        <div class="col-md-7">
                            <label class="form-label" style="font-weight:600; font-size:11px; text-transform:uppercase; color:#6b7280;">
                                {{ __('Select Batch') }} *
                            </label>
                            <select name="batch_id" class="form-select" required>
                                <option value="">{{ __('— Select batch —') }}</option>
                                @foreach ($batches as $b)
                                    <option value="{{ $b->id }}" @selected(old('batch_id')==$b->id)>
                                        {{ $b->course?->title ? $b->course->title.' · ' : '' }}{{ $b->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Amount --}}
                        <div class="col-md-5">
                            <label class="form-label" style="font-weight:600; font-size:11px; text-transform:uppercase; color:#6b7280;">
                                {{ __('Amount') }} (₹) *
                            </label>
                            <input type="number" name="amount" class="form-control" required
                                   min="0" max="9999999" step="0.01" value="{{ old('amount', '0') }}">
                        </div>

                        {{-- Due date --}}
                        <div class="col-md-7">
                            <label class="form-label" style="font-weight:600; font-size:11px; text-transform:uppercase; color:#6b7280;">
                                {{ __('Due Date') }}
                            </label>
                            <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                        </div>

                        {{-- Late fine --}}
                        <div class="col-md-5">
                            <label class="form-label" style="font-weight:600; font-size:11px; text-transform:uppercase; color:#6b7280;">
                                {{ __('Late Fine / Day') }} (₹)
                            </label>
                            <input type="number" name="late_fine_per_day" class="form-control"
                                   min="0" max="99999" step="0.01" value="{{ old('late_fine_per_day', '0') }}">
                        </div>

                        {{-- Notes --}}
                        <div class="col-md-12">
                            <label class="form-label" style="font-weight:600; font-size:11px; text-transform:uppercase; color:#6b7280;">
                                {{ __('Notes') }} <small style="text-transform:none; font-weight:400; color:#9ca3af;">({{ __('optional') }})</small>
                            </label>
                            <textarea name="notes" rows="2" class="form-control"
                                      placeholder="{{ __('Any notes…') }}" maxlength="2000">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="border-top:1px solid #eef0f3;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-coins me-1"></i> {{ __('Create Demand') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     Record Offline Payment Modal (2026-07-10)
     Records a cash/UPI/cheque/bank payment against a demand via the
     existing recordPayment endpoint. JS (below) loads the demand's
     batch students + prefills the outstanding amount. Dark-mode ready.
     ============================================================ --}}
<style>
    .rop-ctx { display:flex; flex-wrap:wrap; gap:8px; justify-content:space-between; align-items:center;
               background:#f8fafc; border:1px solid #eef0f3; border-radius:10px; padding:10px 13px;
               margin-bottom:16px; font-size:12.5px; color:#1c1a4a; }
    .rop-muted { color:#64748b; }
    .rop-out { background:#ecfdf5; color:#047857; font-weight:600; padding:3px 10px; border-radius:999px; font-size:12px; }
    .rop-field { margin-bottom:14px; }
    .rop-field label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.03em; color:#64748b; margin-bottom:6px; }
    .rop-opt { text-transform:none; font-weight:400; color:#94a3b8; }
    .rop-input { width:100%; background:#fff; border:1px solid #dfe3ea; border-radius:9px; color:#1c1a4a; font-size:13px; padding:10px 12px; font-family:inherit; }
    .rop-input:focus { outline:none; border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.12); }
    .rop-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .rop-methods { display:flex; flex-wrap:wrap; gap:7px; }
    .rop-m { border:1px solid #dfe3ea; background:#fff; color:#1c1a4a; border-radius:999px; padding:7px 13px; font-size:12.5px; cursor:pointer; }
    .rop-m.is-on { background:#10b981; border-color:#10b981; color:#fff; }
    .rop-hint { font-size:11px; color:#94a3b8; margin-top:5px; }

    /* 2026-07-10 (New Changes for UI #4) — dark mode for this modal. */
    html[data-theme="dark"] .rop-modal { background:#1e293b; color:#e2e8f0; }
    html[data-theme="dark"] .rop-head, html[data-theme="dark"] .rop-foot { border-color:#2a3a55; }
    html[data-theme="dark"] .rop-title { color:#e2e8f0; }
    html[data-theme="dark"] .rop-ctx { background:#17233a; border-color:#2a3a55; color:#e2e8f0; }
    html[data-theme="dark"] .rop-muted { color:#94a3b8; }
    html[data-theme="dark"] .rop-out { background:#08312a; color:#5dcaa5; }
    html[data-theme="dark"] .rop-input { background:#17233a; border-color:#2a3a55; color:#e2e8f0; }
    html[data-theme="dark"] .rop-m { background:#1e293b; border-color:#2a3a55; color:#e2e8f0; }
    html[data-theme="dark"] .rop-field label { color:#94a3b8; }
</style>

<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rop-modal" style="border-radius:14px;">
            <form method="POST" id="ropForm" action="" enctype="multipart/form-data">
                @csrf
                <div class="modal-header rop-head" style="border-bottom:1px solid #eef0f3;">
                    <h5 class="modal-title rop-title" style="color:#1c1a4a; font-weight:600;">
                        <i class="fas fa-coins me-2" style="color:#f59e0b;"></i>{{ __('Record Offline Payment') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="rop-ctx">
                        <div>
                            <span class="rop-muted">{{ __('Demand') }}</span> <b id="ropDemandTitle">—</b>
                            · <span class="rop-muted">{{ __('Batch') }}</span> <span id="ropBatch">—</span>
                        </div>
                        <span class="rop-out"><span class="rop-muted">{{ __('Outstanding') }}</span> <b id="ropOutstanding">₹0</b></span>
                    </div>

                    <div class="rop-field">
                        <label>{{ __('Student') }} *</label>
                        <select name="student_id" id="ropStudent" class="rop-input" required>
                            <option value="">{{ __('Loading students…') }}</option>
                        </select>
                        <div class="rop-hint" id="ropStudentHint"></div>
                    </div>

                    <div class="rop-row">
                        <div class="rop-field">
                            <label>{{ __('Amount (₹)') }} *</label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="ropAmount" class="rop-input" required>
                        </div>
                        <div class="rop-field">
                            <label>{{ __('Payment date') }} *</label>
                            <input type="date" name="paid_at" id="ropDate" class="rop-input" required>
                        </div>
                    </div>

                    <div class="rop-field">
                        <label>{{ __('Payment method') }} *</label>
                        {{-- 2026-07-11 — only the methods this coach accepts (per-coach config). --}}
                        <div class="rop-methods" id="ropMethods">
                            @foreach (offlineEnabledMethods() as $mKey => $mMeta)
                                <button type="button" class="rop-m {{ $loop->first ? 'is-on' : '' }}" data-g="{{ $mKey }}"><i class="fas {{ $mMeta[1] }}"></i> {{ __($mMeta[0]) }}</button>
                            @endforeach
                        </div>
                        <input type="hidden" name="gateway" id="ropGateway" value="{{ array_key_first(offlineEnabledMethods()) }}">
                    </div>

                    <div class="rop-field">
                        <label>{{ __('Reference no.') }} <span class="rop-opt">({{ __('optional') }})</span></label>
                        <input type="text" name="reference_no" maxlength="128" class="rop-input"
                               placeholder="{{ __('Cheque no. / UPI txn id / bank ref') }}">
                    </div>

                    {{-- 2026-07-11 — optional proof/receipt upload (Offline Payment upgrade). --}}
                    <div class="rop-field">
                        <label>{{ __('Receipt / proof') }} <span class="rop-opt">({{ __('optional') }})</span></label>
                        <input type="file" name="proof" class="rop-input" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </div>

                    <div class="rop-field" style="margin-bottom:0;">
                        <label>{{ __('Note') }} <span class="rop-opt">({{ __('optional') }})</span></label>
                        <textarea name="note" rows="2" maxlength="500" class="rop-input"
                                  placeholder="{{ __('e.g. Received at front desk') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer rop-foot" style="border-top:1px solid #eef0f3;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="background:#059669; border:none;">
                        <i class="fas fa-receipt me-1"></i> {{ __('Record Payment') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="{{ csp_nonce() }}">
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('recordPaymentModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    var bsModal = new bootstrap.Modal(modalEl);
    var form = document.getElementById('ropForm');
    var sel  = document.getElementById('ropStudent');
    var amt  = document.getElementById('ropAmount');
    var dateEl = document.getElementById('ropDate');
    var gw   = document.getElementById('ropGateway');
    var hint = document.getElementById('ropStudentHint');

    function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
    function money(n){ return '₹' + (Math.round((+n||0)*100)/100).toLocaleString('en-IN'); }

    document.querySelectorAll('.js-record-pay').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.setAttribute('action', btn.dataset.actionUrl);
            document.getElementById('ropDemandTitle').textContent = btn.dataset.title || '—';
            document.getElementById('ropBatch').textContent = btn.dataset.batch || '—';
            var outstanding = parseFloat(btn.dataset.outstanding || '0');
            document.getElementById('ropOutstanding').textContent = money(outstanding);
            amt.value = outstanding ? outstanding : '';
            dateEl.value = new Date().toISOString().slice(0, 10);
            var firstM = document.querySelector('.rop-m');
            var defG = firstM ? firstM.dataset.g : 'cash';
            gw.value = defG;
            document.querySelectorAll('.rop-m').forEach(function (m) { m.classList.toggle('is-on', m.dataset.g === defG); });
            form.querySelector('[name=reference_no]').value = '';
            form.querySelector('[name=note]').value = '';
            hint.textContent = '';
            sel.innerHTML = '<option value="">' + @json(__('Loading students…')) + '</option>';
            bsModal.show();

            fetch(btn.dataset.studentsUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var list = (d && d.students) || [];
                    if (!list.length) { sel.innerHTML = '<option value="">' + @json(__('No students in this batch')) + '</option>'; return; }
                    var opts = '<option value="">' + @json(__('Select a student…')) + '</option>';
                    list.forEach(function (s) {
                        opts += '<option value="' + s.id + '" data-out="' + s.outstanding + '">' +
                            esc(s.name) + ' — ' + esc(s.email) + ' (' + @json(__('due')) + ' ' + money(s.outstanding) + ')</option>';
                    });
                    sel.innerHTML = opts;
                })
                .catch(function () { sel.innerHTML = '<option value="">' + @json(__('Could not load students')) + '</option>'; });
        });
    });

    sel.addEventListener('change', function () {
        var o = sel.options[sel.selectedIndex];
        if (o && o.value) {
            var out = parseFloat(o.getAttribute('data-out') || '0');
            amt.value = out > 0 ? out : '';
            hint.textContent = out > 0 ? '' : @json(__('This student has already paid in full.'));
        }
    });

    document.querySelectorAll('.rop-m').forEach(function (m) {
        m.addEventListener('click', function () {
            document.querySelectorAll('.rop-m').forEach(function (x) { x.classList.remove('is-on'); });
            m.classList.add('is-on');
            gw.value = m.dataset.g;
        });
    });
});
</script>
@endsection
