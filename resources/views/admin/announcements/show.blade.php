@extends('admin.master_layout')
@section('title')<title>{{ __('Announcement Detail') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-bullhorn"></i> {{ __('Announcement Detail') }}</h1>
            <div class="section-header-breadcrumb">
                <a href="{{ route('admin.announcements.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h3 class="mb-3">{{ $announcement->title }}</h3>

                        <div class="mb-3">
                            <span class="badge badge-{{ $announcement->status === 'active' ? 'success' : 'secondary' }}">
                                {{ ucfirst($announcement->status ?? 'active') }}
                            </span>
                            @if ($announcement->batch)
                                <span class="badge badge-info">{{ $announcement->batch->title }}</span>
                            @else
                                <span class="badge badge-light">{{ __('Course-wide') }}</span>
                            @endif
                        </div>

                        {{-- Audit 2026-05-18 — preserve newlines via <pre> with default-escaped
                             output. Avoids unsafe raw-print syntax. --}}
                        <div class="mb-4 border-left pl-3" style="border-left:4px solid #5751e1 !important;">
                            <pre style="white-space:pre-wrap; font-family:inherit; font-size:inherit; background:transparent; border:0; padding:0;">{{ $announcement->announcement }}</pre>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <table class="table table-sm">
                            <tr>
                                <th>{{ __('Course') }}</th>
                                <td>{{ optional($announcement->course)->title ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Batch(es)') }}</th>
                                <td>
                                    {{-- Audit 2026-05-18 — multi-batch aware. --}}
                                    @if ($announcement->batches && $announcement->batches->count() > 0)
                                        @foreach ($announcement->batches as $b)
                                            <div class="mb-1">
                                                <span class="badge badge-info">{{ $b->title }}</span>
                                                <small class="text-muted">
                                                    {{ optional($b->start_date)->format('Y-m-d') }} →
                                                    {{ optional($b->end_date)->format('Y-m-d') }}
                                                </small>
                                            </div>
                                        @endforeach
                                    @elseif ($announcement->batch)
                                        {{ $announcement->batch->title }}<br>
                                        <small class="text-muted">
                                            {{ optional($announcement->batch->start_date)->format('Y-m-d') }} →
                                            {{ optional($announcement->batch->end_date)->format('Y-m-d') }}
                                        </small>
                                    @else
                                        <span class="text-muted">{{ __('All students of this course') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('Coach') }}</th>
                                <td>
                                    {{ optional($announcement->instructor)->name }}<br>
                                    <small class="text-muted">{{ optional($announcement->instructor)->email }}</small>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('Sent At') }}</th>
                                <td>{{ optional($announcement->sent_at ?? $announcement->created_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Last Updated') }}</th>
                                <td>{{ optional($announcement->updated_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
