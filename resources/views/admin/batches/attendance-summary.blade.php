@extends('admin.master_layout')
@section('title')<title>{{ __('Batch Attendance') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-users"></i> {{ __('Batch Attendance') }}</h1>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4 class="mb-0">{{ $batch->title }}</h4>
                    <small class="text-muted">
                        {{ __('Course') }}: {{ optional($batch->course)->title ?? '—' }}
                        &middot;
                        {{ optional($batch->start_date)->format('Y-m-d') }} →
                        {{ optional($batch->end_date)->format('Y-m-d') }}
                    </small>
                </div>
                <form method="GET" class="form-inline">
                    <label for="date" class="mr-2"><small class="text-muted">{{ __('As of date') }}:</small></label>
                    <input type="date" name="date" id="date" class="form-control mr-2" value="{{ $summary['date'] }}">
                    <button class="btn btn-sm btn-primary">{{ __('Update') }}</button>
                </form>
            </div>
            <div class="card-body">
                <x-batch-attendance-summary :summary="$summary" />

                @if (!empty($summary['live_class_ids']))
                    <p class="text-muted mt-2">
                        <small>
                            {{ trans_choice('Live class IDs|Live class IDs', count($summary['live_class_ids'])) }}:
                            {{ implode(', ', $summary['live_class_ids']) }}
                        </small>
                    </p>
                @else
                    <p class="text-muted mt-2"><small>{{ __('No live class scheduled on this date for this batch.') }}</small></p>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
