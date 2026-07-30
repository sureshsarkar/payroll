@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<div class="corp-page">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-chart-pie"></i> {{ __('Reports') }}</h4>
            <p>{{ __('One place for all your reporting — revenue, payments, invoices and attendance.') }}</p>
        </div>
        <div class="corp-header__actions">
            <span style="display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 14px;border-radius:10px;background:#fff;border:1px solid #e6e8f0;color:#475569;font-size:12.5px;font-weight:600;">
                <i class="fas fa-calendar-alt" style="color:#10b981;"></i>
                {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
            </span>
        </div>
    </div>

    {{-- Headline KPIs (this month) — figures come straight from the certified
         OrderItem money methods, tenant-scoped to the coach's courses. --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px;">
        @foreach ($kpis as $k)
            <div style="background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:16px 18px;position:relative;overflow:hidden;">
                <div style="position:absolute;left:0;top:0;bottom:0;width:3px;background:{{ $k['accent'] }};"></div>
                <div style="display:flex;align-items:center;gap:8px;color:#64748b;font-size:12px;font-weight:600;">
                    <i class="bi {{ $k['icon'] }}" style="color:{{ $k['accent'] }};"></i> {{ $k['label'] }}
                </div>
                <div style="font-size:22px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $k['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Report cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;">
        @foreach ($cards as $c)
            <a href="{{ route('instructor.reports.show', $c['type']) }}"
               style="display:block;background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:18px;text-decoration:none;transition:box-shadow .15s,transform .15s;">
                <div style="width:42px;height:42px;border-radius:11px;background:#ecfdf5;color:#10b981;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px;">
                    <i class="bi {{ $c['icon'] }}"></i>
                </div>
                <div style="font-size:15px;font-weight:700;color:#0f172a;">{{ $c['title'] }}</div>
                <div style="font-size:12.5px;color:#64748b;margin-top:4px;line-height:1.5;">{{ $c['desc'] }}</div>
                <div style="margin-top:12px;font-size:12.5px;font-weight:600;color:#10b981;">
                    {{ __('Open report') }} <i class="fas fa-arrow-right" style="font-size:10px;"></i>
                </div>
            </a>
        @endforeach
    </div>
</div>

<style>
    .corp-page a[href*="/reports/"]:hover { box-shadow:0 6px 18px rgba(15,23,42,.08); transform:translateY(-2px); }
    html[data-theme="dark"] .corp-page > div[style*="background:#fff"],
    html[data-theme="dark"] .corp-page a[href*="/reports/"] { background:#1e293b !important; border-color:#2a3a55 !important; }
    html[data-theme="dark"] .corp-page a[href*="/reports/"] div[style*="color:#0f172a"] { color:#e2e8f0 !important; }
</style>
@endsection
