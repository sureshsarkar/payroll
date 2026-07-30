@extends('admin.master_layout')
@section('title')<title>{{ __('Course Batches') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-layer-group"></i> {{ __('Course Batches') }}</h1>
        </div>

        <div class="card">
            <div class="card-header">
                <form method="GET" class="form-inline" style="gap:8px; flex-wrap:wrap;">
                    <input type="text" name="q" class="form-control mr-2 mb-2" value="{{ request('q') }}"
                           placeholder="{{ __('Search batch / course') }}" style="min-width:200px;">
                    <select name="status" class="form-control mr-2 mb-2">
                        <option value="">{{ __('Any status') }}</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                    </select>
                    <button class="btn btn-primary mb-2"><i class="fas fa-search"></i></button>
                    @if (request()->hasAny(['q','status']))
                        <a href="{{ route('admin.batches.index') }}" class="btn btn-outline-secondary mb-2">{{ __('Clear') }}</a>
                    @endif
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ __('Batch') }}</th>
                                <th>{{ __('Course') }}</th>
                                <th>{{ __('Schedule') }}</th>
                                <th class="text-center">{{ __('Total') }}</th>
                                <th class="text-center">{{ __('Attended Today') }}</th>
                                <th class="text-center">{{ __('Not Attended') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-right">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($batches as $batch)
                            @php $s = $summaries[$batch->id] ?? null; @endphp
                            <tr>
                                <td><strong>{{ $batch->title }}</strong></td>
                                <td>{{ \Illuminate\Support\Str::limit(optional($batch->course)->title, 30) ?? '—' }}</td>
                                <td>
                                    <small class="text-muted">
                                        {{ optional($batch->start_date)->format('Y-m-d') }} →
                                        {{ optional($batch->end_date)->format('Y-m-d') }}
                                    </small>
                                </td>
                                <td class="text-center"><strong>{{ $s['total_students'] ?? 0 }}</strong></td>
                                <td class="text-center"><span class="badge badge-success">{{ $s['attended'] ?? 0 }}</span></td>
                                <td class="text-center"><span class="badge badge-danger">{{ $s['not_attended'] ?? 0 }}</span></td>
                                <td>
                                    <span class="badge badge-{{ $batch->status === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($batch->status) }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('admin.batch-attendance.show', $batch->id) }}"
                                       class="btn btn-sm btn-info" title="{{ __('View attendance') }}">
                                        <i class="fas fa-users"></i> {{ __('Attendance') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No batches found.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($batches->hasPages())
                <div class="card-footer">{{ $batches->links() }}</div>
            @endif
        </div>
    </section>
</div>
@endsection
