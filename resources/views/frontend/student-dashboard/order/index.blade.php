@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
    {{--
        Student order history — corporate redesign (2026-05).

        Same data + routes + client-side filter behaviour. Inter font,
        brand-aware via $brand. Replaces the dark Sora/JetBrains-Mono
        layout with the platform corp tokens.

        Functional contract preserved 1:1:
          * $orders paginator with orderItems → course (id, title) eager loaded
          * $completedCount + $pendingCount drive the KPI tiles
          * Per-row data-status + data-invoice attrs drive the
            JS filter + search (preserved verbatim)
          * Routes: student.order.show($id), payment(invoice_id=...)
          * Pagination via $orders->links()
    --}}

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    <style>
        .so-page {
            --so-brand:       {{ $brand->primaryColor ?: '#10b981' }};
            --so-brand-2:     {{ $brand->accentColor  ?: '#059669' }};
            --so-text:        #0b1220;
            --so-text-2:      #1f2937;
            --so-muted:       #6b7280;
            --so-subtle:      #9ca3af;
            --so-line:        #e5e7eb;
            --so-line-soft:   #f1f3f5;
            --so-bg:          #fafbfc;
            --so-card:        #ffffff;
            --so-shadow-sm:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 2px 6px -2px rgba(15, 23, 42, 0.04);
            --so-shadow-md:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 6px 18px -6px rgba(15, 23, 42, 0.08),
                0 12px 36px -12px rgba(15, 23, 42, 0.08);
            --so-ease: cubic-bezier(.22, 1, .36, 1);

            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            padding: 4px 8px 28px 0;       /* right gutter clears the floating top-right toolbar */
            color: var(--so-text);
        }
        @media (max-width: 768px) { .so-page { padding: 4px 0 20px; } }
        .so-page *, .so-page *::before, .so-page *::after { box-sizing: border-box; }
        .so-page .so-tabular {
            font-variant-numeric: tabular-nums; font-feature-settings: 'tnum';
        }

        /* Header */
        .so-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            gap: 16px; flex-wrap: wrap; margin-bottom: 22px;
        }
        .so-eyebrow {
            font-size: 11px; font-weight: 700; color: var(--so-brand);
            text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 6px;
        }
        .so-header h1 {
            font-size: 24px; font-weight: 800; color: var(--so-text);
            margin: 0 0 4px; letter-spacing: -0.028em; line-height: 1.2;
        }
        .so-header__sub { font-size: 13.5px; color: var(--so-muted); margin: 0; line-height: 1.5; }

        /* KPI grid */
        .so-kpi-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 14px; margin-bottom: 22px;
        }
        @media (max-width: 700px) { .so-kpi-grid { grid-template-columns: 1fr; } }
        .so-kpi {
            background: var(--so-card); border: 1px solid var(--so-line);
            border-radius: 12px; padding: 16px 18px;
            box-shadow: var(--so-shadow-sm);
            display: flex; align-items: center; gap: 14px;
            position: relative;
        }
        .so-kpi::before {
            content: ''; position: absolute; top: 0; left: 18px; right: 18px;
            height: 2px; border-radius: 0 0 2px 2px;
            background: var(--so-accent, var(--so-brand)); opacity: 0.85;
        }
        .so-kpi__icon {
            width: 38px; height: 38px; border-radius: 10px;
            background: color-mix(in srgb, var(--so-accent, var(--so-brand)) 12%, #fff);
            color: var(--so-accent, var(--so-brand));
            display: flex; align-items: center; justify-content: center; font-size: 14px;
            border: 1px solid color-mix(in srgb, var(--so-accent, var(--so-brand)) 18%, transparent);
            flex-shrink: 0;
        }
        .so-kpi__body { min-width: 0; flex: 1; }
        .so-kpi__label {
            font-size: 11px; font-weight: 700; color: var(--so-muted);
            text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px;
        }
        .so-kpi__value {
            font-size: 22px; font-weight: 800; color: var(--so-text);
            line-height: 1.05; letter-spacing: -0.025em;
        }

        /* Toolbar */
        .so-toolbar {
            display: flex; align-items: center; gap: 10px;
            flex-wrap: wrap; margin-bottom: 14px;
        }
        .so-pills {
            display: flex; gap: 4px; padding: 4px;
            background: var(--so-card); border: 1px solid var(--so-line);
            border-radius: 10px; box-shadow: var(--so-shadow-sm);
            flex-wrap: wrap;
        }
        .so-pill {
            font-size: 12.5px; font-weight: 600;
            padding: 6px 14px; border-radius: 7px;
            color: var(--so-muted); cursor: pointer; border: 0; background: transparent;
            font-family: inherit;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all .15s var(--so-ease);
        }
        .so-pill:hover { color: var(--so-text); background: var(--so-line-soft); }
        .so-pill.active { background: var(--so-brand); color: #fff; }
        .so-pill.active:hover { color: #fff; }
        .so-pill i { font-size: 10px; }

        .so-search {
            display: flex; align-items: center; gap: 8px;
            padding: 6px 12px; background: #fff;
            border: 1px solid var(--so-line); border-radius: 10px;
            flex: 1; min-width: 220px; max-width: 360px; margin-left: auto;
            box-shadow: var(--so-shadow-sm);
            transition: border-color .15s var(--so-ease), box-shadow .15s var(--so-ease);
        }
        .so-search:focus-within {
            border-color: var(--so-brand);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--so-brand) 18%, transparent);
        }
        .so-search i { color: var(--so-subtle); font-size: 13px; }
        .so-search input {
            flex: 1; border: 0; outline: none; font-size: 13.5px;
            color: var(--so-text); font-family: inherit; background: transparent;
        }
        .so-search input::placeholder { color: var(--so-subtle); }

        /* Card + Table */
        .so-card {
            background: var(--so-card); border: 1px solid var(--so-line);
            border-radius: 14px; box-shadow: var(--so-shadow-sm);
            overflow: hidden;
        }
        .so-table { width: 100%; border-collapse: collapse; }
        .so-table thead th {
            font-size: 11px; font-weight: 700; color: var(--so-muted);
            text-transform: uppercase; letter-spacing: 0.06em;
            text-align: left; padding: 12px 16px;
            background: var(--so-line-soft);
            border-bottom: 1px solid var(--so-line);
            white-space: nowrap;
        }
        .so-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--so-line-soft);
            font-size: 13px; color: var(--so-text-2); vertical-align: middle;
        }
        .so-table tbody tr:last-child td { border-bottom: 0; }
        .so-table tbody tr {
            transition: background-color .12s var(--so-ease);
        }
        .so-table tbody tr:hover {
            background: color-mix(in srgb, var(--so-brand) 4%, #ffffff);
        }

        .so-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 7px;
            background: var(--so-line-soft); color: var(--so-muted);
            font-size: 11.5px; font-weight: 700;
        }
        .so-course {
            font-weight: 600; color: var(--so-text);
            max-width: 280px;
            display: inline-block;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .so-invoice {
            display: inline-block; padding: 3px 9px;
            background: color-mix(in srgb, var(--so-brand) 10%, #fff);
            color: var(--so-brand);
            border-radius: 6px; font-weight: 700; font-size: 12px;
            border: 1px solid color-mix(in srgb, var(--so-brand) 18%, transparent);
            font-variant-numeric: tabular-nums;
        }
        .so-amount {
            font-weight: 700; color: var(--so-text);
            font-variant-numeric: tabular-nums;
        }
        .so-currency {
            font-size: 11px; color: var(--so-subtle); font-weight: 500; margin-left: 4px;
        }
        .so-gateway {
            font-size: 12px; color: var(--so-text-2); font-weight: 500;
        }

        .so-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 9px; border-radius: 999px;
            font-size: 10.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
            white-space: nowrap;
        }
        .so-badge--completed { background: #ecfdf5; color: #047857; }
        .so-badge--paid      { background: #ecfdf5; color: #047857; }
        .so-badge--processing{ background: #eff6ff; color: #1d4ed8; }
        .so-badge--pending   { background: #fffbeb; color: #92400e; }
        .so-badge--declined  { background: #fef2f2; color: #991b1b; }
        .so-badge--cancelled { background: #fef2f2; color: #991b1b; }
        .so-badge::before {
            content: ''; width: 5px; height: 5px; border-radius: 50%;
            background: currentColor; flex-shrink: 0;
        }

        .so-actions { display: inline-flex; gap: 6px; justify-content: flex-end; }
        .so-action {
            width: 30px; height: 30px; border-radius: 7px;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--so-line-soft); color: var(--so-muted);
            text-decoration: none; font-size: 12px;
            transition: all .15s var(--so-ease);
        }
        .so-action:hover {
            background: color-mix(in srgb, var(--so-brand) 12%, #fff);
            color: var(--so-brand); text-decoration: none;
        }
        .so-pay {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 8px;
            background: var(--so-brand); color: #fff;
            font-size: 11.5px; font-weight: 600; text-decoration: none;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            transition: all .15s var(--so-ease);
        }
        .so-pay:hover {
            background: color-mix(in srgb, var(--so-brand) 88%, #000);
            color: #fff; text-decoration: none;
        }
        .so-pay i { font-size: 10px; }

        /* Empty */
        .so-empty {
            text-align: center; padding: 60px 24px;
        }
        .so-empty__icon {
            width: 56px; height: 56px; margin: 0 auto 14px; border-radius: 14px;
            background: color-mix(in srgb, var(--so-brand) 10%, #fff);
            color: var(--so-brand);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            border: 1px solid color-mix(in srgb, var(--so-brand) 18%, transparent);
        }
        .so-empty__title {
            font-size: 16px; font-weight: 700; color: var(--so-text);
            margin-bottom: 6px; letter-spacing: -0.015em;
        }
        .so-empty__text {
            font-size: 13.5px; color: var(--so-muted); line-height: 1.5;
            margin-bottom: 16px;
        }
        .so-empty__cta {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px; border-radius: 9px;
            background: var(--so-brand); color: #fff;
            font-size: 13px; font-weight: 600; text-decoration: none;
        }
        .so-empty__cta:hover {
            background: color-mix(in srgb, var(--so-brand) 88%, #000); color: #fff; text-decoration: none;
        }

        /* Pagination */
        .so-pagination {
            padding: 14px 16px; border-top: 1px solid var(--so-line-soft);
            display: flex; justify-content: center;
        }
        .so-pagination .pagination { gap: 4px; margin: 0; }
        .so-pagination .page-item .page-link {
            background: var(--so-card); border: 1px solid var(--so-line);
            color: var(--so-text-2); border-radius: 8px !important;
            padding: 7px 13px; font-size: 12.5px; font-weight: 600;
            font-family: inherit;
            transition: all .15s var(--so-ease);
        }
        .so-pagination .page-item .page-link:hover {
            border-color: var(--so-brand); color: var(--so-brand);
        }
        .so-pagination .page-item.active .page-link {
            background: var(--so-brand); border-color: var(--so-brand); color: #fff;
        }
        .so-pagination .page-item.disabled .page-link {
            color: var(--so-subtle); background: var(--so-line-soft);
        }

        @media (max-width: 900px) {
            .so-hide-sm { display: none; }
        }
    </style>

    <style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] .so-page {
        --so-text: #e2e8f0;
        --so-text-2: #cbd5e1;
        --so-muted: #94a3b8;
        --so-subtle: #94a3b8;
        --so-line: #2a3a55;
        --so-line-soft: #22304a;
        --so-bg: #17233a;
        --so-card: #1e293b;
        --so-shadow-sm: none;
        --so-shadow-md: none;
    }
    html[data-theme="dark"] .so-search { background: #1e293b; }
    html[data-theme="dark"] .so-table tbody tr:hover {
        background: color-mix(in srgb, var(--so-brand) 10%, #1e293b);
    }
    </style>

    @php $total = $orders->total() ?? $orders->count(); @endphp

    <div class="so-page">

        <header class="so-header">
            <div>
                <div class="so-eyebrow">{{ __('My orders') }}</div>
                <h1>{{ __('Order history') }}</h1>
                <p class="so-header__sub">
                    {{ __('Every purchase you have made — pay pending invoices, download receipts, and review what you bought.') }}
                </p>
            </div>
        </header>

        <div class="so-kpi-grid">
            <div class="so-kpi" style="--so-accent: var(--so-brand);">
                <span class="so-kpi__icon"><i class="fas fa-receipt"></i></span>
                <div class="so-kpi__body">
                    <div class="so-kpi__label">{{ __('Total orders') }}</div>
                    <div class="so-kpi__value so-tabular">{{ $total }}</div>
                </div>
            </div>
            <div class="so-kpi" style="--so-accent: #10b981;">
                <span class="so-kpi__icon"><i class="fas fa-check-circle"></i></span>
                <div class="so-kpi__body">
                    <div class="so-kpi__label">{{ __('Completed') }}</div>
                    <div class="so-kpi__value so-tabular">{{ $completedCount }}</div>
                </div>
            </div>
            <div class="so-kpi" style="--so-accent: #f59e0b;">
                <span class="so-kpi__icon"><i class="fas fa-clock"></i></span>
                <div class="so-kpi__body">
                    <div class="so-kpi__label">{{ __('Pending') }}</div>
                    <div class="so-kpi__value so-tabular">{{ $pendingCount }}</div>
                </div>
            </div>
        </div>

        <div class="so-toolbar">
            <div class="so-pills">
                <button class="so-pill active" data-filter="all" type="button">
                    <i class="fas fa-list"></i> {{ __('All') }}
                </button>
                <button class="so-pill" data-filter="completed" type="button">
                    <i class="fas fa-check-circle"></i> {{ __('Completed') }}
                </button>
                <button class="so-pill" data-filter="pending" type="button">
                    <i class="fas fa-clock"></i> {{ __('Pending') }}
                </button>
            </div>
            <div class="so-search">
                <i class="fas fa-search"></i>
                <input type="text" id="oh-search-input" placeholder="{{ __('Search by invoice ID…') }}">
            </div>
        </div>

        <div class="so-card">
            @if ($orders->count() > 0)
                <table class="so-table" id="oh-orders-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Course') }}</th>
                            <th>{{ __('Invoice') }}</th>
                            <th class="so-hide-sm">{{ __('Amount') }}</th>
                            <th class="so-hide-sm">{{ __('Gateway') }}</th>
                            <th>{{ __('Order') }}</th>
                            <th>{{ __('Payment') }}</th>
                            <th style="text-align:right;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $index => $order)
                            @php
                                $firstItem = $order->orderItems->first();
                                $courseTitle = $firstItem?->course?->title ?? '—';
                                $extraCount = max(0, $order->orderItems->count() - 1);
                                $orderStatusKey = strtolower((string) $order->status);
                                $paymentStatusKey = strtolower((string) $order->payment_status);
                                $orderRowNumber = ($orders->currentPage() - 1) * $orders->perPage() + $index + 1;
                            @endphp
                            <tr data-status="{{ $order->status }}"
                                data-invoice="{{ strtolower($order->invoice_id) }}">
                                <td><span class="so-num so-tabular">{{ $orderRowNumber }}</span></td>
                                <td>
                                    <span class="so-course" title="{{ $courseTitle }}">{{ $courseTitle }}</span>
                                    @if ($extraCount > 0)
                                        <span class="so-currency">+{{ $extraCount }} {{ __('more') }}</span>
                                    @endif
                                </td>
                                <td><span class="so-invoice">#{{ $order->invoice_id }}</span></td>
                                <td class="so-hide-sm">
                                    <span class="so-amount">{{ number_format($order->paid_amount, 2) }}</span>
                                    <span class="so-currency">{{ $order->payable_currency }}</span>
                                </td>
                                <td class="so-hide-sm">
                                    <span class="so-gateway">{{ ucfirst((string) $order->payment_method) }}</span>
                                </td>
                                <td>
                                    @switch($orderStatusKey)
                                        @case('completed')
                                            <span class="so-badge so-badge--completed">{{ __('Completed') }}</span>
                                            @break
                                        @case('processing')
                                            <span class="so-badge so-badge--processing">{{ __('Processing') }}</span>
                                            @break
                                        @case('declined')
                                            <span class="so-badge so-badge--declined">{{ __('Declined') }}</span>
                                            @break
                                        @default
                                            <span class="so-badge so-badge--pending">{{ __('Pending') }}</span>
                                    @endswitch
                                </td>
                                <td>
                                    @switch($paymentStatusKey)
                                        @case('paid')
                                            <span class="so-badge so-badge--paid">{{ __('Paid') }}</span>
                                            @break
                                        @case('cancelled')
                                            <span class="so-badge so-badge--cancelled">{{ __('Cancelled') }}</span>
                                            @break
                                        @default
                                            <span class="so-badge so-badge--pending">{{ __('Unpaid') }}</span>
                                    @endswitch
                                </td>
                                <td>
                                    <div class="so-actions">
                                        {{-- 2026-06-03 (Model B) — a coach-created (coach_manual) order is an
                                             invoice the STUDENT pays online via a real gateway, so "Pay now"
                                             must show for it too. The stored "coach_manual" isn't a real
                                             gateway, so the payment page lets the student pick an active
                                             gateway (Razorpay, etc.) when the order's method isn't payable —
                                             see PaymentController@index. --}}
                                        @if ($orderStatusKey === 'pending')
                                            <a target="_blank"
                                               href="{{ route('payment', ['invoice_id' => $order->invoice_id]) }}"
                                               class="so-pay" title="{{ __('Pay now') }}">
                                                <i class="fas fa-credit-card"></i>
                                                {{ __('Pay now') }}
                                            </a>
                                        @endif
                                        <a href="{{ route('student.order.show', $order->id) }}"
                                           class="so-action" title="{{ __('View order') }}">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($orders->hasPages())
                    <div class="so-pagination">{{ $orders->links() }}</div>
                @endif
            @else
                <div class="so-empty">
                    <div class="so-empty__icon"><i class="fas fa-receipt"></i></div>
                    <div class="so-empty__title">{{ __('No orders yet') }}</div>
                    <div class="so-empty__text">{{ __('Your purchased courses will appear here once you make your first enrollment.') }}</div>
                    <a class="so-empty__cta" href="{{ url('/courses') }}">
                        <i class="fas fa-search" style="font-size:11px;"></i>
                        {{ __('Browse courses') }}
                    </a>
                </div>
            @endif
        </div>

    </div>

    <script>
        (function () {
            var searchInput = document.getElementById('oh-search-input');
            var filterBtns  = document.querySelectorAll('.so-pill');
            var rows        = document.querySelectorAll('#oh-orders-table tbody tr[data-status]');
            var activeFilter = 'all';

            function apply() {
                var q = (searchInput.value || '').toLowerCase().trim();
                rows.forEach(function (row) {
                    var status   = row.dataset.status;
                    var invoice  = row.dataset.invoice || '';
                    var matchF   = (activeFilter === 'all') || (status === activeFilter);
                    var matchS   = !q || invoice.includes(q);
                    row.style.display = (matchF && matchS) ? '' : 'none';
                });
            }
            if (searchInput) searchInput.addEventListener('input', apply);
            filterBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    filterBtns.forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    activeFilter = btn.dataset.filter;
                    apply();
                });
            });
        })();
    </script>
@endsection
