@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard__content-wrap mt-3">
    <div class="dashboard__content-title d-flex justify-content-between align-items-center flex-wrap">
        <h4 class="title"><i class="fas fa-bullhorn" style="color:#10b981;"></i> {{ __('Announcements') }}</h4>
        <small class="text-muted">{{ $totalCount }} {{ __('total') }} · {{ $unreadCount }} {{ __('unread') }}</small>
    </div>

    {{-- Filter pills ─────────────────────────────────────────── --}}
    <div class="d-flex gap-2 mb-3 mt-2 flex-wrap">
        @php $isUnread = request()->boolean('unread'); @endphp
        <a href="{{ route('student.announcements.index') }}"
           class="btn btn-sm @if (!$isUnread) btn-primary @else btn-light @endif"
           style="border-radius:999px; padding:6px 16px; font-size:12px;">
            {{ __('All') }} <span class="badge bg-light text-dark">{{ $totalCount }}</span>
        </a>
        <a href="{{ route('student.announcements.index', ['unread' => 1]) }}"
           class="btn btn-sm @if ($isUnread) btn-primary @else btn-light @endif"
           style="border-radius:999px; padding:6px 16px; font-size:12px;">
            {{ __('Unread') }} <span class="badge bg-light text-dark">{{ $unreadCount }}</span>
        </a>
    </div>

    {{-- List ─────────────────────────────────────────────────── --}}
    <div style="background:#fff; border-radius:10px; padding:8px;">
        @forelse ($announcements as $a)
            @php
                $isUnread = !$a->is_read_by_me;
                $audienceLabel = $a->audience_type === 'all_students' ? __('All Students') : __('Batch');
                $audienceColor = $a->audience_type === 'all_students' ? '#10b981' : '#10b981';
            @endphp
            <a href="{{ route('student.announcements.show', $a->id) }}"
               style="display:block; padding:14px 16px; border-bottom:1px solid #f3f4f6;
                      text-decoration:none; color:inherit;
                      background:{{ $isUnread ? '#f3f2ff' : '#fff' }};
                      border-left:4px solid {{ $isUnread ? '#10b981' : 'transparent' }};">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                    <div style="flex:1;">
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px;">
                            @if ($a->is_pinned)
                                <i class="fas fa-thumbtack" style="color:#f59e0b; font-size:11px;"></i>
                            @endif
                            @if ($isUnread)
                                <span style="background:#10b981; color:#fff; padding:1px 7px;
                                             border-radius:999px; font-size:9px; font-weight:700;">{{ __('NEW') }}</span>
                            @endif
                            <span style="background:rgba({{ $audienceColor === '#10b981' ? '99,102,241' : '16,185,129' }}, 0.12);
                                         color:{{ $audienceColor }};
                                         padding:1px 8px; border-radius:999px;
                                         font-size:10px; font-weight:600;">{{ $audienceLabel }}</span>
                        </div>
                        <div style="font-weight:{{ $isUnread ? '700' : '500' }}; color:#1f2937; font-size:14px;">
                            {{ \Illuminate\Support\Str::limit($a->title, 90) }}
                        </div>
                        <div style="font-size:12px; color:#6b7280; margin-top:4px;
                                    overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:600px;">
                            {{ \Illuminate\Support\Str::limit(strip_tags($a->announcement), 110) }}
                        </div>
                        <div style="font-size:11px; color:#9ca3af; margin-top:6px;">
                            <i class="fas fa-user"></i> {{ $a->instructor?->name ?? __('System') }}
                            @if ($a->course)
                                · <i class="fas fa-graduation-cap"></i> {{ \Illuminate\Support\Str::limit($a->course->title, 30) }}
                            @endif
                            · <i class="fas fa-clock"></i> {{ optional($a->sent_at)->diffForHumans() }}
                            @if ($a->attachments && $a->attachments->count())
                                · <i class="fas fa-paperclip"></i> {{ $a->attachments->count() }}
                            @endif
                        </div>
                    </div>
                    <i class="fas fa-chevron-right" style="color:#d1d5db; font-size:12px; margin-top:6px;"></i>
                </div>
            </a>
        @empty
            <div class="text-center text-muted py-5">
                <i class="fas fa-bullhorn fa-2x mb-2" style="color:#d1d5db;"></i>
                <div>{{ $isUnread ? __('No unread announcements.') : __('No announcements yet.') }}</div>
                <small>{{ __('When your coach or the admin sends one, it will appear here.') }}</small>
            </div>
        @endforelse

        @if ($announcements->hasPages())
            <div class="mt-3 px-2">{{ $announcements->links() }}</div>
        @endif
    </div>
</div>
@endsection
