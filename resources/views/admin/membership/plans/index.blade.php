@extends('admin.master_layout')
@section('title')<title>{{ __('Membership Plans') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-shield-alt"></i> {{ __('Membership Plans') }}</h1>
            <div class="section-header-breadcrumb">
                <a href="{{ route('admin.membership-plans.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('New plan') }}</a>
            </div>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    {{-- M11 (2026-05-12) — added table-hover for consistency with the other admin lists. --}}
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                {{-- M6 (2026-05-12) — sortable headers. Only Action stays static. --}}
                                @include('admin.partials.sort-header', ['key' => 'name',          'label' => __('Name'),     'sort' => $sort, 'dir' => $dir, 'route' => 'admin.membership-plans.index'])
                                @include('admin.partials.sort-header', ['key' => 'role',          'label' => __('Role'),     'sort' => $sort, 'dir' => $dir, 'route' => 'admin.membership-plans.index'])
                                @include('admin.partials.sort-header', ['key' => 'price',         'label' => __('Price'),    'sort' => $sort, 'dir' => $dir, 'route' => 'admin.membership-plans.index'])
                                <th>{{ __('Setup fee') }}</th>
                                <th>{{ __('Commission') }}</th>
                                <th>{{ __('Capacity') }}</th>
                                <th>{{ __('Settlement') }}</th>
                                @include('admin.partials.sort-header', ['key' => 'duration_days', 'label' => __('Duration'), 'sort' => $sort, 'dir' => $dir, 'route' => 'admin.membership-plans.index'])
                                @include('admin.partials.sort-header', ['key' => 'status',        'label' => __('Status'),   'sort' => $sort, 'dir' => $dir, 'route' => 'admin.membership-plans.index'])
                                @include('admin.partials.sort-header', ['key' => 'sort_order',    'label' => __('Sort'),     'sort' => $sort, 'dir' => $dir, 'route' => 'admin.membership-plans.index'])
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($plans as $plan)
                            <tr>
                                <td>
                                    <strong>{{ $plan->name }}</strong>
                                    @if (!empty($plan->features))
                                        <div class="text-muted" style="font-size:11px;">{{ count($plan->features) }} {{ __('features') }}</div>
                                    @endif
                                </td>
                                <td><span class="badge badge-info">{{ ucfirst($plan->role) }}</span></td>
                                <td>{{ currency($plan->price) }}</td>
                                <td>{{ $plan->setup_fee_custom ? __('Custom') : ((float) ($plan->setup_fee ?? 0) > 0 ? currency($plan->setup_fee) : '—') }}</td>
                                <td>
                                    @php
                                        $cmin = $plan->commission_min_rate; $cmax = $plan->commission_max_rate; $cr = $plan->platform_commission_rate;
                                    @endphp
                                    @if ($cmin !== null && $cmax !== null)
                                        {{ (0 + $cmin) }}–{{ (0 + $cmax) }}%
                                    @elseif ($cr !== null)
                                        {{ (0 + $cr) }}%
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $plan->isUnlimitedStudents() ? __('Unlimited') : number_format($plan->student_capacity) }}</td>
                                <td>
                                    @if ($plan->direct_settlement)
                                        <span class="badge badge-success">{{ __('Direct') }}</span>
                                    @elseif (! $plan->payout_required)
                                        <span class="badge badge-info">{{ __('No payout') }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ __('Payout req.') }}</span>
                                    @endif
                                </td>
                                <td>{{ $plan->duration_days > 0 ? $plan->duration_days . ' ' . __('days') : __('Lifetime') }}</td>
                                <td>
                                    @if ($plan->status === 'active')
                                        <span class="badge badge-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td>{{ $plan->sort_order }}</td>
                                <td>
                                    <a href="{{ route('admin.membership-plans.edit', $plan->id) }}"
                                       class="btn btn-sm btn-success"
                                       title="{{ __('Edit plan') }}"
                                       aria-label="{{ __('Edit plan') }}: {{ $plan->name }}"><i class="fas fa-edit" aria-hidden="true"></i></a>
                                    <form method="POST" action="{{ route('admin.membership-plans.destroy', $plan->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this plan?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" title="{{ __('Delete plan') }}" aria-label="{{ __('Delete plan') }}: {{ $plan->name }}"><i class="fas fa-trash" aria-hidden="true"></i></button></form>
                                </td>
                            </tr>
                        @empty
                            @include('admin.partials.empty-state', [
                                'colspan'  => 11,
                                'icon'     => 'fa-id-card',
                                'title'    => __('No plans yet'),
                                'subtitle' => __('Create your first plan to start offering memberships.'),
                            ])
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
