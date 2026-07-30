@extends('admin.master_layout')
@section('title')
    <title>{{ __('Zoom Health') }}</title>
@endsection

@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>{{ __('Zoom OAuth Health') }}</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">{{ __('Settings') }}</div>
                <div class="breadcrumb-item">{{ __('Zoom Health') }}</div>
            </div>
        </div>

        <div class="section-body">
            {{-- Tally cards --}}
            <div class="row">
                <div class="col-md-3">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-success"><i class="fas fa-check"></i></div>
                        <div class="card-wrap">
                            <div class="card-header"><h4>{{ __('Healthy') }}</h4></div>
                            <div class="card-body">{{ $tally['ok'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-warning"><i class="fas fa-clock"></i></div>
                        <div class="card-wrap">
                            <div class="card-header"><h4>{{ __('Expiring soon') }}</h4></div>
                            <div class="card-body">{{ $tally['expiring'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-danger"><i class="fas fa-triangle-exclamation"></i></div>
                        <div class="card-wrap">
                            <div class="card-header"><h4>{{ __('Reconnect needed') }}</h4></div>
                            <div class="card-body">{{ $tally['dead'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-secondary"><i class="fas fa-question"></i></div>
                        <div class="card-wrap">
                            <div class="card-header"><h4>{{ __('Unknown') }}</h4></div>
                            <div class="card-body">{{ $tally['unknown'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4>{{ __('Instructors') }} ({{ $tally['total'] }})</h4>
                    <div class="card-header-action">
                        <form method="POST" action="{{ route('admin.zoom-health.probe-all') }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> {{ __('Probe all now') }}
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if ($credentials->isEmpty())
                        <p class="text-muted text-center p-4">{{ __('No instructors have connected Zoom yet.') }}</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Instructor') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Token expires') }}</th>
                                    <th>{{ __('Last check') }}</th>
                                    <th>{{ __('Detail') }}</th>
                                    <th class="text-right">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($credentials as $c)
                                    @php
                                        $color = match ($c->health_status) {
                                            'ok'       => 'success',
                                            'expiring' => 'warning',
                                            'dead'     => 'danger',
                                            default    => 'secondary',
                                        };
                                        $label = match ($c->health_status) {
                                            'ok'       => __('Healthy'),
                                            'expiring' => __('Expiring'),
                                            'dead'     => __('Reconnect'),
                                            default    => __('Unknown'),
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold">{{ $c->instructor?->name ?? '—' }}</div>
                                            <div class="text-muted small">{{ $c->instructor?->email ?? '#'.$c->instructor_id }}</div>
                                        </td>
                                        <td><span class="badge badge-{{ $color }}">{{ $label }}</span></td>
                                        <td>
                                            @if ($c->zoom_token_expires_at)
                                                <span title="{{ $c->zoom_token_expires_at }}">
                                                    {{ $c->zoom_token_expires_at->diffForHumans() }}
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if ($c->last_health_check_at)
                                                <span title="{{ $c->last_health_check_at }}">
                                                    {{ $c->last_health_check_at->diffForHumans() }}
                                                </span>
                                            @else
                                                <span class="text-muted">{{ __('Never') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">{{ $c->health_message ?: '—' }}</td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('admin.zoom-health.probe-one', $c->instructor_id) }}" style="display:inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-sync-alt"></i> {{ __('Probe') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
