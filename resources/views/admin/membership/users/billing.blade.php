@extends('admin.master_layout')
@section('title')<title>{{ __('Coach Billing') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-chart-line"></i> {{ __('Coach Billing & Settlement') }}</h1>
            <div class="section-header-breadcrumb">
                <a href="{{ route('admin.user-memberships.assign.form') }}" class="btn btn-primary"><i class="fas fa-user-tag"></i> {{ __('Assign plan') }}</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <form method="GET" class="d-flex" style="gap:8px;max-width:380px;">
                    <input type="text" name="q" value="{{ $keyword }}" class="form-control" placeholder="{{ __('Search coach name / email') }}">
                    <button class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('Coach') }}</th>
                                <th>{{ __('Plan') }}</th>
                                <th>{{ __('Commission') }}</th>
                                <th>{{ __('Capacity') }}</th>
                                <th>{{ __('Gross') }}</th>
                                <th>{{ __('Platform') }}</th>
                                <th>{{ __('Coach revenue') }}</th>
                                <th>{{ __('Wallet') }}</th>
                                <th>{{ __('Settlement') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($rows as $row)
                            @php $c = $row['coach']; $s = $row['summary']; @endphp
                            <tr>
                                <td><strong>{{ $c->name }}</strong><div class="text-muted" style="font-size:11px;">{{ $c->email }}</div></td>
                                <td>
                                    @if($s['plan'])<span class="badge badge-primary">{{ $s['plan']->name }}</span>
                                    @else<span class="text-muted">{{ __('No plan') }}</span>@endif
                                </td>
                                <td>{{ rtrim(rtrim(number_format($s['commission_rate'],2),'0'),'.') }}%</td>
                                <td>{{ $s['capacity_unlimited'] ? __('Unlimited') : number_format($s['capacity_used']).' / '.number_format($s['capacity']) }}</td>
                                {{-- AUD-028 — render each money column PER currency (never a
                                     cross-currency sum). formatMoney is session-rate independent. --}}
                                @php $bc = !empty($s['by_currency']) ? $s['by_currency'] : [['currency'=>$s['primary_currency'] ?? null,'gross'=>$s['gross'],'platform_commission'=>$s['platform_commission'],'coach_revenue'=>$s['coach_revenue'],'is_exception'=>false]]; @endphp
                                <td>@foreach($bc as $g)<div @if($g['is_exception']) class="text-danger" @endif>{{ formatMoneyCur($g['gross'], $g['currency']) }}</div>@endforeach</td>
                                <td class="text-danger">@foreach($bc as $g)<div>{{ formatMoneyCur($g['platform_commission'], $g['currency']) }}</div>@endforeach</td>
                                <td class="text-success">@foreach($bc as $g)<div>{{ formatMoneyCur($g['coach_revenue'], $g['currency']) }}</div>@endforeach</td>
                                <td>{{ formatMoneyCur($s['wallet_balance'], $s['primary_currency'] ?? null) }}</td>
                                <td>
                                    @if($s['direct_settlement'])<span class="badge badge-success">{{ __('Direct') }}</span>
                                    @elseif(!$s['payout_required'])<span class="badge badge-info">{{ __('No payout') }}</span>
                                    @else<span class="badge badge-secondary">{{ __('Payout req.') }}</span>@endif
                                </td>
                            </tr>
                        @empty
                            @include('admin.partials.empty-state', ['colspan' => 9, 'icon' => 'fa-chart-line', 'title' => __('No coaches'), 'subtitle' => __('Coaches will appear here once they onboard.')])
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($coaches->hasPages())<div class="card-footer">{{ $coaches->links() }}</div>@endif
        </div>
    </section>
</div>
@endsection
