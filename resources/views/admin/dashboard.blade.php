@extends('admin.master_layout')
@section('title')
    <title>{{ __('Dashboard') }} | {{ $setting?->app_name ?? config('app.name') }}</title>
@endsection
@section('admin-content')
@php
    // Everything below is rendered from data the controller already builds.
    // No money is re-derived here: the commission figure comes from
    // FinancialReportingService via $data['commission_by_currency'], and each
    // coach row from FinancialReportingService::coachLifetime().
    $sym       = $data['primary_currency_symbol'] ?? '';
    $primaryCc = $data['primary_currency'] ?? null;
    $commRow   = collect($data['commission_by_currency'] ?? [])->firstWhere('currency', $primaryCc);
    $commission = (float) ($commRow['platform_commission'] ?? 0);
    $commOrders = (int) ($commRow['orders'] ?? 0);
    $failed    = $data['system']['failed_jobs'] ?? null;
    $money = fn ($v) => $sym . number_format((float) $v, 2);
@endphp

<div class="main-content adm-dash">
<div id="adDash">
    <style>
        #adDash{ --ad-ink:#101828; --ad-sub:#525c6a; --ad-mut:#94a1b2; --ad-line:#e3e8ee; --ad-line2:#eef2f6;
            --ad-panel:#fff; --ad-soft:#f7f9fb; --ad-acc:#0e7c62; --ad-acc-soft:#e9f6f2;
            --ad-dgr:#b42318; --ad-dgr-soft:#fef3f2; --ad-wrn:#b54708; --ad-wrn-soft:#fffaeb;
            --ad-sh:0 1px 2px rgba(16,24,40,.05); color:var(--ad-ink); }
        #adDash *{ box-sizing:border-box; }
        #adDash .n{ font-variant-numeric:tabular-nums; }
        #adDash h1{ font-size:21px; font-weight:700; letter-spacing:-.016em; margin:0; }
        #adDash .ad-meta{ display:flex; align-items:center; gap:9px; flex-wrap:wrap; margin-top:5px; font-size:12.5px; color:var(--ad-mut); }
        #adDash .ad-meta .dot{ width:3px; height:3px; border-radius:50%; background:var(--ad-mut); opacity:.7; }
        #adDash .ok{ display:inline-flex; align-items:center; gap:6px; font-weight:600; color:var(--ad-acc); }
        #adDash .ok i{ width:7px; height:7px; border-radius:50%; background:var(--ad-acc); display:inline-block; }
        #adDash .ok.bad{ color:var(--ad-dgr); } #adDash .ok.bad i{ background:var(--ad-dgr); }

        #adDash .ad-aq{ display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-top:16px; }
        #adDash .aqi{ display:block; background:var(--ad-panel); border:1px solid var(--ad-line); border-left:3px solid var(--ad-mut);
            border-radius:10px; padding:13px 14px; box-shadow:var(--ad-sh); text-decoration:none; color:inherit; }
        #adDash .aqi:hover{ border-color:var(--ad-acc); }
        /* The controller emits severity high|medium|low (not danger/warning/info),
           so those were the class names to style — the original selectors never
           matched and every card rendered with the same neutral border. */
        #adDash .aqi.sev-high{ border-left-color:var(--ad-dgr); } #adDash .aqi.sev-high .cnt{ color:var(--ad-dgr); }
        #adDash .aqi.sev-medium{ border-left-color:var(--ad-wrn); } #adDash .aqi.sev-medium .cnt{ color:var(--ad-wrn); }
        #adDash .aqi.sev-low,
        #adDash .aqi.sev-info{ border-left-color:var(--ad-acc); }
        #adDash .aqi.sev-low .cnt,
        #adDash .aqi.sev-info .cnt{ color:var(--ad-acc); }
        #adDash .aqi .cnt{ font-size:20px; font-weight:800; line-height:1; }
        #adDash .aqi .lb{ font-size:12px; font-weight:600; margin-top:5px; }
        #adDash .aqi .rv{ font-size:11px; color:var(--ad-acc); font-weight:600; margin-top:5px; }

        #adDash .ad-kpis{ display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-top:16px; }
        #adDash .kc{ background:var(--ad-panel); border:1px solid var(--ad-line); border-radius:11px; padding:14px 15px; box-shadow:var(--ad-sh); }
        #adDash .kc .l{ font-size:11px; color:var(--ad-mut); display:flex; align-items:center; gap:6px; }
        #adDash .kc .l i{ font-size:12px; color:var(--ad-acc); opacity:.75; }
        #adDash .kc .v{ font-size:20px; font-weight:700; margin-top:7px; letter-spacing:-.015em; }
        #adDash .kc .s{ font-size:10.5px; color:var(--ad-mut); margin-top:3px; }
        #adDash .kc .up{ color:var(--ad-acc); font-weight:600; }
        #adDash .kc .warn{ color:var(--ad-wrn); }

        #adDash .ad-g2{ display:grid; grid-template-columns:minmax(0,1.75fr) minmax(0,1fr); gap:16px; margin-top:20px; align-items:start; }
        #adDash .col{ display:grid; gap:16px; }
        #adDash .card2{ background:var(--ad-panel); border:1px solid var(--ad-line); border-radius:12px; box-shadow:var(--ad-sh); overflow:hidden; }
        #adDash .ch2{ display:flex; align-items:center; gap:10px; padding:14px 16px; border-bottom:1px solid var(--ad-line2); }
        #adDash .ch2 h2{ font-size:14px; font-weight:600; margin:0; }
        #adDash .ch2 .note{ font-size:11.5px; color:var(--ad-mut); }
        #adDash .ch2 .tools{ margin-left:auto; display:flex; gap:7px; }
        #adDash .ch2 select{ height:30px; border:1px solid var(--ad-line); border-radius:7px; background:#fff; font-size:12px; padding:0 8px; }
        #adDash .ch2 a.more{ margin-left:auto; font-size:11.5px; font-weight:600; color:var(--ad-acc); text-decoration:none; }
        #adDash table.t2{ width:100%; border-collapse:collapse; font-size:12.5px; }
        #adDash table.t2 th{ text-align:left; font-size:10.5px; font-weight:600; color:var(--ad-mut); padding:9px 16px; border-bottom:1px solid var(--ad-line2); }
        #adDash table.t2 td{ padding:10px 16px; border-bottom:1px solid var(--ad-line2); }
        #adDash table.t2 tr:last-child td{ border-bottom:none; }
        #adDash table.t2 .r{ text-align:right; }
        #adDash .who{ display:flex; align-items:center; gap:9px; }
        #adDash .ini{ width:26px; height:26px; border-radius:50%; background:var(--ad-acc-soft); color:var(--ad-acc);
            font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex:0 0 auto; }
        #adDash .r3{ display:flex; align-items:center; padding:11px 16px; border-bottom:1px solid var(--ad-line2); font-size:12.5px; }
        #adDash .r3:last-child{ border-bottom:none; }
        #adDash .r3 .lb{ color:var(--ad-sub); } #adDash .r3 .vl{ margin-left:auto; font-weight:700; font-size:14px; }
        #adDash .r3 .vl.warn{ color:var(--ad-wrn); } #adDash .r3 .vl.acc{ color:var(--ad-acc); }
        #adDash .empty{ padding:26px 16px; text-align:center; color:var(--ad-mut); font-size:12.5px; }
        @media (max-width:1400px){ #adDash .ad-kpis{ grid-template-columns:repeat(3,1fr); } }
        @media (max-width:1200px){ #adDash .ad-g2{ grid-template-columns:1fr; } #adDash .ad-aq{ grid-template-columns:repeat(2,1fr); } }
    </style>

    <h1>{{ __('Platform overview') }}</h1>
    <div class="ad-meta">
        <span id="adTodayDate"><span>—</span></span>
        <span class="dot"></span>
        <span>{{ number_format($data['coaches']['total']) }} {{ __('coaches') }}</span>
        <span class="dot"></span>
        @if ($failed === null || $failed === 0)
            <span class="ok"><i></i>{{ __('All systems normal') }}</span>
        @else
            <span class="ok bad"><i></i>{{ trans_choice('{1}:count failed job|[2,*]:count failed jobs', $failed, ['count' => $failed]) }}</span>
        @endif
    </div>

    {{-- Action queue — what an operator must actually do today. --}}
    <div class="ad-aq">
        @forelse ($data['operator']['action_items'] ?? [] as $item)
            @php
                $sev = $item['severity'] ?? 'info';
                // 'route' is a route NAME ('admin.orders'), not a URL. Emitting it
                // straight into href resolved it relative to /admin/ and 404'd.
                // Route::has + try/catch also covers a parameterised route, which
                // would otherwise throw UrlGenerationException and 500 the page.
                $href = null;
                if (! empty($item['route']) && Route::has($item['route'])) {
                    try {
                        $href = route($item['route']);
                    } catch (\Throwable $e) {
                        $href = null;
                    }
                }
            @endphp
            <a class="aqi sev-{{ $sev }}" @if ($href) href="{{ $href }}" @endif>
                <div class="cnt n">{{ number_format($item['n'] ?? $item['count'] ?? 0) }}</div>
                <div class="lb">{{ $item['label'] ?? '' }}</div>
                @if ($href)
                    <div class="rv">{{ $item['cta'] ?? __('Review') }} &rarr;</div>
                @endif
            </a>
        @empty
            <div class="aqi sev-info"><div class="cnt n">0</div><div class="lb">{{ __('Nothing needs your attention') }}</div></div>
        @endforelse
    </div>

    {{-- Six consolidated KPIs, rendered from one definition so the tile markup
         is never hand-rolled per card (replaces the old 8 stat-card includes). --}}
    @php
        $kpis = [
            ['icon' => 'fa-user',        'label' => __('Coaches'),              'value' => number_format($data['coaches']['total']),
             'hi' => '+' . number_format($data['coaches']['new_30d']), 'sub' => __('in 30 days')],
            ['icon' => 'fa-users',       'label' => __('Students'),             'value' => number_format($data['coaches']['total_students']),
             'sub' => __('across all coaches')],
            ['icon' => 'fa-coins',       'label' => __('Platform commission'),  'value' => $money($commission),
             'sub' => $commOrders . ' ' . __('settled orders') . ' · ' . $primaryCc],
            ['icon' => 'fa-id-card',     'label' => __('Active subscriptions'), 'value' => number_format($data['membership']['active_total']),
             'sub' => $data['membership']['active_paid'] . ' ' . __('paid') . ' · ' . $data['membership']['trials_active'] . ' ' . __('trial')],
            ['icon' => 'fa-money-check', 'label' => __('Pending payouts'),      'value' => number_format($data['pending_withdraws'] ?? 0),
             'sub' => __('awaiting review'), 'tone' => ($data['pending_withdraws'] ?? 0) > 0 ? 'warn' : ''],
            ['icon' => 'fa-globe',       'label' => __('Custom domains'),       'value' => number_format($data['coaches']['domains_total']),
             'sub' => number_format($data['coaches']['domains_active']) . ' ' . __('mapped & live')],
        ];
    @endphp
    <div class="ad-kpis">
        @foreach ($kpis as $k)
            <div class="kc">
                <div class="l">@isset($k['icon'])<i class="fas {{ $k['icon'] }}" aria-hidden="true"></i>@endisset{{ $k['label'] }}</div>
                <div class="v n {{ $k['tone'] ?? '' }}">{{ $k['value'] }}</div>
                <div class="s">@isset($k['hi'])<span class="up">{{ $k['hi'] }}</span> @endisset{{ $k['sub'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="ad-g2">
        <div class="col">
            {{-- Revenue chart — same data + year/month filter as before. --}}
            <div class="card2">
                @php
                    // Default view = the approved design's 12-month bar chart.
                    // An explicit ?year=&month= still drills into that month's
                    // daily series, so the existing filter keeps working.
                    $drill = request()->has('year') && request()->has('month');
                @endphp
                <div class="ch2">
                    <h2>{{ __('Revenue') }}</h2>
                    <span class="note">{{ $drill
                        ? Carbon\Carbon::createFromFormat('Y-m', request('year') . '-' . request('month'))->format('F, Y')
                        : __('last 12 months') . ' · ' . $primaryCc }}</span>
                    {{-- One control, as in the approved design. The month drill-down is
                         kept (it is a real feature and an existing test covers
                         ?year=&month=) — it is now reachable from this same select
                         instead of two separate year/month dropdowns. --}}
                    <div class="tools">
                        @php
                            $selected = $drill
                                ? request('year') . '-' . str_pad(request('month'), 2, '0', STR_PAD_LEFT)
                                : '';
                            $cursor = Carbon\Carbon::now()->startOfMonth();
                        @endphp
                        <select id="revRange" aria-label="{{ __('Revenue period') }}"
                            onchange="var v=this.value, u='{{ route('admin.dashboard') }}';
                                      location.href = v ? u + '?year=' + v.slice(0,4) + '&month=' + v.slice(5) : u;">
                            <option value="" @selected(! $drill)>{{ __('Last 12 months') }}</option>
                            @for ($i = 0; $i < 12; $i++)
                                @php $m = $cursor->copy()->subMonths($i); @endphp
                                <option value="{{ $m->format('Y-m') }}" @selected($selected === $m->format('Y-m'))>
                                    {{ $m->format('M Y') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                </div>
                <div style="padding:16px;">
                    <div class="chart-area" style="position:relative; height:300px;">
                        <canvas id="myAreaChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Top coaches — ranking by paid orders; money from coachLifetime(). --}}
            <div class="card2">
                <div class="ch2"><h2>{{ __('Top coaches') }}</h2>
                    <a class="more" href="{{ route('admin.all-instructors') }}">{{ __('View all') }}</a></div>
                @if (!empty($data['top_coaches']))
                    <table class="t2">
                        <thead><tr>
                            <th>{{ __('Coach') }}</th><th class="r">{{ __('Students') }}</th>
                            <th class="r">{{ __('Orders') }}</th><th class="r">{{ __('Commission') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($data['top_coaches'] as $c)
                            <tr>
                                <td><span class="who"><span class="ini">{{ strtoupper(mb_substr($c['name'], 0, 2)) }}</span>{{ $c['name'] }}</span></td>
                                <td class="r n">{{ number_format($c['students']) }}</td>
                                <td class="r n">{{ number_format($c['orders']) }}</td>
                                <td class="r n">{{ $money($c['commission']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="empty">{{ __('No paid orders yet.') }}</div>
                @endif
            </div>
        </div>

        <div class="col">
            <div class="card2">
                <div class="ch2"><h2>{{ __('Membership health') }}</h2></div>
                <div class="r3"><span class="lb">{{ __('Active members') }}</span><span class="vl n">{{ number_format($data['membership']['active_total']) }}</span></div>
                <div class="r3"><span class="lb">{{ __('Paid') }}</span><span class="vl n">{{ number_format($data['membership']['active_paid']) }}</span></div>
                <div class="r3"><span class="lb">{{ __('On trial') }}</span><span class="vl n">{{ number_format($data['membership']['trials_active']) }}</span></div>
                <div class="r3"><span class="lb">{{ __('Trials ending in 3 days') }}</span><span class="vl n {{ $data['membership']['trials_ending_3d'] > 0 ? 'warn' : '' }}">{{ number_format($data['membership']['trials_ending_3d']) }}</span></div>
                {{-- 'Payment pending' lives in the action queue now (it is an item
                     you act on, not a stat) — showing it twice was a duplicate. --}}
            </div>

            <div class="card2">
                <div class="ch2"><h2>{{ __('Referrals') }}</h2>
                    <a class="more" href="{{ route('admin.referrals.index') }}">{{ __('All') }}</a></div>
                <div class="r3"><span class="lb">{{ __('Total referrals') }}</span><span class="vl n">{{ number_format($data['referral']['total']) }}</span></div>
                <div class="r3"><span class="lb">{{ __('Pending review') }}</span><span class="vl n {{ $data['referral']['pending'] > 0 ? 'warn' : '' }}">{{ number_format($data['referral']['pending']) }}</span></div>
                <div class="r3"><span class="lb">{{ __('Wallet outstanding') }}</span><span class="vl n acc">{{ $money($data['referral']['wallet_outstanding']) }}</span></div>
            </div>

            <div class="card2">
                <div class="ch2"><h2>{{ __('System status') }}</h2></div>
                {{-- Label matches the approved design; the VALUE stays the configured
                     driver. The mockup's "● Running" would assert worker health we
                     cannot observe — a configured driver is not a running worker. --}}
                <div class="r3"><span class="lb">{{ __('Queue') }}</span><span class="vl" style="font-size:12.5px;">{{ $data['system']['queue'] }}</span></div>
                @if (!is_null($data['system']['failed_jobs']))
                    <div class="r3"><span class="lb">{{ __('Failed jobs') }}</span><span class="vl n {{ $data['system']['failed_jobs'] > 0 ? 'warn' : '' }}">{{ number_format($data['system']['failed_jobs']) }}</span></div>
                @endif
                @if (!is_null($data['system']['pending_jobs']))
                    <div class="r3"><span class="lb">{{ __('Queued jobs') }}</span><span class="vl n">{{ number_format($data['system']['pending_jobs']) }}</span></div>
                @endif
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('js')
    <script src="{{ asset('backend/js/chart.umd.min.js') }}"></script>

    <script>
        (function () {
            // Date pill in the header
            var dEl = document.querySelector('#adTodayDate span');
            if (dEl) {
                try {
                    var d = new Date();
                    dEl.textContent = d.toLocaleDateString(undefined, {
                        weekday: 'long', month: 'short', day: 'numeric', year: 'numeric'
                    });
                } catch (e) { /* keep — */ }
            }
        })();
    </script>

    <script>
        (function ($) {
            "use strict";
            $(document).ready(function () {

                var ctx = document.getElementById("myAreaChart");
                if (!ctx) return;

                // Two views off the SAME revenue basis: 12 monthly bars (the
                // approved default) and, when ?year=&month= is present, that
                // month's daily line.
                var drill  = @json($drill);
                var ACCENT = '#0e7c62';   // shared emerald, same as the sidebar
                // Currency symbol for the axis + tooltip. Taken from the same
                // primary-currency resolution the figures above use, so the chart
                // cannot label ₹ data with a different symbol.
                var CUR    = @json($data['primary_currency_symbol'] ?? session()->get('currency_icon') ?? '');

                var jData, labels, chartType, dataset;

                if (drill) {
                    jData  = JSON.parse(@json($data['monthly_data']));
                    labels = ["1","2","3","4","5","6","7","8","9","10","11","12","13","14","15","16",
                              "17","18","19","20","21","22","23","24","25","26","27","28","29","30","31"];
                    chartType = 'line';

                    var canvasCtx = ctx.getContext('2d');
                    var gradient  = canvasCtx.createLinearGradient(0, 0, 0, 320);
                    gradient.addColorStop(0, 'rgba(14, 124, 98, 0.20)');
                    gradient.addColorStop(1, 'rgba(14, 124, 98, 0.00)');

                    dataset = {
                        label: "{{ __('Revenue') }}",
                        lineTension: 0.35,
                        backgroundColor: gradient,
                        borderColor: ACCENT,
                        pointRadius: 3,
                        pointBackgroundColor: ACCENT,
                        pointBorderColor: "#fff",
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: ACCENT,
                        pointHoverBorderColor: "#fff",
                        pointHitRadius: 12,
                        pointBorderWidth: 2,
                        borderWidth: 2,
                        data: jData,
                        fill: true,
                    };
                } else {
                    jData     = @json($data['revenue_12m']['values']);
                    labels    = @json($data['revenue_12m']['labels']);
                    chartType = 'bar';

                    dataset = {
                        label: "{{ __('Revenue') }}",
                        backgroundColor: ACCENT,
                        hoverBackgroundColor: '#0b6450',
                        borderWidth: 0,
                        barPercentage: 0.62,
                        categoryPercentage: 0.78,
                        data: jData,
                    };
                }

                // The approved design shows a soft track behind every bar so the
                // 12-month rhythm reads even in months with no revenue. Chart.js
                // has no built-in for this, so draw it under the bars.
                var trackPlugin = {
                    id: 'barTrack',
                    beforeDatasetsDraw: function (c) {
                        if (c.config.type !== 'bar') return;
                        var meta = c.getDatasetMeta(0), area = c.chartArea, g = c.ctx;
                        if (!meta || !area) return;
                        g.save();
                        g.fillStyle = '#e9f6f2';            // --adm-accent-bg
                        meta.data.forEach(function (bar) {
                            var w = bar.width || 0, r = 4;
                            var x = bar.x - w / 2, y = area.top, h = area.bottom - area.top;
                            g.beginPath();
                            g.moveTo(x, y + h);
                            g.lineTo(x, y + r);
                            g.quadraticCurveTo(x, y, x + r, y);
                            g.lineTo(x + w - r, y);
                            g.quadraticCurveTo(x + w, y, x + w, y + r);
                            g.lineTo(x + w, y + h);
                            g.closePath();
                            g.fill();
                        });
                        g.restore();
                    }
                };

                new Chart(ctx, {
                    type: chartType,
                    plugins: [trackPlugin],
                    data: {
                        labels: labels,
                        datasets: [dataset],
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        layout: { padding: { left: 8, right: 16, top: 16, bottom: 0 } },
                        // Chart.js 4 syntax. This block was written for Chart.js 2
                        // (scales.xAxes[], root-level legend, `tooltips`) while the
                        // page loads 4.4.1 — so every option here was silently
                        // ignored: no currency on the axis, legend never hidden,
                        // grid + tooltip styling never applied.
                        scales: {
                            x: {
                                grid: { display: false, drawOnChartArea: false },
                                border: { display: false },
                                ticks: {
                                    maxTicksLimit: drill ? 7 : 12,
                                    color: '#9ca3af',
                                    font: { family: 'Inter', size: 11 },
                                },
                            },
                            y: {
                                beginAtZero: true,
                                // A drilled month with no revenue is all-zeros;
                                // without a floor Chart.js scales the axis to
                                // 0–1 and prints ₹0.5 / ₹1, which reads broken.
                                suggestedMax: (Array.isArray(jData) && Math.max.apply(null, jData.map(Number)) > 0)
                                    ? undefined : 1000,
                                grid: { color: 'rgba(15, 23, 42, 0.06)' },
                                border: { display: false, dash: [3, 3] },
                                ticks: {
                                    maxTicksLimit: 5,
                                    padding: 10,
                                    color: '#9ca3af',
                                    font: { family: 'Inter', size: 11 },
                                    callback: function (value) {
                                        return CUR + Number(value).toLocaleString();
                                    },
                                },
                            },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(11, 18, 32, 0.95)',
                                bodyColor: '#f1f5f9',
                                titleColor: '#fff',
                                titleFont: { family: 'Inter', size: 12 },
                                bodyFont: { family: 'Inter', size: 12 },
                                cornerRadius: 8,
                                padding: { x: 12, y: 10 },
                                displayColors: false,
                                intersect: false,
                                mode: 'index',
                                caretPadding: 8,
                                callbacks: {
                                    label: function (context) {
                                        var lbl = context.dataset.label || '';
                                        return lbl + ': ' + CUR + Number(context.parsed.y).toLocaleString();
                                    },
                                },
                            },
                        },
                    }
                });
            });
        })(jQuery);
    </script>

    {{-- Preserved legacy 24h-dismissible alert handlers — kept for any
         alerts still injected elsewhere in the layout. --}}
    <script>
        $(document).ready(function () {
            "use strict";
            ['missingCrentialsAlert', 'updateAvailablityAlert'].forEach(function (alertKey) {
                var $el = $('#' + alertKey);
                if (!$el.length) return;
                var dismissed = localStorage.getItem(alertKey);
                if (!dismissed || Date.now() - dismissed > 24 * 60 * 60 * 1000) {
                    $el.removeClass('d-none').show();
                } else {
                    $el.hide();
                }
                $('#' + alertKey + 'Close').on('click', function () {
                    $el.hide();
                    localStorage.setItem(alertKey, Date.now());
                });
            });
        });
    </script>
@endpush
