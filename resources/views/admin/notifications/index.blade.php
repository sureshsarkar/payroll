@extends('admin.master_layout')

@section('title')
    <title>{{ __('Notifications') }}</title>
@endsection

@push('css')
{{-- Round-4 cleanup (2026-05-12) — used to have ~15 inline `style=` attributes
     across the notification rows. Extracted to named classes for parity with
     the rest of the admin polish. --}}
<style>
    .nf-row { display:flex; align-items:flex-start; padding:14px 16px; border-bottom:1px solid #f1f5f9; text-decoration:none; color:inherit; transition:background .15s; }
    .nf-row:hover { background:#f8fafc; }
    .nf-row--unread { background:#f0f7ff; }
    .nf-row__icon { flex-shrink:0; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; margin-right:14px; }
    .nf-row__body { flex:1; min-width:0; }
    .nf-row__title { font-size:14px; font-weight:600; color:#1c1a4a; }
    .nf-row__desc  { font-size:13px; color:#6b7280; margin-top:2px; }
    .nf-row__time  { font-size:11px; color:#9ca3af; margin-top:4px; }
    .nf-badge-new  { display:inline-block; background:#5751e1; color:#fff; padding:1px 6px; border-radius:8px; font-size:9px; font-weight:700; letter-spacing:.5px; margin-left:4px; vertical-align:middle; }
    .nf-empty      { text-align:center; color:#94a3b8; padding:60px 20px; }
    .nf-empty i    { font-size:48px; opacity:.4; display:block; margin-bottom:10px; }
</style>
@endpush

@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-bell" aria-hidden="true"></i> {{ __('Notifications') }}</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a></div>
                <div class="breadcrumb-item">{{ __('Notifications') }}</div>
            </div>
        </div>

        <div class="section-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="text-muted mb-0">
                    {{ $notifications->total() }} {{ __('total') }} ·
                    {{ Auth::guard('admin')->user()->unreadNotifications()->count() }} {{ __('unread') }}
                </p>
                @if (Auth::guard('admin')->user()->unreadNotifications()->count() > 0)
                    <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-check-double" aria-hidden="true"></i> {{ __('Mark all as read') }}
                        </button>
                    </form>
                @endif
            </div>

            <div class="card">
                <div class="card-body p-0">
                    @forelse ($notifications as $n)
                        @php
                            $data     = $n->data;
                            $unread   = $n->read_at === null;
                            $iconClr  = $data['iconColor'] ?? '#5751e1';
                        @endphp
                        <a href="{{ $data['url'] ?? '#' }}"
                           data-id="{{ $n->id }}"
                           class="nf-row mbs-admin-notif-row {{ $unread ? 'nf-row--unread' : '' }}"
                           aria-label="{{ ($unread ? __('Unread notification') . ': ' : '') }}{{ $data['title'] ?? '' }}">
                            <div class="nf-row__icon" style="background: {{ $iconClr }};">
                                <i class="fa {{ $data['icon'] ?? 'fa-bell' }}" aria-hidden="true"></i>
                            </div>
                            <div class="nf-row__body">
                                <div class="nf-row__title">
                                    {{ $data['title'] ?? '' }}
                                    @if ($unread)
                                        <span class="nf-badge-new">{{ __('NEW') }}</span>
                                    @endif
                                </div>
                                <div class="nf-row__desc">{{ $data['body'] ?? '' }}</div>
                                <div class="nf-row__time">{{ $n->created_at?->diffForHumans() }}</div>
                            </div>
                        </a>
                    @empty
                        <div class="nf-empty">
                            <i class="fa fa-bell-slash" aria-hidden="true"></i>
                            <p class="mb-0">{{ __('No notifications yet') }}</p>
                        </div>
                    @endforelse
                </div>
                @if ($notifications->hasPages())
                    <div class="card-footer">{{ $notifications->links() }}</div>
                @endif
            </div>
        </div>
    </section>
</div>

<script>
document.querySelectorAll('.mbs-admin-notif-row').forEach(el => {
    el.addEventListener('click', () => {
        const id = el.dataset.id;
        if (!id) return;
        fetch("{{ url('admin/notifications') }}/" + encodeURIComponent(id) + "/read", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json'
            }
        }).catch(() => {});
    });
});
</script>
@endsection
