@extends('admin.master_layout')
@section('title')<title>{{ __('User Memberships') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header"><h1><i class="fas fa-users"></i> {{ __('User Memberships') }}</h1></div>

        <div class="card">
            <div class="card-header">
                <form method="GET" class="form-inline" style="gap:8px;">
                    <input type="text" name="q" class="form-control mr-2" value="{{ request('q') }}" placeholder="{{ __('Search user') }}">
                    <select name="status" class="form-control mr-2">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach (['pending','active','expired','cancelled'] as $s)
                            <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <select name="payment_status" class="form-control mr-2">
                        <option value="">{{ __('Any payment') }}</option>
                        @foreach (['pending','paid','failed','refunded'] as $s)
                            <option value="{{ $s }}" {{ request('payment_status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>{{ __('User') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Cash paid') }}</th><th>{{ __('Wallet used') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Status') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Action') }}</th></tr></thead>
                        <tbody>
                        @forelse ($memberships as $m)
                            <tr>
                                <td><strong>{{ $m->user?->name }}</strong><br><small class="text-muted">{{ $m->user?->email }} · {{ ucfirst($m->user?->role ?? '') }}</small></td>
                                <td>{{ $m->plan?->name ?? '—' }}</td>
                                <td>{{ currency($m->price_paid) }}</td>
                                <td>{{ currency($m->wallet_credit_used) }}</td>
                                <td>
                                    @switch($m->payment_status)
                                        @case('paid')     <span class="badge badge-success">{{ __('Paid') }}</span> @break
                                        @case('pending')  <span class="badge badge-warning">{{ __('Pending') }}</span> @break
                                        @case('failed')   <span class="badge badge-danger">{{ __('Failed') }}</span> @break
                                        @case('refunded') <span class="badge badge-dark">{{ __('Refunded') }}</span> @break
                                    @endswitch
                                </td>
                                <td>
                                    @switch($m->status)
                                        @case('active')    <span class="badge badge-success">{{ __('Active') }}</span> @break
                                        @case('pending')   <span class="badge badge-warning">{{ __('Pending') }}</span> @break
                                        @case('expired')   <span class="badge badge-secondary">{{ __('Expired') }}</span> @break
                                        @case('cancelled') <span class="badge badge-danger">{{ __('Cancelled') }}</span> @break
                                    @endswitch
                                </td>
                                <td>{{ $m->expires_at?->format('M d, Y') ?? '—' }}</td>
                                <td>
                                    {{-- M5 (2026-05-12) — aria-labels with membership id for disambiguation. --}}
                                    @if ($m->status === 'pending' || $m->payment_status === 'pending')
                                        <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#confirm-{{ $m->id }}"
                                            title="{{ __('Mark paid') }}"
                                            aria-label="{{ __('Mark membership paid') }} #{{ $m->id }}"><i class="fas fa-check" aria-hidden="true"></i></button>
                                        <div class="modal fade" id="confirm-{{ $m->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.user-memberships.confirm', $m->id) }}">@csrf
                                            <div class="modal-header"><h5 class="modal-title">{{ __('Confirm payment for #'.$m->id) }}</h5></div>
                                            <div class="modal-body">
                                                <div class="form-group"><label>{{ __('Cash amount paid') }}</label><input type="number" step="0.01" name="paid_amount" value="{{ $m->plan?->price - $m->wallet_credit_used }}" class="form-control" required></div>
                                                <div class="form-group"><label>{{ __('Transaction reference') }}</label><input type="text" name="transaction_id" class="form-control"></div>
                                            </div>
                                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-success">{{ __('Activate') }}</button></div>
                                        </form></div></div></div>
                                    @endif
                                    @if ($m->payment_status === 'paid' && $m->payment_status !== 'refunded' && $m->status !== 'cancelled')
                                        <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#refund-{{ $m->id }}"
                                            title="{{ __('Refund (restore wallet credit + reverse referral reward)') }}"
                                            aria-label="{{ __('Refund membership') }} #{{ $m->id }}"><i class="fas fa-undo" aria-hidden="true"></i></button>
                                        <div class="modal fade" id="refund-{{ $m->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.user-memberships.refund', $m->id) }}" onsubmit="return confirm('{{ __('Process refund? This cannot be undone.') }}')">@csrf
                                            <div class="modal-header"><h5 class="modal-title">{{ __('Refund membership #'.$m->id) }}</h5></div>
                                            <div class="modal-body">
                                                <p style="font-size:13px; margin-bottom:8px;"><strong>{{ __('What will happen:') }}</strong></p>
                                                <ul style="font-size:13px; padding-left:18px; margin-bottom:14px;">
                                                    <li>{{ __('Membership marked refunded + cancelled') }}</li>
                                                    @if ($m->wallet_credit_used > 0)
                                                        <li><strong>{{ currency($m->wallet_credit_used) }}</strong> {{ __('wallet credit restored to user') }}</li>
                                                    @endif
                                                    @if (\App\Models\Referral::where('membership_id', $m->id)->where('status', 'rewarded')->exists())
                                                        <li>{{ __('Referral reward to the referrer will be reversed') }}</li>
                                                    @endif
                                                    <li class="text-muted" style="font-size:11.5px;">{{ __('Note: cash portion (Razorpay/Stripe) must be refunded manually in the gateway dashboard.') }}</li>
                                                </ul>
                                                <div class="form-group"><label>{{ __('Reason (optional)') }}</label><textarea name="reason" class="form-control" rows="2" maxlength="500" placeholder="{{ __('Customer request / dispute / etc.') }}"></textarea></div>
                                            </div>
                                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-warning">{{ __('Process refund') }}</button></div>
                                        </form></div></div></div>
                                    @endif

                                    @if ($m->status === 'active')
                                        <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#extend-{{ $m->id }}"
                                            title="{{ __('Extend by N days') }}"
                                            aria-label="{{ __('Extend membership') }} #{{ $m->id }}"><i class="fas fa-calendar-plus" aria-hidden="true"></i></button>
                                        <div class="modal fade" id="extend-{{ $m->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.user-memberships.extend', $m->id) }}">@csrf
                                            <div class="modal-header"><h5 class="modal-title">{{ __('Extend membership #'.$m->id) }}</h5></div>
                                            <div class="modal-body">
                                                <p class="text-muted" style="font-size:13px;">{{ __('Current expiry') }}: <strong>{{ $m->expires_at?->format('M d, Y') ?? __('Lifetime') }}</strong></p>
                                                <div class="form-group"><label>{{ __('Add days') }}</label><input type="number" name="days" min="1" max="3650" value="7" class="form-control" required></div>
                                                <p class="text-muted" style="font-size:12px;">{{ __('Common picks: 7, 14, 30, 90, 365') }}</p>
                                            </div>
                                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-info">{{ __('Extend') }}</button></div>
                                        </form></div></div></div>

                                        <form method="POST" action="{{ route('admin.user-memberships.cancel', $m->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Cancel this membership?') }}')">@csrf<button class="btn btn-sm btn-danger"
                                            title="{{ __('Cancel') }}"
                                            aria-label="{{ __('Cancel membership') }} #{{ $m->id }}"><i class="fas fa-times" aria-hidden="true"></i></button></form>
                                    @elseif ($m->status === 'expired')
                                        <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#extend-{{ $m->id }}"
                                            title="{{ __('Reactivate by extending') }}"
                                            aria-label="{{ __('Reactivate membership') }} #{{ $m->id }}"><i class="fas fa-redo" aria-hidden="true"></i></button>
                                        <div class="modal fade" id="extend-{{ $m->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.user-memberships.extend', $m->id) }}">@csrf
                                            <div class="modal-header"><h5 class="modal-title">{{ __('Reactivate membership #'.$m->id) }}</h5></div>
                                            <div class="modal-body">
                                                <p class="text-muted" style="font-size:13px;">{{ __('Currently expired. New expiry will be N days from today.') }}</p>
                                                <div class="form-group"><label>{{ __('Days from today') }}</label><input type="number" name="days" min="1" max="3650" value="30" class="form-control" required></div>
                                            </div>
                                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-info">{{ __('Reactivate') }}</button></div>
                                        </form></div></div></div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center p-4 text-muted">{{ __('No memberships yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $memberships->links() }}</div>
            </div>
        </div>
    </section>
</div>
@endsection
