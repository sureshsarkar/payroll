@extends('admin.master_layout')
@section('title')<title>{{ __('Coach Commissions') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-percentage"></i> {{ __('Coach Commissions') }}</h1>
        </div>

        <div class="alert alert-info" style="padding:10px 14px; font-size:13px;">
            <i class="fas fa-info-circle"></i>
            {{ __('Global commission rate is :rate%. Coaches with no override below use this default. Override per-coach to grant temporary discounts, permanent rate changes, or 0% (free).', ['rate' => $globalRate]) }}
        </div>

        <div class="card">
            <div class="card-header">
                <form method="GET" class="form-inline" style="gap:8px; flex-wrap:wrap;">
                    <input type="text" name="q" class="form-control mr-2 mb-2" value="{{ request('q') }}"
                           placeholder="{{ __('Search name / email') }}" style="min-width:220px;">
                    <select name="mode" class="form-control mr-2 mb-2">
                        <option value="">{{ __('All') }}</option>
                        <option value="default"   {{ request('mode') === 'default'   ? 'selected' : '' }}>{{ __('Using global default') }}</option>
                        <option value="override"  {{ request('mode') === 'override'  ? 'selected' : '' }}>{{ __('Any override') }}</option>
                        <option value="temporary" {{ request('mode') === 'temporary' ? 'selected' : '' }}>{{ __('Temporary') }}</option>
                        <option value="permanent" {{ request('mode') === 'permanent' ? 'selected' : '' }}>{{ __('Permanent') }}</option>
                        <option value="free"      {{ request('mode') === 'free'      ? 'selected' : '' }}>{{ __('Free (0%)') }}</option>
                    </select>
                    <button class="btn btn-primary mb-2"><i class="fas fa-search"></i></button>
                    @if (request()->hasAny(['q','mode']))
                        <a href="{{ route('admin.coach-commissions.index') }}" class="btn btn-outline-secondary mb-2">{{ __('Clear') }}</a>
                    @endif
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ __('Coach') }}</th>
                                <th class="text-right">{{ __('Effective rate') }}</th>
                                <th>{{ __('Mode') }}</th>
                                <th>{{ __('Note') }}</th>
                                <th class="text-right">{{ __('Wallet') }}</th>
                                <th class="text-right">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($coaches as $coach)
                                @php
                                    $isOverride = $coach->commission_rate !== null;
                                    $effective  = $isOverride ? (float) $coach->commission_rate : $globalRate;
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $coach->name }}</strong><br>
                                        <small class="text-muted">{{ $coach->email }}</small>
                                    </td>
                                    <td class="text-right">
                                        <strong style="font-size:16px;">{{ number_format($effective, 2) }}%</strong>
                                        @if (!$isOverride)
                                            <br><small class="text-muted">{{ __('global default') }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($coach->commission_mode === 'free')
                                            <span class="badge badge-success"><i class="fas fa-gift"></i> {{ __('Free') }}</span>
                                        @elseif ($coach->commission_mode === 'permanent')
                                            <span class="badge badge-warning text-dark"><i class="fas fa-anchor"></i> {{ __('Permanent') }}</span>
                                        @elseif ($coach->commission_mode === 'temporary')
                                            <span class="badge badge-info"><i class="fas fa-clock"></i> {{ __('Temporary') }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td><small>{{ \Illuminate\Support\Str::limit($coach->commission_note ?? '', 50) ?: '—' }}</small></td>
                                    <td class="text-right"><small>{{ currency($coach->wallet_balance ?? 0) }}</small></td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.coach-commissions.edit', $coach->id) }}" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> {{ __('Set commission') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-4 text-muted">{{ __('No coaches match this filter.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($coaches->hasPages())
                <div class="card-footer">{{ $coaches->links() }}</div>
            @endif
        </div>
    </section>
</div>
@endsection
