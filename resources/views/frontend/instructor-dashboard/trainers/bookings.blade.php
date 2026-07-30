@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

@php
    $sym = function ($cur) { return ($cur ?: 'INR') === 'INR' ? '₹' : (($cur ?: 'INR') . ' '); };
    $payBadge = [
        'paid'      => ['#ecfdf5', '#065f46', __('Paid')],
        'pending'   => ['#fffbeb', '#92400e', __('Pending')],
        'failed'    => ['#fef2f2', '#991b1b', __('Failed')],
        'cancelled' => ['#f1f5f9', '#475569', __('Cancelled')],
        'unpaid'    => ['#eef2ff', '#3730a3', __('Lead')],
    ];
@endphp

<div class="corp-page" id="trainerBookings">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-calendar-check"></i> {{ __('Trainer Bookings') }}</h4>
            <p>{{ __('Personal Class Session bookings from your trainer pages. Separate from other enquiries.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.trainers.index') }}" class="btn-corp-secondary"><i class="fas fa-user-tie"></i> {{ __('Manage trainers') }}</a>
        </div>
    </div>

    @if(session('messege'))
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13.5px;">{{ session('messege') }}</div>
    @endif

    {{-- KPIs --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px;">
        <div class="corp-form-card"><div class="corp-form-card__body"><div style="font-size:12.5px;color:#64748b;">{{ __('Total bookings') }}</div><div style="font-size:24px;font-weight:700;color:#1e293b;">{{ $kpis['total'] }}</div></div></div>
        <div class="corp-form-card"><div class="corp-form-card__body"><div style="font-size:12.5px;color:#64748b;">{{ __('Paid') }}</div><div style="font-size:24px;font-weight:700;color:#059669;">{{ $kpis['paid'] }}</div></div></div>
        <div class="corp-form-card"><div class="corp-form-card__body"><div style="font-size:12.5px;color:#64748b;">{{ __('Pending') }}</div><div style="font-size:24px;font-weight:700;color:#b45309;">{{ $kpis['pending'] }}</div></div></div>
        <div class="corp-form-card"><div class="corp-form-card__body"><div style="font-size:12.5px;color:#64748b;">{{ __('Collected') }}</div><div style="font-size:24px;font-weight:700;color:#1e293b;">₹{{ number_format((float) $kpis['collected'], 0) }}</div></div></div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="corp-form-card" style="margin-bottom:14px;">
        <div class="corp-form-card__body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end;">
            <label style="font-size:12px;color:#475569;">{{ __('Search') }}
                <input name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('Name / email / phone') }}" style="margin-top:4px;">
            </label>
            <label style="font-size:12px;color:#475569;">{{ __('Trainer') }}
                <select name="trainer_id" class="form-control form-control-sm" style="margin-top:4px;">
                    <option value="">{{ __('All trainers') }}</option>
                    @foreach($trainerOpts as $t)<option value="{{ $t->id }}" {{ (int)$trainerId===$t->id?'selected':'' }}>{{ $t->name }}</option>@endforeach
                </select>
            </label>
            <label style="font-size:12px;color:#475569;">{{ __('Package') }}
                <select name="package_id" class="form-control form-control-sm" style="margin-top:4px;">
                    <option value="">{{ __('All packages') }}</option>
                    @foreach($packageOpts as $p)<option value="{{ $p->id }}" {{ (int)$packageId===$p->id?'selected':'' }}>{{ $p->label() }}</option>@endforeach
                </select>
            </label>
            <label style="font-size:12px;color:#475569;">{{ __('Payment') }}
                <select name="payment" class="form-control form-control-sm" style="margin-top:4px;">
                    <option value="">{{ __('All') }}</option>
                    @foreach(['paid','pending','failed','cancelled','unpaid'] as $ps)<option value="{{ $ps }}" {{ $payment===$ps?'selected':'' }}>{{ $payBadge[$ps][2] ?? ucfirst($ps) }}</option>@endforeach
                </select>
            </label>
            <label style="font-size:12px;color:#475569;">{{ __('From') }}
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm" style="margin-top:4px;">
            </label>
            <label style="font-size:12px;color:#475569;">{{ __('To') }}
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm" style="margin-top:4px;">
            </label>
            <div style="display:flex;gap:6px;">
                <button class="btn-corp-primary" style="white-space:nowrap;"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
                <a href="{{ route('instructor.trainers.bookings') }}" class="btn-corp-secondary">{{ __('Reset') }}</a>
            </div>
        </div>
    </form>

    {{-- List --}}
    <div class="corp-form-card">
        <div class="corp-form-card__body" style="overflow-x:auto;">
            @if($enquiries->count())
                <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:720px;">
                    <thead><tr style="text-align:left;color:#64748b;border-bottom:1px solid #eef0f5;">
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Student') }}</th>
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Trainer') }}</th>
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Package') }}</th>
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Amount') }}</th>
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Payment') }}</th>
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Status') }}</th>
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Date') }}</th>
                        <th style="padding:9px 8px;font-weight:600;"></th>
                    </tr></thead>
                    <tbody>
                        @foreach($enquiries as $e)
                            @php $b = $payBadge[$e->payment_status] ?? $payBadge['unpaid']; @endphp
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px 8px;"><div style="color:#1e293b;font-weight:500;">{{ $e->name }}</div><div style="color:#94a3b8;font-size:11.5px;">{{ $e->email }}</div><div style="color:#94a3b8;font-size:11.5px;">{{ $e->mobile }}</div></td>
                                <td style="padding:10px 8px;color:#475569;">{{ $e->category }}</td>
                                <td style="padding:10px 8px;color:#475569;">{{ $e->time_period }}</td>
                                <td style="padding:10px 8px;font-weight:600;color:#1e293b;">
                                    @if($e->payment_status === 'paid' && $e->paid_amount){{ $sym($e->currency) }}{{ number_format((float)$e->paid_amount,0) }}
                                    @elseif($e->plan_amount){{ $sym($e->currency) }}{{ number_format((float)$e->plan_amount,0) }}
                                    @else <span style="color:#94a3b8;">—</span>@endif
                                </td>
                                <td style="padding:10px 8px;"><span style="background:{{ $b[0] }};color:{{ $b[1] }};font-size:11px;font-weight:600;padding:2px 9px;border-radius:999px;">{{ $b[2] }}</span></td>
                                <td style="padding:10px 8px;">
                                    <form action="{{ route('instructor.trainers.bookings.status', $e->id) }}" method="POST">@csrf @method('PUT')
                                        <select name="status" onchange="this.form.submit()" class="form-control form-control-sm" style="min-width:118px;">
                                            @foreach(['new'=>__('New'),'contacted'=>__('Contacted'),'converted'=>__('Converted'),'closed'=>__('Closed')] as $sv=>$sl)
                                                <option value="{{ $sv }}" {{ ($e->status ?? 'new')===$sv?'selected':'' }}>{{ $sl }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td style="padding:10px 8px;color:#94a3b8;white-space:nowrap;">{{ optional($e->created_at)->format('d M Y') }}</td>
                                <td style="padding:10px 8px;">
                                    <form action="{{ route('instructor.trainers.bookings.destroy', $e->id) }}" method="POST" onsubmit="return confirm('{{ __('Delete this booking?') }}');">@csrf @method('DELETE')
                                        <button class="btn-corp-secondary" style="padding:5px 9px;color:#dc2626;" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="margin-top:14px;">{{ $enquiries->links() }}</div>
            @else
                <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
                    <i class="fas fa-calendar-check" style="font-size:30px;margin-bottom:10px;"></i>
                    <p style="margin:0;">{{ __('No trainer bookings yet. They appear here when visitors book a Personal Class Session.') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
