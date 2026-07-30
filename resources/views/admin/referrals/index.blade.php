@extends('admin.master_layout')
@section('title')<title>{{ __('Referrals') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-gift"></i> {{ __('Referrals') }}</h1>
            <div class="section-header-breadcrumb">
                <a href="{{ route('admin.referrals.settings') }}" class="btn btn-primary">
                    <i class="fas fa-cog"></i> {{ __('Settings') }}
                </a>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="row">
            <div class="col-md-2 col-sm-6 mb-3"><div class="card text-center" style="padding:14px;"><div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Total') }}</div><div style="font-size:22px; font-weight:700; color:#5751e1;">{{ $totals['total_referrals'] }}</div></div></div>
            <div class="col-md-2 col-sm-6 mb-3"><div class="card text-center" style="padding:14px;"><div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Pending') }}</div><div style="font-size:22px; font-weight:700; color:#f59e0b;">{{ $totals['pending'] }}</div></div></div>
            <div class="col-md-2 col-sm-6 mb-3"><div class="card text-center" style="padding:14px;"><div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Rewarded') }}</div><div style="font-size:22px; font-weight:700; color:#10b981;">{{ $totals['rewarded'] }}</div></div></div>
            <div class="col-md-2 col-sm-6 mb-3"><div class="card text-center" style="padding:14px;"><div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Rejected') }}</div><div style="font-size:22px; font-weight:700; color:#ef4444;">{{ $totals['rejected'] }}</div></div></div>
            <div class="col-md-2 col-sm-6 mb-3"><div class="card text-center" style="padding:14px;"><div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Reward paid') }}</div><div style="font-size:18px; font-weight:700;">{{ currency($totals['reward_paid']) }}</div></div></div>
            <div class="col-md-2 col-sm-6 mb-3"><div class="card text-center" style="padding:14px;"><div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Outstanding') }}</div><div style="font-size:18px; font-weight:700;">{{ currency($totals['wallet_outstanding']) }}</div></div></div>
        </div>

        <div class="card">
            <div class="card-header">
                <form method="GET" class="form-inline" style="gap:8px;">
                    <input type="text" name="q" class="form-control mr-2" value="{{ request('q') }}" placeholder="{{ __('Search referrer / referred') }}">
                    <select name="status" class="form-control mr-2">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach (['pending','rewarded','rejected','reversed'] as $s)
                            <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <select name="role" class="form-control mr-2">
                        <option value="">{{ __('Any role') }}</option>
                        <option value="student" {{ request('role')==='student'?'selected':'' }}>{{ __('Student') }}</option>
                        <option value="instructor" {{ request('role')==='instructor'?'selected':'' }}>{{ __('Coach') }}</option>
                    </select>
                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr><th>{{ __('Referrer') }}</th><th>{{ __('Referred') }}</th><th>{{ __('Role') }}</th><th>{{ __('Reward') }}</th><th>{{ __('Status') }}</th><th>{{ __('Date') }}</th><th>{{ __('Action') }}</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($referrals as $r)
                            <tr>
                                <td><strong>{{ $r->referrer?->name }}</strong><br><small class="text-muted">{{ $r->referrer?->email }}</small></td>
                                <td><strong>{{ $r->referred?->name }}</strong><br><small class="text-muted">{{ $r->referred?->email }}</small></td>
                                <td><span class="badge badge-info">{{ ucfirst($r->referred_role) }}</span></td>
                                <td>{{ $r->reward_amount > 0 ? currency($r->reward_amount) : '—' }}</td>
                                <td>
                                    @switch($r->status)
                                        @case('rewarded') <span class="badge badge-success">{{ __('Rewarded') }}</span> @break
                                        @case('pending')  <span class="badge badge-warning">{{ __('Pending') }}</span> @break
                                        @case('rejected') <span class="badge badge-danger">{{ __('Rejected') }}</span> @break
                                        @case('reversed') <span class="badge badge-dark">{{ __('Reversed') }}</span> @break
                                    @endswitch
                                </td>
                                <td>{{ $r->created_at?->format('M d, Y') }}</td>
                                <td>
                                    {{-- M5 (2026-05-12) — aria-labels + referral id for disambiguation. --}}
                                    @if ($r->status === 'pending')
                                        <form method="POST" action="{{ route('admin.referrals.approve', $r->id) }}" class="d-inline">@csrf <button class="btn btn-sm btn-success" title="{{ __('Approve + credit reward') }}" aria-label="{{ __('Approve referral') }} #{{ $r->id }}"><i class="fas fa-check" aria-hidden="true"></i></button></form>
                                    @endif
                                    @if (in_array($r->status, ['pending','rewarded']))
                                        <form method="POST" action="{{ route('admin.referrals.reject', $r->id) }}" class="d-inline" onsubmit="this.querySelector('input[name=reason]').value = prompt('{{ __('Rejection reason?') }}') || '';">@csrf <input type="hidden" name="reason"><button class="btn btn-sm btn-danger" title="{{ __('Reject / reverse') }}" aria-label="{{ __('Reject referral') }} #{{ $r->id }}"><i class="fas fa-times" aria-hidden="true"></i></button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center p-4 text-muted">{{ __('No referrals match the current filters.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $referrals->links() }}</div>
            </div>
        </div>
    </section>
</div>
@endsection
