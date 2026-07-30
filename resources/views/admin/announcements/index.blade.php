@extends('admin.master_layout')
@section('title')<title>{{ __('Announcements') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-bullhorn"></i> {{ __('Announcements') }}</h1>
            {{-- Audit 2026-05-20 — admin can now CREATE (was read-only). --}}
            <div class="section-header-breadcrumb">
                <a class="btn btn-primary" href="{{ route('admin.announcements.create') }}">
                    <i class="fas fa-plus"></i> {{ __('Create Announcement') }}
                </a>
            </div>
        </div>

        {{-- Audit 2026-05-18 — headline counters --}}
        <div class="row">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card text-center" style="padding:14px;">
                    <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Active') }}</div>
                    <div style="font-size:22px; font-weight:700; color:#10b981;">{{ $totalActive }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card text-center" style="padding:14px;">
                    <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Inactive') }}</div>
                    <div style="font-size:22px; font-weight:700; color:#6b7280;">{{ $totalInactive }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card text-center" style="padding:14px;">
                    <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Total Shown') }}</div>
                    <div style="font-size:22px; font-weight:700; color:#5751e1;">{{ $announcements->total() }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <form method="GET" class="form-inline" style="gap:8px; flex-wrap:wrap;">
                    <input type="text" name="q" class="form-control mr-2 mb-2" value="{{ request('q') }}"
                           placeholder="{{ __('Search title / message') }}" style="min-width:200px;">

                    <select name="course_id" class="form-control mr-2 mb-2">
                        <option value="">{{ __('Any course') }}</option>
                        @foreach ($courses as $c)
                            <option value="{{ $c->id }}" {{ request('course_id') == $c->id ? 'selected' : '' }}>
                                {{ \Illuminate\Support\Str::limit($c->title, 40) }}
                            </option>
                        @endforeach
                    </select>

                    <select name="batch_id" class="form-control mr-2 mb-2">
                        <option value="">{{ __('Any batch') }}</option>
                        @foreach ($batchOptions as $b)
                            <option value="{{ $b->id }}" {{ request('batch_id') == $b->id ? 'selected' : '' }}>
                                {{ \Illuminate\Support\Str::limit($b->title, 30) }}
                            </option>
                        @endforeach
                    </select>

                    <select name="instructor_id" class="form-control mr-2 mb-2">
                        <option value="">{{ __('Any coach') }}</option>
                        @foreach ($coaches as $coach)
                            <option value="{{ $coach->id }}" {{ request('instructor_id') == $coach->id ? 'selected' : '' }}>
                                {{ \Illuminate\Support\Str::limit($coach->name, 30) }}
                            </option>
                        @endforeach
                    </select>

                    <select name="status" class="form-control mr-2 mb-2">
                        <option value="">{{ __('Any status') }}</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                    </select>

                    <input type="date" name="date_from" class="form-control mr-2 mb-2" value="{{ request('date_from') }}" placeholder="{{ __('From') }}">
                    <input type="date" name="date_to" class="form-control mr-2 mb-2" value="{{ request('date_to') }}" placeholder="{{ __('To') }}">

                    <button class="btn btn-primary mb-2"><i class="fas fa-search"></i> {{ __('Filter') }}</button>
                    @if (request()->hasAny(['q','course_id','batch_id','instructor_id','status','date_from','date_to']))
                        <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary mb-2">{{ __('Clear') }}</a>
                    @endif
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Course') }}</th>
                                <th>{{ __('Batch') }}</th>
                                <th>{{ __('Coach') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Sent') }}</th>
                                <th class="text-right">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($announcements as $a)
                            <tr id="announcement-row-{{ $a->id }}">
                                <td>
                                    @if ($a->is_pinned)
                                        <i class="fas fa-thumbtack" style="color:#f59e0b;" title="{{ __('Pinned') }}"></i>
                                    @endif
                                    <strong>{{ \Illuminate\Support\Str::limit($a->title, 60) }}</strong>
                                    @if ($a->delivered_at === null && $a->scheduled_at && $a->scheduled_at->isFuture())
                                        <small class="badge badge-secondary ms-1">
                                            <i class="fas fa-clock"></i> {{ __('Scheduled') }}
                                        </small>
                                    @endif
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit(optional($a->course)->title, 30) ?: '—' }}</td>
                                <td>
                                    {{-- Audit 2026-05-18 — multi-batch aware. --}}
                                    @if ($a->batches && $a->batches->count() === 1)
                                        <span class="badge badge-info">{{ \Illuminate\Support\Str::limit($a->batches->first()->title, 24) }}</span>
                                    @elseif ($a->batches && $a->batches->count() > 1)
                                        <span class="badge badge-info" title="{{ $a->batches->pluck('title')->join(', ') }}">
                                            {{ $a->batches->count() }} {{ __('batches') }}
                                        </span>
                                    @elseif ($a->batch)
                                        <span class="badge badge-info">{{ \Illuminate\Support\Str::limit($a->batch->title, 24) }}</span>
                                    @else
                                        <span class="text-muted">{{ __('Course-wide') }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ optional($a->instructor)->name ?? '—' }}<br>
                                    <small class="text-muted">{{ optional($a->instructor)->email }}</small>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $a->status === 'active' ? 'success' : 'secondary' }}"
                                          id="announcement-status-{{ $a->id }}">
                                        {{ $a->status === 'active' ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td>{{ $a->sent_at ? $a->sent_at->format('Y-m-d H:i') : ($a->created_at ? $a->created_at->format('Y-m-d H:i') : '—') }}</td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('admin.announcements.show', $a->id) }}" class="btn btn-sm btn-info" title="{{ __('View') }}">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if (checkAdminHasPermission('announcement.toggle-status'))
                                        <button type="button"
                                                class="btn btn-sm btn-{{ $a->status === 'active' ? 'warning' : 'success' }} js-toggle-announcement"
                                                data-id="{{ $a->id }}"
                                                data-url="{{ route('admin.announcements.toggle', $a->id) }}"
                                                title="{{ __('Toggle status') }}">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                    @endif
                                    @if (checkAdminHasPermission('announcement.delete'))
                                        <button type="button"
                                                class="btn btn-sm btn-danger js-delete-announcement"
                                                data-id="{{ $a->id }}"
                                                data-url="{{ route('admin.announcements.destroy', $a->id) }}"
                                                title="{{ __('Delete') }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No announcements found.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($announcements->hasPages())
                <div class="card-footer">{{ $announcements->links() }}</div>
            @endif
        </div>
    </section>
</div>

<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    document.querySelectorAll('.js-toggle-announcement').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('{{ __('Toggle status?') }}')) return;
            try {
                const res = await fetch(btn.dataset.url, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('HTTP '+res.status);
                const data = await res.json();
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert(data.message || 'Failed');
                }
            } catch (e) {
                alert('Network error: ' + e.message);
            }
        });
    });

    document.querySelectorAll('.js-delete-announcement').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('{{ __('Delete this announcement?') }}')) return;
            try {
                const res = await fetch(btn.dataset.url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('HTTP '+res.status);
                const data = await res.json();
                if (data.status === 'success') {
                    document.getElementById('announcement-row-' + btn.dataset.id)?.remove();
                } else {
                    alert(data.message || 'Failed');
                }
            } catch (e) {
                alert('Network error: ' + e.message);
            }
        });
    });
})();
</script>
@endsection
