@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@php
    /* ---------- small formatting helpers (Indian numbering) ---------- */
    $inr = function ($n) {
        $n = (float) $n;
        if ($n >= 10000000) return '₹'.rtrim(rtrim(number_format($n / 10000000, 2), '0'), '.').'Cr';
        if ($n >= 100000)   return '₹'.rtrim(rtrim(number_format($n / 100000, 2), '0'), '.').'L';
        if ($n >= 1000)     return '₹'.rtrim(rtrim(number_format($n / 1000, 1), '0'), '.').'k';
        return '₹'.number_format($n);
    };
    $statusLabel = [
        'draft' => 'Draft', 'hr_submitted' => 'Submitted',
        'admin_approved' => 'Approved', 'paid' => 'Paid',
    ];

    /* ---------- KPI tiles ---------- */
    $k = $kpis;
    $tiles = [
        ['Team members',   $k['team'],            $k['active'].' active',                         'var(--pv-brand)', 'fa-users'],
        ['Present today',   $k['presentToday'],    $k['team'] ? round($k['presentToday'] / max($k['team'],1) * 100).'% of team' : '—', 'var(--pv-green)', 'fa-user-check'],
        ['On leave today',  $k['onLeaveToday'],    $k['absentToday'].' absent',                    'var(--pv-violet)', 'fa-plane-departure'],
        ['Unmarked today',  $k['unmarkedToday'],   $k['unmarkedToday'] ? 'needs marking' : 'all marked', 'var(--pv-amber)', 'fa-clipboard-list'],
        ['Attendance · MTD', $k['attendancePct'].'%', 'payable / working days',                    'var(--pv-sky)',  'fa-chart-line'],
        ['Payroll · '.\Illuminate\Support\Str::of($periodLabel)->explode(' ')->first(), $k['payrollNet'] !== null ? $inr($k['payrollNet']) : '—', $k['payrollStatus'] ? ($statusLabel[$k['payrollStatus']] ?? $k['payrollStatus']) : 'not started', 'var(--pv-green)', 'fa-rupee-sign'],
    ];

    /* ---------- attendance trend: SVG geometry ---------- */
    $W=780; $H=250; $mL=6; $mR=6; $mT=18; $mB=24;
    $pw=$W-$mL-$mR; $ph=$H-$mT-$mB;
    $n=count($trend); $xs=[]; $ys=[];
    foreach (array_values($trend) as $i=>$t){
        $xs[$i]=$mL + ($n<=1 ? $pw/2 : $i*$pw/($n-1));
        $ys[$i]=$mT + $ph - ($t['rate']/100)*$ph;
    }
    $linePath='';
    if ($n){
        $linePath='M '.round($xs[0],1).','.round($ys[0],1).' ';
        for ($i=0;$i<$n-1;$i++){
            $p0x=$xs[max(0,$i-1)]; $p0y=$ys[max(0,$i-1)];
            $p1x=$xs[$i];          $p1y=$ys[$i];
            $p2x=$xs[$i+1];        $p2y=$ys[$i+1];
            $p3x=$xs[min($n-1,$i+2)]; $p3y=$ys[min($n-1,$i+2)];
            $c1x=$p1x+($p2x-$p0x)/6; $c1y=$p1y+($p2y-$p0y)/6;
            $c2x=$p2x-($p3x-$p1x)/6; $c2y=$p2y-($p3y-$p1y)/6;
            $c1y=max($mT,min($mT+$ph,$c1y)); $c2y=max($mT,min($mT+$ph,$c2y));
            $linePath.=sprintf('C %.1f,%.1f %.1f,%.1f %.1f,%.1f ',$c1x,$c1y,$c2x,$c2y,$p2x,$p2y);
        }
    }
    $areaPath = $n ? $linePath.sprintf('L %.1f,%.1f L %.1f,%.1f Z',$xs[$n-1],$mT+$ph,$xs[0],$mT+$ph) : '';
    $avg = $n ? array_sum(array_column($trend,'rate'))/$n : 0;
    $avgY = $mT+$ph-($avg/100)*$ph;

    /* ---------- today donut ---------- */
    $donutTotal = max(array_sum(array_column($donut,'value')),1);
    $R=52; $CIRC=2*M_PI*$R; $acc=0;

    /* ---------- payroll bars ---------- */
    $maxNet = max(array_map(fn($b)=>$b['net'] ?? 0, $payrollBars)) ?: 1;

    /* ---------- composition strips ---------- */
    $compBar = function($rows){
        $tot = max(array_sum(array_column($rows,'value')),1);
        return collect($rows)->map(fn($r)=>$r+['pct'=>$r['value']/$tot*100])->all();
    };
    $statusRows = $compBar($composition['status']);
    $typeRows   = $compBar($composition['type']);
