@extends('admin.master_layout')
@section('title')
    <title>{{ __('Activity Log') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Activity Log') }}</h1>
            </div>

            <div class="section-body">
                <div class="card">
                    <div class="card-body">
                        {{-- Filters --}}
                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-md-3 mb-2">
                                <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                                    placeholder="{{ __('Search actor / description...') }}">
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="module" class="form-control">
                                    <option value="">{{ __('All modules') }}</option>
                                    @foreach ($modules as $m)
                                        <option value="{{ $m }}" {{ request('module') === $m ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="action" class="form-control">
                                    <option value="">{{ __('All actions') }}</option>
                                    @foreach ($actions as $a)
                                        <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ str_replace('_', ' ', $a) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" title="{{ __('From') }}">
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" title="{{ __('To') }}">
                            </div>
                            <div class="col-md-1 mb-2">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter"></i></button>
                            </div>
                            @if(request()->hasAny(['q','module','action','date_from','date_to','actor_type']))
                                <div class="col-12">
                                    <a href="{{ route('admin.activity-logs') }}" class="btn btn-sm btn-secondary">{{ __('Clear filters') }}</a>
                                </div>
                            @endif
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('When') }}</th>
                                        <th>{{ __('Actor') }}</th>
                                        <th>{{ __('Action') }}</th>
                                        <th>{{ __('Module') }}</th>
                                        <th>{{ __('Subject') }}</th>
                                        <th>{{ __('Description') }}</th>
                                        <th>{{ __('IP') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($logs as $log)
                                        <tr>
                                            <td><small>{{ $log->created_at?->format('d M Y H:i') }}</small></td>
                                            <td>
                                                <strong>{{ $log->actor_name ?? '—' }}</strong>
                                                <br><span class="badge badge-light">{{ $log->actor_role ?? $log->actor_type }}</span>
                                            </td>
                                            <td><span class="badge badge-info">{{ str_replace('_', ' ', $log->action) }}</span></td>
                                            <td>{{ ucfirst($log->module ?? '—') }}</td>
                                            <td>
                                                @if($log->subject_type)
                                                    <small>{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</small>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                <small>{{ $log->description }}</small>
                                                @if($log->old_values || $log->new_values)
                                                    <details class="mt-1">
                                                        <summary class="text-primary" style="cursor:pointer;font-size:11px;">{{ __('changes') }}</summary>
                                                        <pre class="small mb-0" style="white-space:pre-wrap;max-width:320px;">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </details>
                                                @endif
                                            </td>
                                            <td><small>{{ $log->ip_address ?? '—' }}</small></td>
                                        </tr>
                                    @empty
                                        <x-empty-table :name="__('Activity')" route="" create="no"
                                            :message="__('No activity recorded yet.')" colspan="7"></x-empty-table>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">{{ $logs->links() }}</div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
