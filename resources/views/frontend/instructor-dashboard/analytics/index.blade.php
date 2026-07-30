@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@php
    // 2026-07-09 — currency shown on the charts must match the platform currency
    // (₹ for an INR store), not a hardcoded "$". Dynamic: follows whatever the
    // default currency is set to, for every coach.
    getSessionCurrency();
    $curIcon = \Illuminate\Support\Facades\Session::get('currency_icon', '₹');
    $curCode = \Illuminate\Support\Facades\Session::get('currency_code', 'INR');
@endphp
<div class="mbs-analytics" style="padding: 8px 4px;">

    {{-- Header + range filter --}}
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
        <div>
            <h2 style="margin:0; color:#1c1a4a;">
                <i class="fas fa-chart-line" style="color:var(--corp-brand); margin-right:8px;"></i>
                {{ __('Analytics') }}
            </h2>
            <p style="color:#6b7280; margin:4px 0 0; font-size:13px;">
                @if (!empty($isCustom))
                    {{ __('Your business at a glance —') }} {{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}
                @else
                    {{ __('Your business at a glance — last') }} {{ $range }} {{ __('days') }}
                @endif
            </p>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <div style="display:flex; gap:6px; background:#f3f4f6; padding:4px; border-radius:10px;">
                @foreach ([7 => '7D', 30 => '30D', 90 => '90D', 365 => '1Y'] as $days => $label)
                    <a href="{{ route('instructor.analytics.index', ['range' => $days]) }}"
                       style="padding:6px 14px; border-radius:7px; font-size:12px; font-weight:600; text-decoration:none; {{ (empty($isCustom) && $range === $days) ? 'background:#fff; color:var(--corp-brand); box-shadow:0 1px 2px rgba(0,0,0,0.06);' : 'color:#6b7280;' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            {{-- Custom From/To date range (2026-07-10) --}}
            <form method="GET" action="{{ route('instructor.analytics.index') }}"
                  style="display:flex; gap:6px; align-items:center; background:#f3f4f6; padding:4px 6px; border-radius:10px; {{ !empty($isCustom) ? 'box-shadow:0 0 0 2px var(--corp-brand);' : '' }}">
                <input type="date" name="from" value="{{ $from ?? '' }}" required
                       style="border:1px solid #e2e8f0; border-radius:6px; padding:5px 8px; font-size:12px; color:#334155; background:#fff;">
                <span style="color:#9ca3af; font-size:12px;">–</span>
                <input type="date" name="to" value="{{ $to ?? '' }}" required
                       style="border:1px solid #e2e8f0; border-radius:6px; padding:5px 8px; font-size:12px; color:#334155; background:#fff;">
                <button type="submit"
                        style="border:none; border-radius:6px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; {{ !empty($isCustom) ? 'background:var(--corp-brand); color:#fff;' : 'background:#fff; color:var(--corp-brand);' }}">
                    <i class="fas fa-calendar-day" style="font-size:11px; margin-right:4px;"></i>{{ __('Apply') }}
                </button>
            </form>
        </div>
    </div>

    {{-- KPI cards --}}
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
        <div class="mbs-kpi" style="background:#fff; padding:18px 20px; border-radius:12px; border:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <span style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px;">{{ __('Revenue') }}</span>
                <i class="fas fa-coins" style="color:#10b981; background:#dcfce7; padding:6px; border-radius:6px; font-size:11px;"></i>
            </div>
            <div style="font-size:24px; font-weight:700; color:#1c1a4a;">{{ currency($revenueCurrent) }}</div>
            <div style="font-size:11px; color:{{ $revenueDelta >= 0 ? '#10b981' : '#ef4444' }}; margin-top:4px;">
                <i class="fas fa-arrow-{{ $revenueDelta >= 0 ? 'up' : 'down' }}"></i>
                {{ $revenueDelta >= 0 ? '+' : '' }}{{ $revenueDelta }}% {{ __('vs prev period') }}
            </div>
        </div>

        <div class="mbs-kpi" style="background:#fff; padding:18px 20px; border-radius:12px; border:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <span style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px;">{{ __('Sales') }}</span>
                <i class="fas fa-shopping-cart" style="color:#3b82f6; background:#dbeafe; padding:6px; border-radius:6px; font-size:11px;"></i>
            </div>
            <div style="font-size:24px; font-weight:700; color:#1c1a4a;">{{ number_format($salesCount) }}</div>
            <div style="font-size:11px; color:#6b7280; margin-top:4px;">{{ __('paid orders') }}</div>
        </div>

        <div class="mbs-kpi" style="background:#fff; padding:18px 20px; border-radius:12px; border:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <span style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px;">{{ __('Students') }}</span>
                <i class="fas fa-users" style="color:#059669; background:#ede9fe; padding:6px; border-radius:6px; font-size:11px;"></i>
            </div>
            <div style="font-size:24px; font-weight:700; color:#1c1a4a;">{{ number_format($uniqueStudents) }}</div>
            <div style="font-size:11px; color:#6b7280; margin-top:4px;">{{ __('total enrolled') }}</div>
        </div>

        <div class="mbs-kpi" style="background:#fff; padding:18px 20px; border-radius:12px; border:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <span style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px;">{{ __('Rating') }}</span>
                <i class="fas fa-star" style="color:#f59e0b; background:#fef3c7; padding:6px; border-radius:6px; font-size:11px;"></i>
            </div>
            <div style="font-size:24px; font-weight:700; color:#1c1a4a;">{{ $avgRating > 0 ? $avgRating : '—' }}</div>
            <div style="font-size:11px; color:#6b7280; margin-top:4px;">{{ $reviewsCount }} {{ __('reviews') }}</div>
        </div>

        <div class="mbs-kpi" style="background:#fff; padding:18px 20px; border-radius:12px; border:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <span style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px;">{{ __('Avg Order Value') }}</span>
                <i class="fas fa-receipt" style="color:#06b6d4; background:#cffafe; padding:6px; border-radius:6px; font-size:11px;"></i>
            </div>
            <div style="font-size:24px; font-weight:700; color:#1c1a4a;">{{ currency($aov) }}</div>
            <div style="font-size:11px; color:#6b7280; margin-top:4px;">{{ __('per paid order') }}</div>
        </div>

        <div class="mbs-kpi" style="background:#fff; padding:18px 20px; border-radius:12px; border:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <span style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px;">{{ __('Refund Rate') }}</span>
                <i class="fas fa-undo" style="color:{{ $refundRate > 5 ? '#ef4444' : '#10b981' }}; background:{{ $refundRate > 5 ? '#fee2e2' : '#dcfce7' }}; padding:6px; border-radius:6px; font-size:11px;"></i>
            </div>
            <div style="font-size:24px; font-weight:700; color:{{ $refundRate > 5 ? '#ef4444' : '#1c1a4a' }};">{{ $refundRate }}%</div>
            <div style="font-size:11px; color:#6b7280; margin-top:4px;">{{ $refundedCount }} {{ __('refunded') }}</div>
        </div>

        <div class="mbs-kpi" style="background:#fff; padding:18px 20px; border-radius:12px; border:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <span style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px;">{{ __('Wallet') }}</span>
                <i class="fas fa-wallet" style="color:var(--corp-brand); background:#ede9fe; padding:6px; border-radius:6px; font-size:11px;"></i>
            </div>
            <div style="font-size:24px; font-weight:700; color:#1c1a4a;">{{ currency($walletBalance) }}</div>
            <div style="font-size:11px; color:#6b7280; margin-top:4px;">
                @if ($pendingPayout > 0)
                    <span style="color:#f59e0b;">{{ currency($pendingPayout) }} {{ __('pending') }}</span>
                @else
                    {{ __('available to withdraw') }}
                @endif
            </div>
        </div>
    </div>

    {{-- Charts row --}}
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-bottom:24px;">
        <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                <h4 style="margin:0; font-size:15px; color:#1c1a4a;">{{ __('Revenue trend') }}</h4>
                <span style="font-size:11px; color:#9ca3af;">{{ $curCode }}</span>
            </div>
            <div style="height:280px; position:relative;">
                <canvas id="mbsRevenueChart"></canvas>
            </div>
        </div>
        <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
            <h4 style="margin:0 0 14px; font-size:15px; color:#1c1a4a;">{{ __('Rating distribution') }}</h4>
            @foreach ($ratingChart as $stars => $count)
                @php $max = max(array_values($ratingChart)) ?: 1; $width = round(($count / $max) * 100); @endphp
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <span style="font-size:12px; color:#6b7280; width:30px;">{{ $stars }} ★</span>
                    <div style="flex:1; height:8px; background:#f3f4f6; border-radius:4px; overflow:hidden;">
                        <div style="height:100%; width:{{ $width }}%; background:linear-gradient(90deg, #f59e0b, #fbbf24);"></div>
                    </div>
                    <span style="font-size:12px; color:#1c1a4a; font-weight:500; width:30px; text-align:right;">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Top courses + Recent sales --}}
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
        <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
            <h4 style="margin:0 0 14px; font-size:15px; color:#1c1a4a;">{{ __('Top courses') }}</h4>
            @forelse ($topCourses as $i => $row)
                @if ($row->course)
                    <a href="{{ route('instructor.courses.edit-view', $row->course->id) }}"
                       style="display:flex; align-items:center; gap:12px; padding:10px; border-radius:8px; text-decoration:none; color:inherit; transition:background 0.1s;"
                       onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                        <span style="flex-shrink:0; width:24px; height:24px; background:#ede9fe; color:var(--corp-brand); border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700;">{{ $i + 1 }}</span>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; font-weight:600; color:#1c1a4a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $row->course->title }}</div>
                            <div style="font-size:11px; color:#6b7280;">{{ $row->sales }} {{ __('sales') }} · {{ currency($row->revenue) }}</div>
                        </div>
                    </a>
                @endif
            @empty
                <p style="color:#9ca3af; text-align:center; padding:30px 0; font-size:13px;">{{ __('No sales yet in this period.') }}</p>
            @endforelse
        </div>

        <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
            <h4 style="margin:0 0 14px; font-size:15px; color:#1c1a4a;">{{ __('Recent sales') }}</h4>
            @forelse ($recentSales as $sale)
                <div style="display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid #f3f4f6;">
                    <div style="flex-shrink:0; width:32px; height:32px; background:#dcfce7; color:#166534; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                        <i class="fas fa-check" style="font-size:11px;"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:12.5px; color:#1c1a4a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <strong>{{ $sale->order?->user?->name ?? 'Unknown' }}</strong> · {{ $sale->course?->title }}
                        </div>
                        <div style="font-size:11px; color:#9ca3af;">{{ $sale->created_at?->diffForHumans() }}</div>
                    </div>
                    <span style="font-size:13px; font-weight:600; color:#10b981;">{{ currency($sale->price) }}</span>
                </div>
            @empty
                <p style="color:#9ca3af; text-align:center; padding:30px 0; font-size:13px;">{{ __('No sales yet.') }}</p>
            @endforelse
        </div>
    </div>

    {{-- ============ ADVANCED SECTION ============ --}}

    {{-- Course performance table + Funnel + Payment donut --}}
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-top:24px; margin-bottom:24px;">
        <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
            <h4 style="margin:0 0 14px; font-size:15px; color:#1c1a4a;">{{ __('Course performance') }}</h4>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="border-bottom:2px solid #f3f4f6; color:#6b7280; font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">
                            <th style="padding:8px; text-align:left;">{{ __('Course') }}</th>
                            <th style="padding:8px; text-align:right;">{{ __('Sales') }}</th>
                            <th style="padding:8px; text-align:right;">{{ __('Revenue') }}</th>
                            <th style="padding:8px; text-align:right;">{{ __('Students') }}</th>
                            <th style="padding:8px; text-align:right;">{{ __('Rating') }}</th>
                            <th style="padding:8px; text-align:right;">{{ __('Lessons') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coursePerformance as $row)
                            @if ($row->course)
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:10px 8px; color:#1c1a4a; font-weight:500;">
                                        <a href="{{ route('instructor.courses.edit-view', $row->course->id) }}" style="color:inherit; text-decoration:none;">
                                            {{ $row->course->title }}
                                        </a>
                                    </td>
                                    <td style="padding:10px 8px; text-align:right;">{{ $row->sales }}</td>
                                    <td style="padding:10px 8px; text-align:right; font-weight:600; color:#10b981;">{{ currency($row->revenue) }}</td>
                                    <td style="padding:10px 8px; text-align:right;">{{ $row->students }}</td>
                                    <td style="padding:10px 8px; text-align:right;">
                                        @if ($row->avg_rating > 0)
                                            <span style="color:#f59e0b;">★</span> {{ $row->avg_rating }}
                                            <small style="color:#9ca3af;">({{ $row->reviews_count }})</small>
                                        @else
                                            <span style="color:#9ca3af;">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:10px 8px; text-align:right; color:#6b7280;">{{ $row->total_lessons }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="6" style="padding:30px; text-align:center; color:#9ca3af;">{{ __('No paid sales yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
            <h4 style="margin:0 0 14px; font-size:15px; color:#1c1a4a;">{{ __('Engagement funnel') }}</h4>
            @foreach ($funnel as $stage)
                @php $width = $funnelMax > 0 ? round(($stage['count'] / $funnelMax) * 100) : 0; @endphp
                <div style="margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                        <span style="color:#1c1a4a; font-weight:500;">{{ __($stage['label']) }}</span>
                        <span style="color:#6b7280;">{{ number_format($stage['count']) }}</span>
                    </div>
                    <div style="height:10px; background:#f3f4f6; border-radius:5px; overflow:hidden;">
                        <div style="height:100%; width:{{ $width }}%; background:{{ $stage['color'] }}; transition: width 0.4s;"></div>
                    </div>
                </div>
            @endforeach
            <p style="font-size:11px; color:#9ca3af; margin:14px 0 0; padding-top:12px; border-top:1px solid #f3f4f6;">
                {{ __('Lifetime totals across all your courses. "Completed" = ≥80% of lectures watched.') }}
            </p>
        </div>
    </div>

    {{-- Sales heatmap (day × hour) + Payment-method donut --}}
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-bottom:24px;">
        <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                <h4 style="margin:0; font-size:15px; color:#1c1a4a;">{{ __('Sales heatmap') }}</h4>
                <span style="font-size:11px; color:#9ca3af;">{{ __('Last 90 days · darker = more sales') }}</span>
            </div>
            <div style="overflow-x:auto;">
                <table style="border-collapse:separate; border-spacing:2px; font-size:11px; color:#6b7280;">
                    <thead>
                        <tr>
                            <th style="width:30px;"></th>
                            @for ($h = 0; $h < 24; $h++)
                                <th style="text-align:center; font-weight:400; padding:2px;">{{ $h % 3 === 0 ? str_pad($h, 2, '0', STR_PAD_LEFT) : '' }}</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @php $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']; @endphp
                        @foreach ($days as $dow => $label)
                            <tr>
                                <td style="font-weight:500; color:#6b7280; padding-right:6px;">{{ $label }}</td>
                                @for ($hr = 0; $hr < 24; $hr++)
                                    @php
                                        $count = $heatmap[$dow][$hr] ?? 0;
                                        $intensity = $heatmapMax > 0 ? min(1, $count / $heatmapMax) : 0;
                                        // Lerp from #f3f4f6 (light gray) → var(--corp-brand) (purple) by intensity
                                        $bg = $count === 0
                                            ? '#f3f4f6'
                                            : 'rgba(16, 185, 129, ' . max(0.15, $intensity) . ')';
                                    @endphp
                                    <td title="{{ $label }} {{ $hr }}:00 — {{ $count }} {{ __('sale(s)') }}"
                                        style="width:18px; height:18px; background:{{ $bg }}; border-radius:3px;"></td>
                                @endfor
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($heatmapMax === 0)
                <p style="color:#9ca3af; text-align:center; padding:20px 0; font-size:13px;">{{ __('Not enough sales yet to build a heatmap.') }}</p>
            @endif
        </div>

        <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
            <h4 style="margin:0 0 14px; font-size:15px; color:#1c1a4a;">{{ __('Revenue by payment method') }}</h4>
            @if (count($paymentValues) > 0)
                <div style="height:200px; position:relative;">
                    <canvas id="mbsPaymentDonut"></canvas>
                </div>
                <div style="margin-top:14px;">
                    @foreach ($paymentLabels as $i => $label)
                        @php $colors = ['var(--corp-brand)', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#059669', '#06b6d4', '#ec4899']; @endphp
                        <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 0; font-size:12px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="width:10px; height:10px; border-radius:2px; background:{{ $colors[$i % count($colors)] }};"></span>
                                <span style="color:#1c1a4a; text-transform:capitalize;">{{ $label }}</span>
                            </div>
                            <span style="color:#6b7280; font-weight:500;">{{ currency($paymentValues[$i]) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p style="color:#9ca3af; text-align:center; padding:30px 0; font-size:13px;">{{ __('No paid orders in this period.') }}</p>
            @endif
        </div>
    </div>

    {{-- Top customers --}}
    <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h4 style="margin:0; font-size:15px; color:#1c1a4a;">{{ __('Top customers') }}</h4>
            <span style="font-size:11px; color:#9ca3af;">{{ __('By lifetime spend') }}</span>
        </div>
        @if ($topCustomers->isEmpty())
            <p style="color:#9ca3af; text-align:center; padding:30px 0; font-size:13px;">{{ __('No customers yet.') }}</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="border-bottom:2px solid #f3f4f6; color:#6b7280; font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">
                            <th style="padding:8px; text-align:left;">{{ __('Customer') }}</th>
                            <th style="padding:8px; text-align:right;">{{ __('Orders') }}</th>
                            <th style="padding:8px; text-align:right;">{{ __('Lifetime Spend') }}</th>
                            <th style="padding:8px; text-align:right;">{{ __('Last Order') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topCustomers as $i => $c)
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:10px 8px;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span style="flex-shrink:0; width:24px; height:24px; background:#ede9fe; color:var(--corp-brand); border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700;">{{ $i + 1 }}</span>
                                        <div>
                                            <div style="color:#1c1a4a; font-weight:500;">{{ $c->user?->name ?? '—' }}</div>
                                            <small style="color:#9ca3af;">{{ $c->user?->email }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:10px 8px; text-align:right;">{{ $c->orders }}</td>
                                <td style="padding:10px 8px; text-align:right; font-weight:600; color:#10b981;">{{ currency($c->lifetime) }}</td>
                                <td style="padding:10px 8px; text-align:right; color:#6b7280; font-size:12px;">{{ \Carbon\Carbon::parse($c->last_order)->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<style>
    @media (max-width: 900px) {
        .mbs-analytics > div[style*="grid-template-columns: 2fr 1fr"],
        .mbs-analytics > div[style*="grid-template-columns: 1fr 1fr"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
/* This page is authored almost entirely with inline style="" attributes, so the dark
   overrides target those inline surfaces/text by attribute substring (scoped to
   .mbs-analytics, !important to win over the inline value). Brand greens, reds, ambers,
   blues and gradient/rgba fills use different substrings and are left untouched. */
html[data-theme="dark"] .mbs-analytics [style*="background:#fff"] { background:#1e293b !important; }
html[data-theme="dark"] .mbs-analytics [style*="background:#f3f4f6"] { background:#22304a !important; }
html[data-theme="dark"] .mbs-analytics [style*="solid #e5e7eb"] { border-color:#2a3a55 !important; }
html[data-theme="dark"] .mbs-analytics [style*="solid #e2e8f0"] { border-color:#2a3a55 !important; }
html[data-theme="dark"] .mbs-analytics [style*="solid #f3f4f6"] { border-color:#2a3a55 !important; }
html[data-theme="dark"] .mbs-analytics [style*="color:#1c1a4a"] { color:#e2e8f0 !important; }
html[data-theme="dark"] .mbs-analytics [style*="color:#334155"] { color:#e2e8f0 !important; }
html[data-theme="dark"] .mbs-analytics [style*="color:#6b7280"] { color:#94a3b8 !important; }
html[data-theme="dark"] .mbs-analytics [style*="color:#9ca3af"] { color:#94a3b8 !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const ctx = document.getElementById('mbsRevenueChart');
    if (!ctx) return;
    const labels = @json($chartLabels);
    const values = @json($chartValues);
    const CUR = @json($curIcon); // platform currency symbol (₹ / $ / …) — dynamic

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: values,
                borderColor: 'var(--corp-brand)',
                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: 'var(--corp-brand)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1c1a4a',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: ctx => CUR + Number(ctx.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 2 })
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#9ca3af', font: { size: 11 } } },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f3f4f6' },
                    ticks: {
                        color: '#9ca3af',
                        font: { size: 11 },
                        callback: v => CUR + (v >= 1000 ? (v / 1000) + 'k' : v)
                    }
                }
            }
        }
    });

    // Payment method donut
    const donutCtx = document.getElementById('mbsPaymentDonut');
    if (donutCtx) {
        const palette = ['var(--corp-brand)', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#059669', '#06b6d4', '#ec4899'];
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: @json($paymentLabels),
                datasets: [{
                    data: @json($paymentValues),
                    backgroundColor: palette.slice(0, @json(count($paymentValues))),
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1c1a4a',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: ctx => ctx.label + ': ' + CUR + Number(ctx.parsed).toLocaleString(undefined, { minimumFractionDigits: 2 })
                        }
                    }
                }
            }
        });
    }
})();
</script>
@endsection