@endphp

<div class="pv hrd">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">{{ $greeting }}, {{ \Illuminate\Support\Str::of($hr->name)->explode(' ')->first() }}</h1>
            <p class="s">Team snapshot · {{ $periodLabel }} · {{ now()->format('l, d M') }}</p>
        </div>
        <div class="pv-actions">
            <a href="{{ route('hr.attendance.team') }}" class="pv-btn"><i class="fas fa-calendar-check"></i> Attendance</a>
            <a href="{{ route('hr.employees.index') }}" class="pv-btn p"><i class="fas fa-users"></i> Employees</a>
        </div>
    </div>

    {{-- ── KPI tiles ─────────────────────────────────────────────── --}}
    <div class="hrd-kpis">
        @foreach($tiles as [$label,$val,$sub,$color,$icon])
            <div class="hrd-kpi" style="--c:{{ $color }}">
                <div class="hrd-kpi-ic"><i class="fas {{ $icon }}"></i></div>
                <div class="hrd-kpi-body">
                    <div class="hrd-kpi-n" data-countup>{{ $val }}</div>
                    <div class="hrd-kpi-l">{{ $label }}</div>
                    <div class="hrd-kpi-sub">{{ $sub }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Row A · attendance trend + today donut ────────────────── --}}
    <div class="pv-cols c73">
        <div class="pv-card">
            <div class="h">
                <i class="fas fa-wave-square pv-muted"></i> Team attendance · last 14 days
                <span class="hrd-chip" style="margin-left:auto">avg {{ round($avg) }}%</span>
            </div>
            <div class="b">
                @if($k['team'] === 0)
                    <div class="pv-empty"><div class="ic"><i class="fas fa-user-slash"></i></div>No employees on your team yet.</div>
                @else
                <div class="hrd-linechart">
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Attendance trend, last 14 days">
                        <defs>
                            <linearGradient id="hrdArea" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="var(--pv-brand)" stop-opacity="0.30"/>
                                <stop offset="100%" stop-color="var(--pv-brand)" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        @foreach([0,25,50,75,100] as $g)
                            @php $gy=$mT+$ph-($g/100)*$ph; @endphp
                            <line x1="{{ $mL }}" y1="{{ $gy }}" x2="{{ $W-$mR }}" y2="{{ $gy }}" class="hrd-grid"/>
                            <text x="{{ $mL }}" y="{{ $gy-4 }}" class="hrd-gridlabel">{{ $g }}</text>
                        @endforeach

                        <line x1="{{ $mL }}" y1="{{ $avgY }}" x2="{{ $W-$mR }}" y2="{{ $avgY }}" class="hrd-avg"/>

                        <path d="{{ $areaPath }}" fill="url(#hrdArea)" class="hrd-area"/>
                        <path d="{{ $linePath }}" fill="none" stroke="var(--pv-brand)" stroke-width="2.5"
                              stroke-linecap="round" stroke-linejoin="round" pathLength="1" class="hrd-line"/>

                        @foreach(array_values($trend) as $i=>$t)
                            <circle cx="{{ round($xs[$i],1) }}" cy="{{ round($ys[$i],1) }}"
                                    r="{{ $t['today'] ? 4 : 2.4 }}"
                                    class="hrd-dot {{ $t['today'] ? 'is-today' : '' }}"/>
                            <circle cx="{{ round($xs[$i],1) }}" cy="{{ round($ys[$i],1) }}" r="14" fill="transparent" class="hrd-hit">
                                <title>{{ $t['label'] }} — {{ $t['rate'] }}% present ({{ $t['present'] }}/{{ $t['total'] }})</title>
                            </circle>
                            <text x="{{ round($xs[$i],1) }}" y="{{ $H-6 }}"
                                  class="hrd-xlabel {{ $t['weekend'] ? 'is-weekend' : '' }} {{ $t['today'] ? 'is-today' : '' }}">{{ $t['dom'] }}</text>
                        @endforeach
                    </svg>
                </div>
                @endif
            </div>
        </div>

        <div class="pv-card">
            <div class="h"><i class="fas fa-chart-pie pv-muted"></i> Today</div>
            <div class="b">
                <div class="hrd-donut-wrap">
                    <svg viewBox="0 0 140 140" class="hrd-donut" role="img" aria-label="Today's attendance split">
                        <circle cx="70" cy="70" r="{{ $R }}" fill="none" stroke="var(--pv-line2)" stroke-width="15"/>
                        @foreach($donut as $seg)
                            @php
                                $frac=$seg['value']/$donutTotal; $len=$frac*$CIRC; $off=$acc*$CIRC; $acc+=$frac;
                            @endphp
                            <circle cx="70" cy="70" r="{{ $R }}" fill="none" stroke="{{ $seg['color'] }}" stroke-width="15"
                                    stroke-dasharray="{{ round($len,2) }} {{ round($CIRC-$len,2) }}"
                                    stroke-dashoffset="{{ round(-$off,2) }}"
                                    transform="rotate(-90 70 70)" class="hrd-seg">
                                <title>{{ $seg['label'] }}: {{ $seg['value'] }}</title>
                            </circle>
                        @endforeach
                        <text x="70" y="66" class="hrd-donut-n">{{ $k['presentToday'] }}<tspan class="hrd-donut-slash">/{{ $k['team'] }}</tspan></text>
                        <text x="70" y="86" class="hrd-donut-c">present</text>
                    </svg>
                    <ul class="hrd-legend">
                        @forelse($donut as $seg)
                            <li><span class="dot" style="background:{{ $seg['color'] }}"></span>{{ $seg['label'] }}<b>{{ $seg['value'] }}</b></li>
                        @empty
                            <li class="pv-muted">Nothing marked yet today.</li>
                        @endforelse
                    </ul>
                </div>
                <a href="{{ route('hr.attendance.team') }}" class="pv-btn sm" style="margin-top:4px"><i class="fas fa-pen"></i> Mark team attendance</a>
            </div>
        </div>
    </div>

    {{-- ── Row B · team composition + payroll cost ───────────────── --}}
    <div class="pv-cols c2">
        <div class="pv-card">
            <div class="h"><i class="fas fa-sitemap pv-muted"></i> Team composition</div>
            <div class="b">
                <div class="hrd-sub">By department</div>
                @forelse($byDept as $d)
                    <div class="hrd-bar">
                        <div class="hrd-bar-l">{{ $d['name'] }}</div>
                        <div class="hrd-bar-track">
                            <div class="hrd-bar-fill" style="--w:{{ $d['pct'] }}%"></div>
                        </div>
                        <div class="hrd-bar-v">{{ $d['count'] }}</div>
                    </div>
                @empty
                    <p class="pv-muted" style="margin:0 0 6px">No active employees to break down yet.</p>
                @endforelse

                <div class="hrd-divide"></div>

                <div class="hrd-sub">By status</div>
                <div class="hrd-stack">
                    @foreach($statusRows as $r)
                        <span class="hrd-stack-seg" style="width:{{ $r['pct'] }}%;background:{{ $r['color'] }}" title="{{ $r['label'] }}: {{ $r['value'] }}"></span>
                    @endforeach
                </div>
                <ul class="hrd-legend row">
                    @foreach($statusRows as $r)<li><span class="dot" style="background:{{ $r['color'] }}"></span>{{ $r['label'] }}<b>{{ $r['value'] }}</b></li>@endforeach
                </ul>

                <div class="hrd-sub" style="margin-top:12px">By employment type</div>
                <div class="hrd-stack">
                    @foreach($typeRows as $r)
                        <span class="hrd-stack-seg" style="width:{{ $r['pct'] }}%;background:{{ $r['color'] }}" title="{{ $r['label'] }}: {{ $r['value'] }}"></span>
                    @endforeach
                </div>
                <ul class="hrd-legend row">
                    @foreach($typeRows as $r)<li><span class="dot" style="background:{{ $r['color'] }}"></span>{{ $r['label'] }}<b>{{ $r['value'] }}</b></li>@endforeach
                </ul>
            </div>
        </div>

        <div class="pv-card">
            <div class="h">
                <i class="fas fa-rupee-sign pv-muted"></i> Payroll cost · last 6 months
                @if($k['payrollNet'] !== null)<span class="hrd-chip" style="margin-left:auto">{{ $inr($k['payrollNet']) }} this month</span>@endif
            </div>
            <div class="b">
                @php $hasAny = collect($payrollBars)->contains(fn($b)=>$b['net'] !== null); @endphp
                @if(! $hasAny)
                    <div class="pv-empty"><div class="ic"><i class="fas fa-file-invoice-dollar"></i></div>No payroll runs in the last 6 months.</div>
                @else
                <div class="hrd-barchart">
                    @foreach($payrollBars as $b)
                        <div class="hrd-vbar {{ $b['current'] ? 'is-current' : '' }} {{ $b['net'] === null ? 'is-empty' : '' }}">
                            <div class="hrd-vbar-v">{{ $b['net'] !== null ? $inr($b['net']) : '—' }}</div>
                            <div class="hrd-vbar-col">
                                <div class="hrd-vbar-fill" style="--h:{{ $b['net'] !== null ? max(4, round($b['net'] / $maxNet * 100)) : 0 }}%"></div>
                            </div>
                            <div class="hrd-vbar-l">{{ $b['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                @endif
                <div class="hrd-actions-inline">
                    @if($k['payrollRun'])
                        <a href="{{ route('hr.payroll.show', $k['payrollRun']) }}" class="pv-btn sm p"><i class="fas fa-arrow-right"></i> Open {{ $periodLabel }} run</a>
                    @else
                        <a href="{{ route('hr.payroll.index') }}" class="pv-btn sm g"><i class="fas fa-plus"></i> Prepare {{ $periodLabel }} payroll</a>
                    @endif
                    <a href="{{ route('hr.salary.index') }}" class="pv-btn sm"><i class="fas fa-file-invoice-dollar"></i> Salary structures</a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row C · pending leave + new joiners ───────────────────── --}}
    <div class="pv-cols c2">
        <div class="pv-card">
            <div class="h">
                <i class="fas fa-clipboard-check pv-muted"></i> Pending leave requests
                @if($k['pendingLeaves'])<span class="pv-badge pending" style="margin-left:auto">{{ $k['pendingLeaves'] }}</span>@endif
            </div>
            <div class="b tight">
                @forelse($pendingList as $lv)
                    <a class="hrd-listrow" href="{{ route('hr.leave.index') }}">
                        <span class="hrd-avatar">{{ strtoupper(substr($lv->employee->name ?? '?', 0, 1)) }}</span>
                        <span class="hrd-listrow-main">
                            <span class="hrd-listrow-t">{{ $lv->employee->name ?? 'Employee' }}</span>
                            <span class="hrd-listrow-s">{{ $lv->type->name ?? 'Leave' }} · {{ $lv->start_date->format('d M') }}@if($lv->end_date && ! $lv->end_date->isSameDay($lv->start_date)) – {{ $lv->end_date->format('d M') }}@endif</span>
                        </span>
                        <span class="pv-badge pending">{{ rtrim(rtrim(number_format($lv->days, 1), '0'), '.') }}d</span>
                    </a>
                @empty
                    <div class="pv-empty"><div class="ic"><i class="fas fa-check-circle"></i></div>No pending requests. You're all caught up.</div>
                @endforelse
                @if($pendingList->isNotEmpty())
                    <a href="{{ route('hr.leave.index') }}" class="pv-btn sm" style="margin:8px">Review all <i class="fas fa-arrow-right"></i></a>
                @endif
            </div>
        </div>

        <div class="pv-card">
            <div class="h"><i class="fas fa-user-plus pv-muted"></i> New joiners · last 30 days</div>
            <div class="b tight">
                @forelse($newJoiners as $p)
                    <a class="hrd-listrow" href="{{ route('hr.employees.edit', $p->user_id) }}">
                        <span class="hrd-avatar">{{ strtoupper(substr($p->user->name ?? '?', 0, 1)) }}</span>
                        <span class="hrd-listrow-main">
                            <span class="hrd-listrow-t">{{ $p->user->name ?? 'Employee' }}</span>
                            <span class="hrd-listrow-s">{{ $p->department->name ?? 'Unassigned' }}@if($p->designation) · {{ $p->designation }}@endif</span>
                        </span>
                        <span class="hrd-listrow-when">{{ $p->date_of_joining->diffForHumans() }}</span>
                    </a>
                @empty
                    <div class="pv-empty"><div class="ic"><i class="fas fa-user-clock"></i></div>No one joined in the last 30 days.</div>
                @endforelse
                <a href="{{ route('hr.employees.create') }}" class="pv-btn sm g" style="margin:8px"><i class="fas fa-plus"></i> Add employee</a>
            </div>
        </div>
    </div>

    {{-- ── Row D · quick actions ─────────────────────────────────── --}}
    <div class="pv-card">
        <div class="h"><i class="fas fa-bolt pv-muted"></i> Quick actions</div>
        <div class="b">
            <div class="hrd-quick">
                <a class="pv-linkrow" href="{{ route('hr.attendance.sheet') }}"><i class="fas fa-table" style="color:var(--pv-sky)"></i> Monthly attendance sheet</a>
                <a class="pv-linkrow" href="{{ route('hr.leave.index') }}"><i class="fas fa-plane-departure" style="color:var(--pv-violet)"></i> Leave approvals</a>
                <a class="pv-linkrow" href="{{ route('hr.departments.index') }}"><i class="fas fa-sitemap" style="color:var(--pv-brand)"></i> Departments</a>
                <a class="pv-linkrow" href="{{ route('hr.payroll.index') }}"><i class="fas fa-money-check-alt" style="color:var(--pv-green)"></i> Payroll runs</a>
                <a class="pv-linkrow" href="{{ route('hr.companies.index') }}"><i class="fas fa-building" style="color:var(--pv-amber)"></i> Companies</a>
                <a class="pv-linkrow" href="{{ route('instructor.setting.index') }}"><i class="fas fa-cog" style="color:var(--pv-sub)"></i> Settings</a>
            </div>
        </div>
    </div>
</div>

<style>
    /* ================= HR dashboard ================= */
    .hrd .pv-head .t{font-size:23px;}
    .hrd-chip{font-size:11px;font-weight:700;color:var(--pv-sub);background:var(--pv-line2);
        border:1px solid var(--pv-line);padding:3px 9px;border-radius:999px;white-space:nowrap;}

    /* KPI tiles */
    .hrd-kpis{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(184px,1fr));margin-bottom:18px;}
    .hrd-kpi{position:relative;display:flex;gap:13px;align-items:flex-start;background:var(--pv-card);
        border:1px solid var(--pv-line);border-radius:var(--pv-r);padding:15px 16px;overflow:hidden;
        box-shadow:0 1px 2px rgba(16,24,40,.04);}
    .hrd-kpi::after{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--c);}
    .hrd-kpi-ic{flex:none;width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:15px;
        color:var(--c);background:color-mix(in srgb,var(--c) 12%,transparent);}
    .hrd-kpi-n{font-size:24px;font-weight:750;line-height:1.05;letter-spacing:-.01em;color:var(--pv-ink);}
    .hrd-kpi-l{font-size:12.5px;font-weight:600;color:var(--pv-sub);margin-top:5px;}
    .hrd-kpi-sub{font-size:11px;color:var(--pv-mut);margin-top:2px;}

    /* line chart */
    .hrd-linechart svg{width:100%;height:auto;display:block;overflow:visible;}
    .hrd-grid{stroke:var(--pv-line);stroke-width:1;}
    .hrd-gridlabel{fill:var(--pv-mut);font-size:9px;font-weight:600;font-family:inherit;}
    .hrd-avg{stroke:var(--pv-amber);stroke-width:1.2;stroke-dasharray:3 3;opacity:.7;}
    .hrd-line{filter:drop-shadow(0 4px 8px color-mix(in srgb,var(--pv-brand) 30%,transparent));}
    .hrd-dot{fill:var(--pv-card);stroke:var(--pv-brand);stroke-width:2;}
    .hrd-dot.is-today{fill:var(--pv-brand);stroke:var(--pv-card);}
    .hrd-hit{cursor:pointer;}
    .hrd-hit:hover + .hrd-xlabel{fill:var(--pv-ink);}
    .hrd-xlabel{fill:var(--pv-mut);font-size:9.5px;font-weight:600;text-anchor:middle;font-family:inherit;}
    .hrd-xlabel.is-weekend{fill:var(--pv-red);opacity:.6;}
    .hrd-xlabel.is-today{fill:var(--pv-brand);font-weight:800;}

    /* donut */
    .hrd-donut-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;}
    .hrd-donut{width:160px;height:160px;}
    .hrd-seg{stroke-linecap:butt;}
    .hrd-donut-n{fill:var(--pv-ink);font-size:24px;font-weight:750;text-anchor:middle;font-family:inherit;}
    .hrd-donut-slash{fill:var(--pv-mut);font-size:13px;font-weight:600;}
    .hrd-donut-c{fill:var(--pv-sub);font-size:10px;font-weight:600;text-anchor:middle;text-transform:uppercase;letter-spacing:.06em;font-family:inherit;}
    .hrd-legend{list-style:none;margin:0;padding:0;width:100%;display:flex;flex-direction:column;gap:7px;}
    .hrd-legend.row{flex-direction:row;flex-wrap:wrap;gap:5px 14px;margin-top:8px;}
    .hrd-legend li{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--pv-sub);}
    .hrd-legend li b{margin-left:auto;color:var(--pv-ink);font-weight:700;}
    .hrd-legend.row li b{margin-left:4px;}
    .hrd-legend .dot{width:9px;height:9px;border-radius:3px;flex:none;}

    /* dept bars */
    .hrd-sub{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--pv-mut);margin-bottom:10px;}
    .hrd-bar{display:flex;align-items:center;gap:12px;margin-bottom:10px;}
    .hrd-bar-l{width:34%;font-size:12.5px;font-weight:600;color:var(--pv-ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .hrd-bar-track{flex:1;height:9px;background:var(--pv-line2);border-radius:999px;overflow:hidden;}
    .hrd-bar-fill{height:100%;width:var(--w);border-radius:999px;background:linear-gradient(90deg,var(--pv-brand),var(--pv-brand-2));}
    .hrd-bar-v{width:22px;text-align:right;font-size:12.5px;font-weight:700;color:var(--pv-sub);}
    .hrd-divide{height:1px;background:var(--pv-line);margin:16px 0;}

    /* stacked strip */
    .hrd-stack{display:flex;height:12px;border-radius:999px;overflow:hidden;background:var(--pv-line2);}
    .hrd-stack-seg{display:block;height:100%;}
    .hrd-stack-seg + .hrd-stack-seg{box-shadow:inset 1px 0 0 var(--pv-card);}

    /* payroll bars */
    .hrd-barchart{display:flex;align-items:flex-end;justify-content:space-between;gap:10px;height:180px;padding-top:6px;}
    .hrd-vbar{flex:1;display:flex;flex-direction:column;align-items:center;gap:7px;height:100%;}
    .hrd-vbar-v{font-size:11px;font-weight:700;color:var(--pv-sub);white-space:nowrap;}
    .hrd-vbar-col{flex:1;width:100%;max-width:46px;display:flex;align-items:flex-end;}
    .hrd-vbar-fill{width:100%;height:var(--h);min-height:2px;border-radius:7px 7px 3px 3px;
        background:linear-gradient(180deg,var(--pv-brand-2),var(--pv-brand));}
    .hrd-vbar.is-current .hrd-vbar-fill{background:linear-gradient(180deg,var(--pv-green),#047857);}
    .hrd-vbar.is-current .hrd-vbar-v{color:var(--pv-green);}
    .hrd-vbar.is-empty .hrd-vbar-col{align-items:flex-end;}
    .hrd-vbar.is-empty .hrd-vbar-col::after{content:"";width:100%;max-width:46px;height:2px;background:var(--pv-line);border-radius:2px;}
    .hrd-vbar.is-empty .hrd-vbar-fill{display:none;}
    .hrd-vbar-l{font-size:11px;font-weight:600;color:var(--pv-mut);}
    .hrd-vbar.is-current .hrd-vbar-l{color:var(--pv-ink);font-weight:700;}
    .hrd-actions-inline,.hrd-quick{display:flex;gap:8px;flex-wrap:wrap;}
    .hrd-actions-inline{margin-top:16px;}
    .hrd-quick{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));}

    /* list rows */
    .hrd-listrow{display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:var(--pv-r-sm);
        transition:background .14s;}
    .hrd-listrow:hover{background:var(--pv-line2);}
    .hrd-avatar{flex:none;width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-size:13px;
        font-weight:700;color:#fff;background:linear-gradient(135deg,var(--pv-brand),var(--pv-brand-2));}
    .hrd-listrow-main{display:flex;flex-direction:column;min-width:0;flex:1;}
    .hrd-listrow-t{font-size:13.5px;font-weight:650;color:var(--pv-ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .hrd-listrow-s{font-size:12px;color:var(--pv-sub);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .hrd-listrow-when{font-size:11.5px;color:var(--pv-mut);white-space:nowrap;font-weight:600;}

    /* entrance animations */
    @media (prefers-reduced-motion:no-preference){
        .hrd-line{stroke-dasharray:1;stroke-dashoffset:1;animation:hrdDraw 1.1s .15s ease forwards;}
        .hrd-area{opacity:0;animation:hrdFade .8s .7s ease forwards;}
        .hrd-dot,.hrd-hit{opacity:0;animation:hrdFade .4s ease forwards;animation-delay:calc(0.9s + var(--d,0s));}
        .hrd-seg{animation:hrdSeg .9s .2s cubic-bezier(.4,0,.2,1) both;}
        .hrd-bar-fill{animation:hrdGrow 1s .2s cubic-bezier(.4,0,.2,1) both;}
        .hrd-stack-seg{animation:hrdGrow 1s .3s cubic-bezier(.4,0,.2,1) both;}
        .hrd-vbar-fill{animation:hrdRise .9s .2s cubic-bezier(.4,0,.2,1) both;transform-origin:bottom;}
        .hrd-kpi{opacity:0;animation:hrdUp .5s ease forwards;}
        .hrd-kpi:nth-child(1){animation-delay:.02s}.hrd-kpi:nth-child(2){animation-delay:.06s}
        .hrd-kpi:nth-child(3){animation-delay:.10s}.hrd-kpi:nth-child(4){animation-delay:.14s}
        .hrd-kpi:nth-child(5){animation-delay:.18s}.hrd-kpi:nth-child(6){animation-delay:.22s}
    }
    @keyframes hrdDraw{to{stroke-dashoffset:0}}
    @keyframes hrdFade{to{opacity:1}}
    @keyframes hrdSeg{from{stroke-dasharray:0 999}}
    @keyframes hrdGrow{from{width:0}}
    @keyframes hrdRise{from{transform:scaleY(0)}}
    @keyframes hrdUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

    @media(max-width:900px){
        .hrd-barchart{height:150px}
        .hrd-bar-l{width:40%}
    }
</style>

<script nonce="{{ csp_nonce() }}">
(function(){
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var els = document.querySelectorAll('.hrd [data-countup]');
    els.forEach(function(el){
        var raw = el.textContent.trim();
        var m = raw.match(/^([₹]?)([\d,.]+)(.*)$/);
        if (!m) return;
        var prefix = m[1], suffix = m[3];
        var target = parseFloat(m[2].replace(/,/g,''));
        if (!isFinite(target) || target === 0) return;
        var dec = (m[2].split('.')[1] || '').length;
        var dur = 900, start = performance.now();
        function tick(now){
            var p = Math.min(1, (now - start) / dur);
            var e = 1 - Math.pow(1 - p, 3);
            var v = (target * e).toFixed(dec);
            el.textContent = prefix + Number(v).toLocaleString('en-IN') + suffix;
            if (p < 1) requestAnimationFrame(tick);
            else el.textContent = raw;
        }
        el.textContent = prefix + '0' + suffix;
        requestAnimationFrame(tick);
    });
})();
</script>
@endsection
