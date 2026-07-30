@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    {{-- Announcements — index. 2026-07-04: rebuilt on the corp design system.
         Preserved: GET filter form (q/course_id/batch_id/status/date_from/date_to),
         the announcements table + row data, edit route, .delete-item (JS confirm),
         pagination ($announcements->links()), x-empty-table component, and the
         $courseList / $batchList / $announcements variables. --}}
    @include('frontend.instructor-dashboard.settings.partials._corporate')

    <div class="corp-page" id="announcementsIndex">
        <div class="corp-header">
            <div class="corp-header__title">
                <h4><i class="fas fa-bullhorn" style="color:var(--corp-brand);"></i> {{ __('Announcements') }}</h4>
                <p>{{ __('Keep your students informed. Announcements reach the batches you choose.') }}</p>
            </div>
            <div class="corp-header__actions">
                @if (checkPermissionView('announcements-create'))
                    <a href="{{ route('instructor.announcements.create') }}" class="btn-corp-primary">
                        <i class="fas fa-plus"></i> {{ __('Create Announcement') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- Filters --}}
        <div class="corp-form-card">
            <div class="corp-form-card__body" style="padding:14px 16px;">
                <form method="GET" style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                    <input type="text" name="q" class="corp-input" style="max-width:230px;height:38px;"
                           value="{{ request('q') }}" placeholder="{{ __('Search title / message') }}">
                    <select name="course_id" class="form-select corp-select" style="max-width:190px;height:38px;">
                        <option value="">{{ __('Any course') }}</option>
                        @foreach ($courseList ?? [] as $c)
                            <option value="{{ $c->id }}" {{ request('course_id') == $c->id ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($c->title, 30) }}</option>
                        @endforeach
                    </select>
                    <select name="batch_id" class="form-select corp-select" style="max-width:170px;height:38px;">
                        <option value="">{{ __('Any batch') }}</option>
                        @foreach ($batchList ?? [] as $b)
                            <option value="{{ $b->id }}" {{ request('batch_id') == $b->id ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($b->title, 24) }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="form-select corp-select" style="max-width:135px;height:38px;">
                        <option value="">{{ __('Any status') }}</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                    </select>
                    <input type="date" name="date_from" class="corp-input" style="max-width:150px;height:38px;" value="{{ request('date_from') }}" title="{{ __('From') }}">
                    <input type="date" name="date_to" class="corp-input" style="max-width:150px;height:38px;" value="{{ request('date_to') }}" title="{{ __('To') }}">
                    <button class="btn-corp-primary" style="height:38px;"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
                    @if (request()->hasAny(['q','course_id','batch_id','status','date_from','date_to']))
                        <a href="{{ route('instructor.announcements.index') }}" class="btn-corp-secondary" style="height:38px;">{{ __('Clear') }}</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="corp-form-card">
            <div class="corp-form-card__body" style="overflow-x:auto;">
                <table class="corp-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>{{ __('No') }}</th>
                            <th>{{ __('Course') }}</th>
                            <th>{{ __('Batch') }}</th>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Sent') }}</th>
                            <th style="text-align:right;">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($announcements as $key => $announcement)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ truncate(optional($announcement->course)->title ?? '—') }}</td>
                                <td>
                                    @php $batchList = $announcement->relationLoaded('batches') ? $announcement->batches : $announcement->batches()->get(); @endphp
                                    @if ($batchList && $batchList->count() === 1)
                                        <span class="corp-pill corp-pill--brand">{{ truncate($batchList->first()->title, 24) }}</span>
                                    @elseif ($batchList && $batchList->count() > 1)
                                        <span class="corp-pill corp-pill--brand" title="{{ $batchList->pluck('title')->join(', ') }}">{{ $batchList->count() }} {{ __('batches') }}</span>
                                    @elseif ($announcement->batch)
                                        <span class="corp-pill corp-pill--brand">{{ truncate($announcement->batch->title, 24) }}</span>
                                    @else
                                        <span style="color:var(--corp-muted);font-size:12px;">{{ __('Course-wide') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($announcement->is_pinned)
                                        <i class="fas fa-thumbtack" style="color:#f59e0b;" title="{{ __('Pinned') }}"></i>
                                    @endif
                                    <span style="font-weight:600;">{{ truncate($announcement->title) }}</span>
                                    @if ($announcement->delivered_at === null && $announcement->scheduled_at && $announcement->scheduled_at->isFuture())
                                        <span class="corp-pill" style="background:#f1f5f9;color:#64748b;margin-left:4px;"><i class="fas fa-clock"></i> {{ __('Scheduled') }} {{ $announcement->scheduled_at->diffForHumans() }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if (($announcement->status ?? 'active') === 'active')
                                        <span class="corp-pill" style="background:#ecfdf5;color:#0f766e;">{{ __('Active') }}</span>
                                    @else
                                        <span class="corp-pill" style="background:#f1f5f9;color:#64748b;">{{ __('Inactive') }}</span>
                                    @endif
                                    <small style="color:var(--corp-muted);margin-left:4px;" title="{{ __('Number of students who opened this announcement') }}"><i class="fas fa-eye"></i> {{ $announcement->readers_count ?? 0 }}</small>
                                </td>
                                <td style="color:var(--corp-muted);">{{ formatDate($announcement->sent_at ?? $announcement->created_at) }}</td>
                                <td style="text-align:right;white-space:nowrap;">
                                    @if (checkPermissionView('announcements-edit'))
                                        <a href="{{ route('instructor.announcements.edit', $announcement->id) }}" class="btn-corp-secondary btn-corp-sm" aria-label="{{ __('Edit') }}"><i class="far fa-edit"></i></a>
                                    @endif
                                    @if (checkPermissionView('announcements-delete'))
                                        <a href="{{ route('instructor.announcements.destroy', $announcement->id) }}" class="btn-corp-secondary btn-corp-sm delete-item" style="color:#dc2626;" aria-label="{{ __('Delete') }}"><i class="fas fa-trash-alt"></i></a>
                                    @endif
                                    @if (! checkPermissionView('announcements-edit') && ! checkPermissionView('announcements-delete'))
                                        <span style="color:var(--corp-subtle);font-size:12px;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-empty-table :name="__('Announcement')"
                                route="instructor.announcements.create"
                                create="yes"
                                :colspan="7"
                                :message="__('No announcements yet. Create your first to keep students informed.')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (method_exists($announcements, 'links') && $announcements->hasPages())
            <div class="mt-3 d-flex justify-content-center">{{ $announcements->links() }}</div>
        @endif
    </div>
@endsection
