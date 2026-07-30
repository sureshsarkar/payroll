@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    @php
        $order   = $orderitem->order;
        $student = $order?->user;
        $pay     = $order?->payment_status ?? 'pending';
        $ord     = $order?->status ?? 'pending';
        $qty     = (int) ($orderitem->qty ?: 1);
        $payBadge = match ($pay) {
            'paid'     => ['#ecfdf5', '#047857', __('Paid')],
            'refunded' => ['#fef2f2', '#991b1b', __('Refunded')],
            'failed'   => ['#fef2f2', '#991b1b', __('Failed')],
            default    => ['#fffbeb', '#92400e', __('Pending')],
        };
        $ordBadge = match ($ord) {
            'completed'  => ['#ecfdf5', '#047857', __('Completed')],
            'processing' => ['#eff6ff', '#1d4ed8', __('Processing')],
            'declined'   => ['#fef2f2', '#991b1b', __('Declined')],
            default      => ['#f3f4f6', '#374151', __('Pending')],
        };
    @endphp

    <style>
        .sell-card { border-radius:14px; background:#fff; box-shadow:0 8px 25px rgba(0,0,0,.05); overflow:hidden; }
        .sell-header { background:linear-gradient(135deg,#29a65c,#4fbe80); color:#fff; padding:22px 26px;
                       display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .sell-header h5 { margin:0 0 3px; }
        .sell-body { padding:26px; }
        .inv-dl { background:rgba(255,255,255,.18); color:#fff; border:1.5px solid rgba(255,255,255,.5);
                  border-radius:100px; padding:8px 16px; font-size:13px; font-weight:600; text-decoration:none;
                  display:inline-flex; align-items:center; gap:7px; }
        .inv-dl:hover { background:rgba(255,255,255,.30); color:#fff; }
        .badge-pill { display:inline-block; padding:4px 12px; border-radius:999px; font-size:11px; font-weight:700; }
        .det-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:18px; margin-bottom:8px; }
        .det-item { background:#f9fafb; border:1px solid #f1f3f5; border-radius:12px; padding:14px 16px; }
        .det-item .k { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; font-weight:600; margin-bottom:5px; }
        .det-item .v { font-size:15px; color:#111827; font-weight:600; word-break:break-word; }
        .det-section-title { font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#9ca3af; font-weight:700; margin:22px 0 12px; }
        .sf-field select { width:100%; font-size:14px; color:var(--n800); background:var(--n50);
                           border:1.5px solid var(--n200); border-radius:10px; padding:11px 38px 11px 14px;
                           outline:none; appearance:none; -webkit-appearance:none; cursor:pointer; }
        .sell-label { font-weight:600; margin-bottom:6px; }
        .select-wrap { position:relative; }
        .select-wrap::after { content:''; position:absolute; top:50%; right:14px; transform:translateY(-50%);
                              border:5px solid transparent; border-top:6px solid var(--n400); pointer-events:none; }
        .sell-footer { display:flex; align-items:center; justify-content:flex-end; gap:12px;
                       padding:18px 26px; border-top:1px solid #f1f3f5; }
        .btn-cancel-sf { font-size:14px; font-weight:500; padding:9px 20px; border-radius:100px; background:#f1f3f5;
                         color:#4b5563; border:1.5px solid #e4e8ec; text-decoration:none; display:inline-flex; align-items:center; gap:7px; }
        .btn-cancel-sf:hover { color:#4b5563; }
        .amount-big { font-size:22px; font-weight:800; color:#047857; }
    </style>

    <style>
        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
        html[data-theme="dark"] .sell-card { background:#1e293b; }
        html[data-theme="dark"] .det-item { background:#17233a; border-color:#2a3a55; }
        html[data-theme="dark"] .det-item .k { color:#94a3b8; }
        html[data-theme="dark"] .det-item .v { color:#e2e8f0; }
        html[data-theme="dark"] .det-section-title { color:#94a3b8; }
        html[data-theme="dark"] .sell-footer { border-top-color:#2a3a55; }
        html[data-theme="dark"] .btn-cancel-sf { background:#22304a; color:#94a3b8; border-color:#2a3a55; }
    </style>

    <div class="dashboard__content-wrap">
        <div class="sell-card">

            {{-- Header --}}
            <div class="sell-header">
                <div>
                    <h5 class="text-light">{{ __('Sale / Order Details') }}</h5>
                    <small>{{ __('Invoice') }}: <strong>{{ $order?->invoice_id ?? '—' }}</strong>
                        @if ($order?->created_at) · {{ $order->created_at->format('d M Y, h:i A') }} @endif
                    </small>
                </div>
                <a href="{{ route('instructor.my-sells.print-invoice', $orderitem->id) }}" class="inv-dl">
                    <i class="fas fa-download"></i> {{ __('Download invoice') }}
                </a>
            </div>

            <div class="sell-body">

                {{-- Status badges --}}
                <div style="margin-bottom:18px; display:flex; gap:10px; flex-wrap:wrap;">
                    <span>{{ __('Payment') }}:
                        <span class="badge-pill" style="background:{{ $payBadge[0] }}; color:{{ $payBadge[1] }};">{{ $payBadge[2] }}</span>
                    </span>
                    <span>{{ __('Order') }}:
                        <span class="badge-pill" style="background:{{ $ordBadge[0] }}; color:{{ $ordBadge[1] }};">{{ $ordBadge[2] }}</span>
                    </span>
                </div>

                {{-- Student --}}
                <div class="det-section-title">{{ __('Student') }}</div>
                <div class="det-grid">
                    <div class="det-item"><div class="k">{{ __('Name') }}</div><div class="v">{{ $student?->name ?? __('Deleted / Guest') }}</div></div>
                    <div class="det-item"><div class="k">{{ __('Email') }}</div><div class="v">{{ $student?->email ?? '—' }}</div></div>
                    <div class="det-item"><div class="k">{{ __('Phone') }}</div><div class="v">{{ $student?->phone ?? '—' }}</div></div>
                </div>

                {{-- Course / amount --}}
                <div class="det-section-title">{{ __('Purchase') }}</div>
                <div class="det-grid">
                    <div class="det-item"><div class="k">{{ __('Course') }}</div><div class="v">{{ $orderitem->course?->title ?? '—' }}</div></div>
                    <div class="det-item"><div class="k">{{ __('Batch') }}</div><div class="v">{{ $batch?->title ?? __('—') }}</div></div>
                    <div class="det-item"><div class="k">{{ __('Payment method') }}</div><div class="v">{{ $order?->payment_method ?? '—' }}</div></div>
                    {{-- 2026-07-07 ("Coach Order amount Issue") — full money breakdown:
                         commission is on the coupon-adjusted amount the student paid,
                         and earnings are shown after coupon discount + admin commission. --}}
                    @php
                        $detRate = (float) ($order->commission_rate ?? $orderitem->commission_rate ?? 0);
                        $detOrig = (float) $orderitem->price;
                        $detPaid = $orderitem->netPaid();
                        $detDisc = round($detOrig - $detPaid, 2);
                        $detEarn = $orderitem->coachPayout($detRate);
                    @endphp
                    <div class="det-item">
                        <div class="k">{{ __('Original Course Price') }}</div>
                        <div class="v">{{ currency($detOrig) }}</div>
                    </div>
                    @if ($detDisc > 0)
                        <div class="det-item">
                            <div class="k">{{ __('Coupon Discount') }} @if ($order?->coupon_code)<span style="color:#6b7280;">({{ $order->coupon_code }})</span>@endif</div>
                            <div class="v" style="color:#b45309;">− {{ currency($detDisc) }}</div>
                        </div>
                    @endif
                    <div class="det-item">
                        <div class="k">{{ __('Final Amount Paid by Student') }} @if ($qty > 1) <span style="color:#6b7280;">(×{{ $qty }})</span> @endif</div>
                        <div class="v amount-big">{{ currency($detPaid) }}</div>
                    </div>
                    <div class="det-item">
                        <div class="k">{{ __('Final Coach Earnings') }} <span style="color:#6b7280;">({{ __('after commission') }})</span></div>
                        <div class="v" style="color:#047857;">{{ currency($detEarn) }}</div>
                    </div>
                </div>

                {{-- Update status form (unchanged behaviour) --}}
                <div class="det-section-title">{{ __('Update Status') }}</div>
                <form action="{{ route('instructor.my-sells.update', $orderitem->id) }}" method="POST">
                    @csrf
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="sf-field">
                                <label class="sell-label">{{ __('Payment Status') }}</label>
                                <div class="select-wrap">
                                    <select name="payment_status" class="form-control">
                                        <option value="pending"  @selected(old('payment_status', $pay) == 'pending')>{{ __('Pending') }}</option>
                                        <option value="paid"     @selected(old('payment_status', $pay) == 'paid')>{{ __('Paid') }}</option>
                                        {{-- 'refunded' triggers the wallet-reversal + access-revoke path --}}
                                        <option value="refunded" @selected(old('payment_status', $pay) == 'refunded')>{{ __('Refunded') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="sf-field">
                                <label class="sell-label">{{ __('Order Status') }}</label>
                                <div class="select-wrap">
                                    <select name="order_status" class="form-control">
                                        <option value="pending"    @selected(old('order_status', $ord) == 'pending')>{{ __('Pending') }}</option>
                                        <option value="processing" @selected(old('order_status', $ord) == 'processing')>{{ __('Processing') }}</option>
                                        <option value="completed"  @selected(old('order_status', $ord) == 'completed')>{{ __('Completed') }}</option>
                                        <option value="declined"   @selected(old('order_status', $ord) == 'declined')>{{ __('Declined') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="sell-footer mt-4" style="border:none; padding:18px 0 0;">
                        <a href="{{ route('instructor.my-sells.index') }}" class="btn-cancel-sf"><i class="bi bi-x-lg"></i> {{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-success px-4">✓ {{ __('Update Status') }}</button>
                    </div>
                </form>

                @if ($pay != 'paid')
                    {{-- 2026-07-11 (Offline Payment Phase 2) — record how this order was
                         paid offline; marks it paid + enrolls the student, capturing the
                         method / reference / date / proof. No online gateway. --}}
                    <div class="det-section-title" style="margin-top:24px;">{{ __('Record offline payment') }}</div>
                    <form action="{{ route('instructor.offline-payments.record-order', $orderitem->order_id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="sf-field">
                                    <label class="sell-label">{{ __('Payment method') }}</label>
                                    <div class="select-wrap">
                                        <select name="method" class="form-control" required>
                                            @foreach (offlineEnabledMethods() as $mKey => $mMeta)
                                                <option value="{{ $mKey }}">{{ __($mMeta[0]) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="sf-field">
                                    <label class="sell-label">{{ __('Payment date') }}</label>
                                    <input type="date" name="paid_at" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="sf-field">
                                    <label class="sell-label">{{ __('Transaction / reference no.') }}</label>
                                    <input type="text" name="reference_no" maxlength="128" class="form-control" placeholder="{{ __('UTR, cheque no., UPI ref…') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="sf-field">
                                    <label class="sell-label">{{ __('Receipt / proof') }}</label>
                                    <input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="sf-field">
                                    <label class="sell-label">{{ __('Notes') }}</label>
                                    <input type="text" name="notes" maxlength="1000" class="form-control" placeholder="{{ __('e.g. received in cash at front desk') }}">
                                </div>
                            </div>
                        </div>
                        <div class="sell-footer mt-4" style="border:none; padding:18px 0 0;">
                            <button type="submit" class="btn btn-success px-4"><i class="bi bi-cash-stack"></i> {{ __('Record offline payment') }}</button>
                        </div>
                    </form>
                @endif

            </div>
        </div>
    </div>
@endsection
