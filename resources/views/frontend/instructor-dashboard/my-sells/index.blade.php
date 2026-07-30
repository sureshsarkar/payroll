@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    {{--
        Coach sales (my-sells) — rebuilt on the platform corp design system (2026-05).
        Uses the same primitives as course-batches and payout (corp-page,
        corp-header, corp-kpi, corp-form-card, corp-table, corp-pill,
        corp-actions, corp-empty, btn-corp-primary).

        Functional contract preserved 1:1:
          * $orders paginator (OrderItem rows, with order + course relations)
          * $totalRevenue, $totalCommission, $pendingCount
          * Routes: instructor.my-sells.create / show / print-invoice
          * Permission gates: 'coach-orders-create' / 'coach-orders-edit'
            (2026-07-13 — were the non-existent 'coach-sells-*' slugs, so staff
             granted "Create Order" never saw the button. Now match the catalog.)
    --}}

    @include('frontend.instructor-dashboard.settings.partials._corporate')

    <style>
        #mySells {
            --corp-brand:      {{ $brand->primaryColor ?: '#10b981' }};
            --corp-brand-2:    {{ $brand->accentColor  ?: '#059669' }};
            --corp-brand-grad: linear-gradient(135deg, var(--corp-brand) 0%, var(--corp-brand-2) 100%);
        }

        /* Small page-specific helpers — everything else is from corp-page */
        #mySells .se-course {
            display: flex; align-items: center; gap: 10px; min-width: 0;
        }
        #mySells .se-course__icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: color-mix(in srgb, var(--corp-brand) 10%, #fff);
            color: var(--corp-brand);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0;
            border: 1px solid color-mix(in srgb, var(--corp-brand) 16%, transparent);
        }
        #mySells .se-course__title {
            font-weight: 600; color: var(--corp-text);
            font-size: 13px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            max-width: 220px;
        }
        #mySells .se-buyer { display: flex; align-items: center; gap: 8px; min-width: 0; }
        #mySells .se-buyer__avatar {
            width: 26px; height: 26px; border-radius: 50%;
            background: var(--corp-brand-grad); color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 11px; flex-shrink: 0;
        }
        #mySells .se-buyer__name {
            font-weight: 500; color: var(--corp-text-2);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            max-width: 140px;
        }
        #mySells .se-invoice {
            display: inline-block; padding: 3px 9px;
            background: color-mix(in srgb, var(--corp-brand) 10%, #fff);
            color: var(--corp-brand);
            border-radius: 6px; font-weight: 700; font-size: 11.5px;
            border: 1px solid color-mix(in srgb, var(--corp-brand) 18%, transparent);
            font-variant-numeric: tabular-nums;
        }
        #mySells .se-amount { font-weight: 700; color: var(--corp-text); font-variant-numeric: tabular-nums; }
        #mySells .se-commission { font-weight: 700; color: #047857; font-variant-numeric: tabular-nums; }
        #mySells .se-date { font-size: 12px; color: var(--corp-text-2); font-weight: 500; white-space: nowrap; font-variant-numeric: tabular-nums; }
        #mySells .se-date small { display: block; color: var(--corp-muted); font-size: 10.5px; }
    </style>

    @php
        $canCreate = userAuth()->role == 'instructor'
            || (!in_array(strtolower(trim(userAuth()->role)), ['instructor','student'])
                && checkPermissionView('coach-orders-create'));
        $canEdit = userAuth()->role == 'instructor'
            || (!in_array(strtolower(trim(userAuth()->role)), ['instructor','student'])
                && checkPermissionView('coach-orders-edit'));
    @endphp

    <div class="corp-page" id="mySells">

        {{-- Header --}}
        <div class="corp-header">
            <div class="corp-header__title">
                <h4>{{ __('My sales') }}</h4>
                <p>{{ __('Every order on a course you own — revenue, your commission cut, and order status all in one place.') }}</p>
            </div>
            <div class="corp-header__actions">
                @if ($canCreate)
                    <a href="{{ route('instructor.my-sells.create') }}" class="btn-corp-primary">
                        <i class="fas fa-plus"></i> {{ __('Add manual order') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- KPI icon chip styles (scoped) --}}
        <style>
            #mySells .corp-kpi__tile { padding-left: 70px; min-height: 96px; }
            #mySells .corp-kpi__tile .se-kpi-icon {
                position: absolute; top: 18px; left: 18px;
                width: 38px; height: 38px; border-radius: 10px;
                display: inline-flex; align-items: center; justify-content: center;
                background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #ffffff);
                color: var(--accent, var(--corp-brand));
                border: 1px solid color-mix(in srgb, var(--accent, var(--corp-brand)) 18%, transparent);
                font-size: 15px;
            }
        </style>

        {{-- KPI strip --}}
        <div class="corp-kpi">
            <div class="corp-kpi__tile" style="--accent:var(--corp-brand);">
                <span class="se-kpi-icon"><i class="fas fa-receipt"></i></span>
                <div class="corp-kpi__label">{{ __('Total orders') }}</div>
                <div class="corp-kpi__value">{{ number_format($orders->total()) }}</div>
                <div class="corp-kpi__sub">{{ __('all-time count') }}</div>
            </div>
            <div class="corp-kpi__tile" style="--accent:var(--corp-brand);">
                <span class="se-kpi-icon"><i class="fas fa-coins"></i></span>
                <div class="corp-kpi__label">{{ __('Total revenue') }}</div>
                <div class="corp-kpi__value">{{ currency($totalRevenue) }}</div>
                <div class="corp-kpi__sub">{{ __('gross amount sold') }}</div>
            </div>
            <div class="corp-kpi__tile" style="--accent:#10b981;">
                <span class="se-kpi-icon"><i class="fas fa-hand-holding-usd"></i></span>
                <div class="corp-kpi__label">{{ __('Your commission') }}</div>
                <div class="corp-kpi__value" style="color:#047857;">{{ currency($totalCommission) }}</div>
                <div class="corp-kpi__sub">{{ __('after platform cut') }}</div>
            </div>
            <div class="corp-kpi__tile" style="--accent:#f59e0b;">
                <span class="se-kpi-icon"><i class="fas fa-clock"></i></span>
                <div class="corp-kpi__label">{{ __('Pending') }}</div>
                <div class="corp-kpi__value" style="color:{{ $pendingCount > 0 ? '#92400e' : 'var(--corp-text)' }};">
                    {{ number_format($pendingCount) }}
                </div>
                <div class="corp-kpi__sub">
                    {{ $pendingCount > 0 ? __('awaiting payment') : __('all paid up') }}
                </div>
            </div>
        </div>

        {{-- List card --}}
        <div class="corp-form-card" style="margin-top:14px;">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title">
                    <i class="fas fa-receipt"></i>
                    {{ __('Recent orders') }}
                    <span style="margin-left:auto; font-weight:500; color:var(--corp-muted); text-transform:none; letter-spacing:0; font-size:11px;">
                        {{ $orders->total() }} {{ trans_choice('order|orders', $orders->total()) }}
                    </span>
                </h6>
            </div>

            @if ($orders->count() > 0)
                <div class="corp-table-wrap" style="border:none; border-radius:0;">
                    <table class="corp-table">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>{{ __('Course') }}</th>
                                <th>{{ __('Buyer') }}</th>
                                <th>{{ __('Invoice') }}</th>
                                <th>{{ __('Price') }}</th>
                                <th>{{ __('Discount') }}</th>
                                <th>{{ __('Paid') }}</th>
                                <th>{{ __('Your cut') }}</th>
                                <th>{{ __('Order') }}</th>
                                <th>{{ __('Payment') }}</th>
                                <th>{{ __('Created') }}</th>
                                @if ($canEdit)
                                    <th style="width:100px; text-align:right;">{{ __('Actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $index => $o)
                                @php
                                    // 2026-07-07 ("Coach Order amount Issue") — earnings are on the
                                    // coupon-adjusted amount the student paid, not the original price.
                                    $orderRate      = (float) ($o->order->commission_rate ?? $o->commission_rate ?? 0);
                                    $originalPrice  = (float) $o->price;
                                    $netPaid        = $o->netPaid();
                                    $couponDiscount = round($originalPrice - $netPaid, 2);
                                    $amountAfterCommission = $o->coachPayout($orderRate);   // final coach earnings
                                    $status   = strtolower((string) ($o->order->status ?? ''));
                                    $payment  = strtolower((string) ($o->order->payment_status ?? ''));
                                    $statusPill  = $status  === 'completed' ? 'corp-pill--success' : ($status  === 'pending' ? 'corp-pill--warning' : 'corp-pill--danger');
                                    $paymentPill = $payment === 'paid'      ? 'corp-pill--success' : ($payment === 'pending' ? 'corp-pill--warning' : 'corp-pill--danger');
                                    $buyerName    = $o?->order?->user?->name ?? '—';
                                    $buyerInitial = strtoupper(substr($buyerName, 0, 1));
                                @endphp
                                <tr>
                                    <td data-label="#">{{ $orders->firstItem() + $index }}</td>
                                    <td data-label="{{ __('Course') }}">
                                        <div class="se-course">
                                            <span class="se-course__icon"><i class="fas fa-book-open"></i></span>
                                            <span class="se-course__title" title="{{ $o->course->title ?? '' }}">
                                                {{ $o->course->title ?? __('Course unavailable') }}
                                            </span>
                                        </div>
                                    </td>
                                    <td data-label="{{ __('Buyer') }}">
                                        <div class="se-buyer">
                                            <div class="se-buyer__avatar">{{ $buyerInitial }}</div>
                                            <span class="se-buyer__name" title="{{ $buyerName }}">{{ $buyerName }}</span>
                                        </div>
                                    </td>
                                    <td data-label="{{ __('Invoice') }}">
                                        <span class="se-invoice">#{{ $o?->order?->invoice_id ?? '—' }}</span>
                                    </td>
                                    <td data-label="{{ __('Price') }}">
                                        <span class="se-amount">{{ currency($originalPrice) }}</span>
                                    </td>
                                    <td data-label="{{ __('Discount') }}">
                                        @if ($couponDiscount > 0)
                                            <span class="se-amount" style="color:#b45309;">− {{ currency($couponDiscount) }}</span>
                                        @else
                                            <span style="color:#9ca3af;">—</span>
                                        @endif
                                    </td>
                                    <td data-label="{{ __('Paid') }}">
                                        <span class="se-amount">{{ currency($netPaid) }}</span>
                                    </td>
                                    <td data-label="{{ __('Your cut') }}">
                                        <span class="se-commission">{{ currency($amountAfterCommission) }}</span>
                                    </td>
                                    <td data-label="{{ __('Order') }}">
                                        <span class="corp-pill {{ $statusPill }}">{{ ucfirst($status) ?: '—' }}</span>
                                    </td>
                                    <td data-label="{{ __('Payment') }}">
                                        <span class="corp-pill {{ $paymentPill }}">{{ ucfirst($payment) ?: '—' }}</span>
                                    </td>
                                    <td data-label="{{ __('Created') }}">
                                        <span class="se-date">
                                            {{ $o->created_at->format('d M Y') }}
                                            <small>{{ $o->created_at->format('h:i A') }}</small>
                                        </span>
                                    </td>
                                    @if ($canEdit)
                                        <td data-label="{{ __('Actions') }}" style="text-align:right;">
                                            <div class="corp-actions" style="justify-content:flex-end;">
                                                <a href="{{ route('instructor.my-sells.show', $o->id) }}"
                                                   class="corp-actions__btn"
                                                   title="{{ __('View order') }}">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('instructor.my-sells.print-invoice', $o->id) }}"
                                                   class="corp-actions__btn"
                                                   title="{{ __('Download invoice') }}"
                                                   style="color:#047857;">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($orders->hasPages())
                    <div style="padding:14px 18px; border-top:1px solid var(--corp-line-soft); display:flex; justify-content:center;">
                        {{ $orders->links() }}
                    </div>
                @endif
            @else
                <div class="corp-form-card__body">
                    <div class="corp-empty">
                        <div class="corp-empty__icon"><i class="fas fa-shopping-bag"></i></div>
                        <div class="corp-empty__title">{{ __('No sales yet') }}</div>
                        <div class="corp-empty__hint">
                            {{ __('Once a student buys one of your courses, the order will appear here with your commission breakdown.') }}
                        </div>
                    </div>
                </div>
            @endif
        </div>

    </div>
@endsection
