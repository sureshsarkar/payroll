@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard__content-wrap mt-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <a href="{{ route('student.announcements.index') }}" class="btn btn-light btn-sm">
            <i class="fas fa-arrow-left"></i> {{ __('Back to announcements') }}
        </a>
    </div>

    <div style="background:#fff; border-radius:12px; padding:24px;">
        {{-- Audience + status row --}}
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
            @if ($announcement->is_pinned)
                <span style="background:#fffbeb; color:#92400e; padding:3px 10px; border-radius:999px;
                             font-size:11px; font-weight:600;">
                    <i class="fas fa-thumbtack"></i> {{ __('Pinned') }}
                </span>
            @endif
            @if ($announcement->audience_type === 'all_students')
                <span style="background:#eef1ff; color:#10b981; padding:3px 10px; border-radius:999px;
                             font-size:11px; font-weight:600;">
                    🌐 {{ __('All Students') }}
                </span>
            @else
                <span style="background:#ecfdf5; color:#047857; padding:3px 10px; border-radius:999px;
                             font-size:11px; font-weight:600;">
                    📚 {{ __('Batch announcement') }}
                </span>
            @endif
        </div>

        {{-- Title --}}
        <h2 style="font-size:22px; font-weight:700; color:#1c1a4a; margin-bottom:10px;">
            {{ $announcement->title }}
        </h2>

        {{-- Sender + when --}}
        <div style="font-size:12px; color:#6b7280; margin-bottom:18px;
                    padding-bottom:14px; border-bottom:1px solid #f3f4f6;">
            <i class="fas fa-user"></i>
            <strong style="color:#374151;">{{ $announcement->instructor?->name ?? __('System') }}</strong>
            @if ($announcement->course)
                · <i class="fas fa-graduation-cap"></i> {{ $announcement->course->title }}
            @endif
            · <i class="fas fa-clock"></i> {{ optional($announcement->sent_at)->format('M j, Y · g:i A') }}
        </div>

        {{-- Body --}}
        <div style="font-size:14px; line-height:1.7; color:#374151;">
            {!! clean($announcement->announcement) !!}
        </div>

        {{-- Attachments --}}
        @if ($announcement->attachments && $announcement->attachments->count() > 0)
            <div style="margin-top:24px; padding-top:18px; border-top:1px solid #f3f4f6;">
                <div style="font-size:11px; text-transform:uppercase; color:#6b7280; font-weight:600;
                            letter-spacing:.4px; margin-bottom:10px;">
                    <i class="fas fa-paperclip"></i> {{ __('Attachments') }}
                    <span class="text-muted">({{ $announcement->attachments->count() }})</span>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    @foreach ($announcement->attachments as $att)
                        <a href="{{ route('announcements.attachments.download', $att->id) }}"
                           style="display:inline-flex; align-items:center; gap:8px;
                                  background:#fafbfc; border:1px solid #e5e7eb; border-radius:8px;
                                  padding:8px 12px; text-decoration:none; color:#374151;
                                  font-size:12px;">
                            <i class="fas fa-file" style="color:#10b981;"></i>
                            <span>{{ \Illuminate\Support\Str::limit($att->filename, 28) }}</span>
                            <span class="text-muted" style="font-size:10px;">
                                {{ number_format(($att->size_bytes ?? 0) / 1024, 1) }} KB
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
