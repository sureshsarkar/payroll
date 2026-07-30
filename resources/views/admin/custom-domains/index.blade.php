@extends('admin.master_layout')
@section('title')<title>{{ __('Custom Domains') }}</title>@endsection
@section('admin-content')
@php
    $statusBadge = [
        'pending'   => ['#fffbeb', '#92400e', __('Pending')],
        'verified'  => ['#eff6ff', '#1d4ed8', __('Verified — awaiting approval')],
        'active'    => ['#ecfdf5', '#047857', __('Active')],
        'failed'    => ['#fef2f2', '#991b1b', __('Failed')],
        'suspended' => ['#f3f4f6', '#374151', __('Suspended')],
    ];
    $sslBadge = [
        'none'    => ['#f3f4f6', '#6b7280', __('No SSL')],
        'pending' => ['#fffbeb', '#92400e', __('SSL pending')],
        'issued'  => ['#ecfdf5', '#047857', __('SSL issued')],
        'failed'  => ['#fef2f2', '#991b1b', __('SSL failed')],
    ];
@endphp
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-globe"></i> {{ __('Custom Domains') }}</h1>
            <div class="section-header-breadcrumb">
                <a href="{{ route('admin.custom-domains.settings') }}" class="btn btn-primary">
                    <i class="fas fa-cog"></i> {{ __('Settings') }}
                </a>
            </div>
        </div>

        @if (! $settings->enabled())
            <div class="alert alert-warning">
                {{ __('The custom-domain feature is currently DISABLED. Coaches cannot add domains until you enable it in Settings.') }}
            </div>
        @endif

        {{-- Summary cards --}}
        <div class="row">
            @foreach (['total' => '#5751e1', 'pending' => '#f59e0b', 'verified' => '#1d4ed8', 'active' => '#10b981', 'failed' => '#ef4444', 'suspended' => '#6b7280'] as $k => $color)
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="card text-center" style="padding:14px;">
                        <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ ucfirst($k) }}</div>
                        <div style="font-size:22px; font-weight:700; color:{{ $color }};">{{ $totals[$k] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Filters --}}
        <div class="card">
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <select name="status" class="form-control mr-2 mb-2">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (['pending', 'verified', 'active', 'failed', 'suspended'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control mr-2 mb-2" placeholder="{{ __('Search domain / coach') }}">
                    <button type="submit" class="btn btn-primary mb-2"><i class="fas fa-search"></i> {{ __('Filter') }}</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('Domain') }}</th>
                            <th>{{ __('Coach') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('SSL') }}</th>
                            <th>{{ __('Last verified') }}</th>
                            <th style="width:330px;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($domains as $d)
                        @php
                            $sb = $statusBadge[$d->status] ?? ['#f3f4f6', '#374151', $d->status];
                            $ss = $sslBadge[$d->ssl_status] ?? ['#f3f4f6', '#6b7280', $d->ssl_status];
                        @endphp
                        <tr>
                            <td><code>{{ $d->hostname }}</code>
                                @if ($d->last_error)
                                    <div style="font-size:11px; color:#b91c1c;">{{ \Illuminate\Support\Str::limit($d->last_error, 70) }}</div>
                                @endif
                            </td>
                            <td>{{ $d->coach?->name ?? '—' }}<div style="font-size:11px;color:#6b7280;">{{ $d->coach?->email }}</div></td>
                            <td><span class="badge" style="background:{{ $sb[0] }};color:{{ $sb[1] }};">{{ $sb[2] }}</span></td>
                            <td><span class="badge" style="background:{{ $ss[0] }};color:{{ $ss[1] }};">{{ $ss[2] }}</span></td>
                            <td>{{ $d->last_verified_at?->diffForHumans() ?? '—' }}</td>
                            <td>
                                <a href="{{ route('admin.custom-domains.show', $d->id) }}" class="btn btn-sm btn-info" title="{{ __('View') }}"><i class="fas fa-eye"></i></a>

                                @if (in_array($d->status, ['pending', 'failed', 'verified']))
                                    @include('admin.custom-domains._action', ['route' => route('admin.custom-domains.recheck', $d->id), 'class' => 'btn-secondary', 'icon' => 'fa-sync', 'label' => __('Re-check')])
                                @endif
                                @if ($d->status === 'verified')
                                    @include('admin.custom-domains._action', ['route' => route('admin.custom-domains.approve', $d->id), 'class' => 'btn-success', 'icon' => 'fa-check', 'label' => __('Approve')])
                                    @include('admin.custom-domains._action', ['route' => route('admin.custom-domains.reject', $d->id), 'class' => 'btn-warning', 'icon' => 'fa-times', 'label' => __('Reject'), 'confirm' => __('Reject this domain?')])
                                @endif
                                @if ($d->status === 'active')
                                    @include('admin.custom-domains._action', ['route' => route('admin.custom-domains.suspend', $d->id), 'class' => 'btn-warning', 'icon' => 'fa-pause', 'label' => __('Suspend'), 'confirm' => __('Suspend this domain? It will stop serving.')])
                                @endif
                                @if ($d->status === 'suspended')
                                    @include('admin.custom-domains._action', ['route' => route('admin.custom-domains.resume', $d->id), 'class' => 'btn-success', 'icon' => 'fa-play', 'label' => __('Resume')])
                                @endif

                                <form action="{{ route('admin.custom-domains.remove', $d->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('{{ __('Remove this domain permanently?') }}');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" title="{{ __('Remove') }}"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ __('No custom domains yet.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $domains->links() }}
            </div>
        </div>
    </section>
</div>
@endsection
