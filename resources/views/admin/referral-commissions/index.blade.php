@extends('admin.master_layout')

@section('title')
    <title>{{ __('Referral Commissions') }}</title>
@endsection

@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-share-alt"></i> {{ __('Referral Commissions') }}</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a></div>
                <div class="breadcrumb-item">{{ __('Referrals') }}</div>
            </div>
        </div>

        <div class="section-body">
            @if (session('messege'))
                <div class="alert alert-{{ session('alert-type', 'info') == 'success' ? 'success' : 'info' }}">
                    {{ session('messege') }}
                </div>
            @endif

            {{-- Totals --}}
            <div class="row">
                @foreach (['eligible' => '#f59e0b', 'approved' => '#3b82f6', 'credited' => '#10b981', 'reversed' => '#ef4444'] as $key => $color)
                    <div class="col-md-3">
                        <div class="card" style="border-left: 4px solid {{ $color }};">
                            <div class="card-body">
                                <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px;">{{ __(ucfirst($key)) }}</div>
                                <div style="font-size:22px; font-weight:700; color:#1c1a4a; margin-top:4px;">{{ currency($totals[$key]) }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Commission rate setting --}}
            <div class="card" style="border-left:4px solid #5751e1;">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.referral-commissions.percent') }}" class="row g-2 align-items-center">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:13px; color:#1c1a4a; margin:0;">
                                <strong>{{ __('Commission rate') }}</strong> —
                                <span class="text-muted">{{ __('Applied to every paid order from a referred buyer.') }}</span>
                            </label>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="number" name="percent" value="{{ $referralPercent }}" min="0" max="100" step="0.5" class="form-control" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">{{ __('Update rate') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Filters --}}
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="{{ __('Search referrer / referred name or email') }}">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-control">
                                <option value="">{{ __('All statuses') }}</option>
                                @foreach (['eligible', 'approved', 'credited', 'rejected', 'reversed'] as $s)
                                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="fa fa-filter"></i> {{ __('Filter') }}</button>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('admin.referral-commissions.index') }}" class="btn btn-outline-secondary w-100">{{ __('Clear') }}</a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Table --}}
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Referrer') }}</th>
                                    <th>{{ __('Referred buyer') }}</th>
                                    <th>{{ __('Order') }}</th>
                                    <th class="text-end">{{ __('Amount') }}</th>
                                    <th class="text-center">{{ __('Status') }}</th>
                                    <th class="text-center">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($commissions as $c)
                                    <tr>
                                        <td><small>{{ $c->created_at->format('M j, Y') }}</small></td>
                                        <td>
                                            <strong>{{ $c->referrer?->name ?? '—' }}</strong><br>
                                            <small class="text-muted">{{ $c->referrer?->email }}</small>
                                        </td>
                                        <td>
                                            {{ $c->referred?->name ?? '—' }}<br>
                                            <small class="text-muted">{{ $c->referred?->email }}</small>
                                        </td>
                                        <td>
                                            <code style="font-size:11px;">#{{ $c->order?->invoice_id ?? $c->order_id }}</code><br>
                                            @if ($c->order)
                                                <small class="text-muted">{{ currency($c->order->paid_amount) }} order</small>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <strong>{{ currency($c->amount) }}</strong><br>
                                            <small class="text-muted">{{ $c->percent }}%</small>
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $badges = [
                                                    'eligible' => 'warning',
                                                    'approved' => 'info',
                                                    'credited' => 'success',
                                                    'rejected' => 'secondary',
                                                    'reversed' => 'danger',
                                                ];
                                            @endphp
                                            <span class="badge bg-{{ $badges[$c->status] ?? 'secondary' }}">{{ ucfirst($c->status) }}</span>
                                        </td>
                                        <td class="text-center">
                                            {{-- M5 (2026-05-12) — aria-labels + commission id for disambiguation. --}}
                                            @if ($c->status === 'eligible')
                                                <form method="POST" action="{{ route('admin.referral-commissions.approve', $c->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-info"
                                                        title="{{ __('Approve') }}"
                                                        aria-label="{{ __('Approve commission') }} #{{ $c->id }}">
                                                        <i class="fa fa-check" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if (in_array($c->status, ['eligible', 'approved']))
                                                <form method="POST" action="{{ route('admin.referral-commissions.pay', $c->id) }}" class="d-inline"
                                                      onsubmit="return confirm('{{ __('Credit referrer wallet with this amount?') }}');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success"
                                                        title="{{ __('Mark paid (credit wallet)') }}"
                                                        aria-label="{{ __('Mark commission paid') }} #{{ $c->id }}">
                                                        <i class="fa fa-money-bill" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if (! in_array($c->status, ['reversed', 'rejected']))
                                                <form method="POST" action="{{ route('admin.referral-commissions.reverse', $c->id) }}" class="d-inline"
                                                      onsubmit="return confirm('{{ __('Reverse this commission?') }}');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        title="{{ __('Reverse') }}"
                                                        aria-label="{{ __('Reverse commission') }} #{{ $c->id }}">
                                                        <i class="fa fa-undo"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center py-4 text-muted">{{ __('No referral commissions yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($commissions->hasPages())
                    <div class="card-footer">{{ $commissions->links() }}</div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
