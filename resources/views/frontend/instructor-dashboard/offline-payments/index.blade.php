@extends('frontend.instructor-dashboard.layouts.master')

{{-- Offline Payment — record-only (Phase 1). Built on the corp design system so
     it is brand-aware (var(--corp-brand)) + dark-mode ready. Every list/picker
     is tenant-scoped in the controller; nothing here is coach-specific. --}}

@section('dashboard-contents')
    @include('frontend.instructor-dashboard.settings.partials._corporate')

    @php
        getSessionCurrency();
        $curIcon = session('currency_icon', '₹');
        // Only the methods this coach accepts (per-coach config; defaults to all).
        $methods = offlineEnabledMethods();
        $oldMethod = old('method', array_key_first($methods));
    @endphp

    <style>
        #offpay .op-methods { display:flex; flex-wrap:wrap; gap:8px; }
        #offpay .op-method {
            border:1px solid var(--corp-line); background:var(--corp-card); border-radius:999px;
            padding:7px 14px; font-size:12.5px; color:var(--corp-text); cursor:pointer;
            display:inline-flex; align-items:center; gap:7px; user-select:none;
        }
        #offpay .op-method.on { border-color:var(--corp-brand); background:var(--corp-brand-bg); color:var(--corp-brand-deep); font-weight:500; }
        #offpay .op-method input { display:none; }
        #offpay .op-up { border:1.5px dashed var(--corp-line); border-radius:8px; padding:12px; text-align:center; color:var(--corp-muted); font-size:12px; background:var(--corp-bg); cursor:pointer; }
        #offpay .op-seg { display:flex; gap:6px; }
        #offpay .op-seg label { flex:1; text-align:center; border:1px solid var(--corp-line); background:var(--corp-card); border-radius:8px; padding:8px; font-size:12px; cursor:pointer; color:var(--corp-text); }
        #offpay .op-seg input { display:none; }
        #offpay .op-seg label.on { border-color:var(--corp-brand); background:var(--corp-brand-bg); color:var(--corp-brand-deep); font-weight:500; }
        #offpay .op-cur { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--corp-muted); font-size:12.5px; }
    </style>

    <div class="corp-page" id="offpay">
        <div class="corp-header">
            <div class="corp-header__title">
                <h4>{{ __('Record offline payment') }}</h4>
                <p>{{ __('Log a payment received off-platform — cash, bank transfer, UPI, cheque or other — without the online gateway.') }}</p>
            </div>
        </div>

        <div style="margin:0 0 14px;">
            <span class="corp-pill corp-pill--success" style="font-weight:500;">
                <i class="fas fa-lock" style="font-size:10px;"></i>
                {{ __('Scoped to your account — your students, branding and receipts only') }}
            </span>
            @if ($needsApproval)
                <span class="corp-pill corp-pill--warning" style="font-weight:500; margin-left:6px;">
                    <i class="fas fa-user-check" style="font-size:10px;"></i>
                    {{ __('Approval required before a payment takes effect') }}
                </span>
            @endif
        </div>

        {{-- Per-coach settings: which methods you accept + approval toggle. --}}
        <details class="corp-form-card" style="margin-bottom:14px;">
            <summary style="padding:12px 14px; cursor:pointer; font-size:13px; font-weight:500; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-sliders"></i> {{ __('Offline payment settings') }}
                <span style="margin-left:auto; font-size:11px; color:var(--corp-muted);">{{ __('methods you accept · approval') }}</span>
            </summary>
            <div class="corp-form-card__body" style="border-top:1px solid var(--corp-line-soft);">
                <form method="POST" action="{{ route('instructor.offline-payments.settings') }}">
                    @csrf
                    <label class="form-label">{{ __('Accepted payment methods') }}</label>
                    <div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:14px;">
                        @foreach ($allMethods as $key => $meta)
                            <label style="display:inline-flex; align-items:center; gap:7px; font-size:13px; cursor:pointer;">
                                <input type="checkbox" name="methods[]" value="{{ $key }}" @checked(in_array($key, $enabledMethods, true))>
                                <i class="fas {{ $meta[1] }}" style="color:var(--corp-muted);"></i> {{ __($meta[0]) }}
                            </label>
                        @endforeach
                    </div>
                    <label style="display:inline-flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="needs_approval" value="1" @checked($needsApproval)>
                        {{ __('Require approval before a recorded payment takes effect') }}
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; margin-top:10px;">
                        <input type="checkbox" name="email_receipt" value="1" @checked($emailReceipt)>
                        {{ __('Email the branded receipt to the student when a payment takes effect') }}
                    </label>
                    <div style="margin-top:14px;">
                        <button type="submit" class="btn-corp-primary"><i class="fas fa-check"></i> {{ __('Save settings') }}</button>
                    </div>
                </form>
            </div>
        </details>

        @if ($errors->any())
            <div class="corp-form-card" style="margin-bottom:12px; border-color:#fecaca;">
                <div class="corp-form-card__body" style="color:#b91c1c; font-size:12.5px;">
                    <i class="fas fa-triangle-exclamation"></i>
                    {{ $errors->first() }}
                </div>
            </div>
        @endif

        {{-- ── Form ── --}}
        <form method="POST" action="{{ route('instructor.offline-payments.store-course') }}" enctype="multipart/form-data">
            @csrf
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title"><i class="fas fa-receipt"></i> {{ __('Payment details') }}</h6>
                </div>
                <div class="corp-form-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Student') }} <span style="color:#ef4444">*</span></label>
                            <select name="student_id" class="form-select" required>
                                <option value="">{{ __('Search a student…') }}</option>
                                @foreach ($students as $s)
                                    <option value="{{ $s->id }}" @selected(old('student_id') == $s->id)>{{ $s->name }} — {{ $s->email }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Course') }} <span style="color:#ef4444">*</span></label>
                            <select name="course_id" id="op-course" class="form-select" required>
                                <option value="">{{ __('Choose a course…') }}</option>
                                @foreach ($courses as $c)
                                    <option value="{{ $c->id }}" @selected(old('course_id') == $c->id)>{{ $c->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Batch') }} <span style="color:var(--corp-subtle)">({{ __('optional') }})</span></label>
                            <select name="batch_id" id="op-batch" class="form-select">
                                <option value="">{{ __('No specific batch') }}</option>
                                @foreach ($batches as $b)
                                    <option value="{{ $b->id }}" data-course="{{ $b->course_id }}" @selected(old('batch_id') == $b->id)>{{ $b->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Amount') }} <span style="color:#ef4444">*</span></label>
                            <div style="position:relative;">
                                <span class="op-cur">{{ $curIcon }}</span>
                                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}"
                                       class="form-control" style="padding-left:26px;" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Payment date') }} <span style="color:#ef4444">*</span></label>
                            <input type="date" name="paid_at" value="{{ old('paid_at', date('Y-m-d')) }}" class="form-control" max="{{ date('Y-m-d') }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label">{{ __('Payment method') }} <span style="color:#ef4444">*</span></label>
                            <div class="op-methods">
                                @foreach ($methods as $key => $m)
                                    <label class="op-method {{ $oldMethod === $key ? 'on' : '' }}">
                                        <input type="radio" name="method" value="{{ $key }}" @checked($oldMethod === $key)>
                                        <i class="fas {{ $m[1] }}"></i> {{ __($m[0]) }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ __('Transaction / reference no.') }}</label>
                            <input type="text" name="reference_no" value="{{ old('reference_no') }}" class="form-control"
                                   placeholder="{{ __('UTR, cheque no., UPI ref…') }}" maxlength="128">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Receipt / proof') }} <span style="color:var(--corp-subtle)">({{ __('optional') }})</span></label>
                            <label class="op-up" for="op-proof">
                                <i class="fas fa-upload"></i> <span id="op-proof-name">{{ __('Click to upload (PDF / image)') }}</span>
                            </label>
                            <input type="file" id="op-proof" name="proof" accept=".pdf,.jpg,.jpeg,.png,.webp" style="display:none;">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ __('Payment status') }} <span style="color:#ef4444">*</span></label>
                            <div class="op-seg">
                                @foreach (['paid' => 'Paid', 'partial' => 'Partial', 'pending' => 'Pending'] as $val => $lbl)
                                    <label class="{{ old('status', 'paid') === $val ? 'on' : '' }}">
                                        <input type="radio" name="status" value="{{ $val }}" @checked(old('status', 'paid') === $val)>{{ __($lbl) }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <input type="text" name="notes" value="{{ old('notes') }}" class="form-control"
                                   placeholder="{{ __('e.g. paid at front desk, towards July instalment…') }}" maxlength="1000">
                        </div>
                    </div>
                </div>
                <div class="corp-form-card__head" style="border-top:1px solid var(--corp-line-soft); border-bottom:none; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <span style="font-size:11.5px; color:var(--corp-muted);">
                        <i class="fas fa-user-check"></i>
                        {{ __('Recorded by') }} <b style="font-weight:500; color:var(--corp-text);">{{ userAuth()->name }}</b>
                        · {{ __('logged in the audit trail') }}
                    </span>
                    <div style="margin-left:auto; display:flex; gap:8px;">
                        <button type="submit" class="btn-corp-primary"><i class="fas fa-check"></i> {{ __('Save payment') }}</button>
                    </div>
                </div>
            </div>
        </form>

        {{-- ── Collections report: summary + date filter + CSV export ── --}}
        <div class="corp-form-card" style="margin-top:14px;">
            <div class="corp-form-card__head" style="flex-wrap:wrap; gap:10px;">
                <h6 class="corp-form-card__title"><i class="fas fa-chart-column"></i> {{ __('Collections') }}</h6>
                <form method="GET" action="{{ route('instructor.offline-payments.index') }}"
                      style="margin-left:auto; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto; height:36px;" title="{{ __('From') }}">
                    <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto; height:36px;" title="{{ __('To') }}">
                    <button type="submit" class="btn-corp-secondary" style="height:36px;"><i class="fas fa-filter"></i> {{ __('Apply') }}</button>
                    @if (($from ?? '') !== '' || ($to ?? '') !== '')
                        <a href="{{ route('instructor.offline-payments.index') }}" class="btn-corp-secondary" style="height:36px;"><i class="fas fa-times"></i></a>
                    @endif
                    <a href="{{ route('instructor.offline-payments.export', array_filter(['from' => $from, 'to' => $to])) }}"
                       class="btn-corp-secondary" style="height:36px;"><i class="fas fa-file-csv"></i> {{ __('Export CSV') }}</a>
                </form>
            </div>
            <div class="corp-form-card__body">
                <div class="corp-kpi" style="margin-bottom:0;">
                    <div class="corp-kpi__tile">
                        <div class="corp-kpi__label">{{ __('Collected') }}</div>
                        <div class="corp-kpi__value">{{ $curIcon }}{{ number_format($summary['total'], 2) }}</div>
                        <div class="corp-kpi__sub">{{ $summary['count'] }} {{ trans_choice('payment|payments', $summary['count']) }}</div>
                    </div>
                    <div class="corp-kpi__tile">
                        <div class="corp-kpi__label">{{ __('Tax collected') }}</div>
                        <div class="corp-kpi__value">{{ $curIcon }}{{ number_format($summary['tax'], 2) }}</div>
                        <div class="corp-kpi__sub">{{ __('included in total') }}</div>
                    </div>
                    <div class="corp-kpi__tile">
                        <div class="corp-kpi__label">{{ __('Awaiting approval') }}</div>
                        <div class="corp-kpi__value">{{ $summary['pending'] }}</div>
                        <div class="corp-kpi__sub">{{ __('not yet effective') }}</div>
                    </div>
                </div>
                @if (count($summary['by_method']))
                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:14px;">
                        @foreach ($summary['by_method'] as $row)
                            <span class="corp-pill corp-pill--muted">
                                {{ \App\Models\OfflinePayment::METHOD_META[$row->method][0] ?? ucfirst($row->method) }}:
                                {{ $curIcon }}{{ number_format((float) $row->s, 2) }}
                                <span style="opacity:.65;">({{ $row->c }})</span>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Recent offline payments (audit-logged) ── --}}
        <div class="corp-form-card" style="margin-top:14px;">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title"><i class="fas fa-clock-rotate-left"></i> {{ __('Recent offline payments') }}</h6>
                <span style="margin-left:auto; font-size:11px; color:var(--corp-subtle);">{{ __('audit-logged') }}</span>
            </div>
            @if ($payments->count() > 0)
                <div class="corp-table-wrap" style="border:none; border-radius:0;">
                    <table class="corp-table">
                        <thead>
                            <tr>
                                <th>{{ __('Student') }}</th>
                                <th>{{ __('For') }}</th>
                                <th>{{ __('Method') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Recorded by') }}</th>
                                <th style="text-align:right;">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payments as $p)
                                <tr>
                                    <td data-label="{{ __('Student') }}">{{ $p->student->name ?: '—' }}</td>
                                    <td data-label="{{ __('For') }}">{{ $p->course->title ?: __('Fee / other') }}</td>
                                    <td data-label="{{ __('Method') }}">
                                        {{ $p->methodLabel() }}@if ($p->reference_no) · <span style="color:var(--corp-muted); font-size:11px;">{{ $p->reference_no }}</span>@endif
                                    </td>
                                    <td data-label="{{ __('Amount') }}" style="font-weight:500;">{{ $curIcon }}{{ number_format((float) $p->amount, 2) }}</td>
                                    <td data-label="{{ __('Status') }}">
                                        @if ($p->cancelled_at)
                                            <span class="corp-pill corp-pill--muted">{{ __('Cancelled') }}</span>
                                        @elseif ($p->approval_status === \App\Models\OfflinePayment::APPROVAL_PENDING)
                                            <span class="corp-pill corp-pill--warning">{{ __('Needs approval') }}</span>
                                        @elseif ($p->approval_status === \App\Models\OfflinePayment::APPROVAL_REJECTED)
                                            <span class="corp-pill corp-pill--danger">{{ __('Rejected') }}</span>
                                        @elseif ($p->status === 'partial')
                                            <span class="corp-pill" style="background:#e0f2fe;color:#075985;">{{ __('Partial') }}</span>
                                        @else
                                            <span class="corp-pill corp-pill--success">{{ __('Paid') }}</span>
                                        @endif
                                    </td>
                                    <td data-label="{{ __('Recorded by') }}">
                                        {{ $p->recorder->name ?: '—' }}
                                        <div style="font-size:10.5px; color:var(--corp-subtle);">{{ optional($p->paid_at)->format('d M Y') }}</div>
                                    </td>
                                    <td data-label="{{ __('Actions') }}" style="text-align:right;">
                                        <div class="corp-actions" style="justify-content:flex-end;">
                                            @if (! $p->cancelled_at && $p->approval_status === \App\Models\OfflinePayment::APPROVAL_PENDING)
                                                <form method="POST" action="{{ route('instructor.offline-payments.approve', $p->id) }}" style="display:inline;">
                                                    @csrf
                                                    <button class="corp-actions__btn" title="{{ __('Approve') }}"><i class="fas fa-check"></i></button>
                                                </form>
                                                <form method="POST" action="{{ route('instructor.offline-payments.reject', $p->id) }}" style="display:inline;"
                                                      onsubmit="return confirm('{{ __('Reject this payment?') }}')">
                                                    @csrf
                                                    <button class="corp-actions__btn corp-actions__btn--danger" title="{{ __('Reject') }}"><i class="fas fa-xmark"></i></button>
                                                </form>
                                            @endif
                                            @if ($p->isEffective())
                                                <a href="{{ route('instructor.offline-payments.receipt', $p->id) }}" target="_blank" class="corp-actions__btn" title="{{ __('Receipt') }}"><i class="fas fa-file-invoice"></i></a>
                                                <a href="{{ route('instructor.offline-payments.receipt-pdf', $p->id) }}" class="corp-actions__btn" title="{{ __('Download PDF') }}"><i class="fas fa-file-pdf"></i></a>
                                            @endif
                                            @if ($p->proof_path)
                                                <a href="{{ route('instructor.offline-payments.proof', $p->id) }}" class="corp-actions__btn" title="{{ __('Proof') }}"><i class="fas fa-paperclip"></i></a>
                                            @endif
                                            @if (! $p->cancelled_at)
                                                <form method="POST" action="{{ route('instructor.offline-payments.cancel', $p->id) }}" style="display:inline;"
                                                      onsubmit="return confirm('{{ __('Cancel this payment? This reverses its effect.') }}')">
                                                    @csrf
                                                    <button class="corp-actions__btn corp-actions__btn--danger" title="{{ __('Cancel') }}"><i class="fas fa-ban"></i></button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($payments->hasPages())
                    <div style="padding:14px 18px; border-top:1px solid var(--corp-line-soft); display:flex; justify-content:center;">
                        {{ $payments->links() }}
                    </div>
                @endif
            @else
                <div class="corp-form-card__body">
                    <div class="corp-empty">
                        <div class="corp-empty__icon"><i class="fas fa-receipt"></i></div>
                        <div class="corp-empty__title">{{ __('No offline payments yet') }}</div>
                        <div class="corp-empty__hint">{{ __('Record your first cash / bank / UPI / cheque payment using the form above.') }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        (function () {
            var root = document.getElementById('offpay');
            // Method chips
            root.querySelectorAll('.op-method input').forEach(function (r) {
                r.addEventListener('change', function () {
                    root.querySelectorAll('.op-method').forEach(function (m) { m.classList.remove('on'); });
                    r.closest('.op-method').classList.add('on');
                });
            });
            // Status segments
            root.querySelectorAll('.op-seg input').forEach(function (r) {
                r.addEventListener('change', function () {
                    r.closest('.op-seg').querySelectorAll('label').forEach(function (l) { l.classList.remove('on'); });
                    r.closest('label').classList.add('on');
                });
            });
            // Proof filename
            var proof = document.getElementById('op-proof');
            if (proof) proof.addEventListener('change', function () {
                document.getElementById('op-proof-name').textContent = proof.files[0] ? proof.files[0].name : '{{ __('Click to upload (PDF / image)') }}';
            });
            // Batch filter by course
            var courseSel = document.getElementById('op-course'), batchSel = document.getElementById('op-batch');
            function filterBatches() {
                var cid = courseSel.value;
                Array.prototype.forEach.call(batchSel.options, function (o) {
                    if (!o.value) return;
                    var show = o.getAttribute('data-course') === cid;
                    o.hidden = !show;
                    if (!show && o.selected) { o.selected = false; batchSel.value = ''; }
                });
            }
            courseSel.addEventListener('change', filterBatches);
            filterBatches();
        })();
    </script>
@endsection
