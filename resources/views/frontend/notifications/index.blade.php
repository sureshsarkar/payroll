@php
    $u = Auth::guard('web')->user();
    $isCoach = $u && $u->role === 'instructor';
    $dashLayout = $isCoach
        ? 'frontend.instructor-dashboard.layouts.master'
        : 'frontend.student-dashboard.layouts.master';

    // Bucket page items by relative date for sticky group headers.
    $buckets = [
        'today'     => ['label' => __('Today'),      'items' => []],
        'yesterday' => ['label' => __('Yesterday'),  'items' => []],
        'week'      => ['label' => __('This Week'),  'items' => []],
        'earlier'   => ['label' => __('Earlier'),    'items' => []],
    ];
    foreach ($notifications as $n) {
        $created = $n->created_at;
        if (!$created) { $buckets['earlier']['items'][] = $n; continue; }
        if ($created->isToday())              $buckets['today']['items'][]     = $n;
        elseif ($created->isYesterday())      $buckets['yesterday']['items'][] = $n;
        elseif ($created->isCurrentWeek())    $buckets['week']['items'][]      = $n;
        else                                  $buckets['earlier']['items'][]   = $n;
    }
@endphp
@extends($dashLayout)

@section('dashboard-contents')
<div class="mbs-notif-page">
    {{-- ===== HERO ===== --}}
    <div class="mbs-notif-hero">
        <div class="mbs-notif-hero__title">
            <h1>
                <i class="fas fa-bell"></i> {{ __('Notifications') }}
                @if ($unreadCount > 0)
                    <span class="mbs-notif-hero__pill">{{ $unreadCount }} {{ __('new') }}</span>
                @endif
            </h1>
            <p>{{ __('Stay on top of what is happening across your courses, sales and account.') }}</p>
        </div>
        <div class="mbs-notif-hero__actions">
            <a href="{{ route('notifications.preferences.index') }}" class="mbs-notif-btn mbs-notif-btn--ghost" title="{{ __('Notification preferences') }}">
                <i class="fas fa-cog"></i> {{ __('Preferences') }}
            </a>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.mark-all-read') }}" id="mbs-mark-all-form" style="margin:0;">
                    @csrf
                    <button type="submit" class="mbs-notif-btn mbs-notif-btn--primary">
                        <i class="fas fa-check-double"></i> {{ __('Mark all read') }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- ===== STAT CARDS ===== --}}
    <div class="mbs-notif-stats">
        <div class="mbs-notif-stat">
            <div class="mbs-notif-stat__icon" style="background: linear-gradient(135deg,#10b981,#7a73ff);">
                <i class="fas fa-inbox"></i>
            </div>
            <div>
                <div class="mbs-notif-stat__num">{{ $allCount }}</div>
                <div class="mbs-notif-stat__lbl">{{ __('Total') }}</div>
            </div>
        </div>
        <div class="mbs-notif-stat">
            <div class="mbs-notif-stat__icon" style="background: linear-gradient(135deg,#ef4444,#f97316);">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div>
                <div class="mbs-notif-stat__num">{{ $unreadCount }}</div>
                <div class="mbs-notif-stat__lbl">{{ __('Unread') }}</div>
            </div>
        </div>
        <div class="mbs-notif-stat">
            <div class="mbs-notif-stat__icon" style="background: linear-gradient(135deg,#10b981,#34d399);">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <div class="mbs-notif-stat__num">{{ $todayCount }}</div>
                <div class="mbs-notif-stat__lbl">{{ __('Today') }}</div>
            </div>
        </div>
        <div class="mbs-notif-stat">
            <div class="mbs-notif-stat__icon" style="background: linear-gradient(135deg,#3b82f6,#60a5fa);">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div>
                <div class="mbs-notif-stat__num">{{ $weekCount }}</div>
                <div class="mbs-notif-stat__lbl">{{ __('This week') }}</div>
            </div>
        </div>
    </div>

    {{-- ===== TOOLBAR (filter tabs + search) ===== --}}
    <div class="mbs-notif-toolbar">
        <div class="mbs-notif-tabs" role="tablist">
            <a href="{{ route('notifications.index', array_filter(['q' => $q])) }}"
               class="mbs-notif-tab {{ $filter === 'all' ? 'is-active' : '' }}">
                {{ __('All') }} <span class="mbs-notif-tab__count">{{ $allCount }}</span>
            </a>
            <a href="{{ route('notifications.index', array_filter(['filter' => 'unread', 'q' => $q])) }}"
               class="mbs-notif-tab {{ $filter === 'unread' ? 'is-active' : '' }}">
                {{ __('Unread') }} <span class="mbs-notif-tab__count">{{ $unreadCount }}</span>
            </a>
            <a href="{{ route('notifications.index', array_filter(['filter' => 'read', 'q' => $q])) }}"
               class="mbs-notif-tab {{ $filter === 'read' ? 'is-active' : '' }}">
                {{ __('Read') }} <span class="mbs-notif-tab__count">{{ $readCount }}</span>
            </a>
        </div>

        <form method="GET" action="{{ route('notifications.index') }}" class="mbs-notif-search" role="search">
            @if ($filter !== 'all')
                <input type="hidden" name="filter" value="{{ $filter }}">
            @endif
            <i class="fas fa-search mbs-notif-search__icon"></i>
            <input type="text" name="q" value="{{ $q }}"
                   id="mbs-notif-search-input"
                   placeholder="{{ __('Search notifications…') }}"
                   autocomplete="off">
            @if ($q !== '')
                <a href="{{ route('notifications.index', array_filter(['filter' => $filter !== 'all' ? $filter : null])) }}"
                   class="mbs-notif-search__clear" title="{{ __('Clear') }}">
                    <i class="fas fa-times"></i>
                </a>
            @endif
        </form>
    </div>

    {{-- ===== LIST ===== --}}
    <div class="mbs-notif-list-wrap">
        @if ($notifications->count() === 0)
            {{-- Empty state — context-aware --}}
            <div class="mbs-notif-empty">
                <div class="mbs-notif-empty__art">
                    <i class="fas {{ $q !== '' ? 'fa-search' : ($filter === 'unread' ? 'fa-check-circle' : 'fa-bell-slash') }}"></i>
                </div>
                @if ($q !== '')
                    <h3>{{ __('No matches') }}</h3>
                    <p>{{ __('No notifications match') }} "<strong>{{ $q }}</strong>". {{ __('Try a different search.') }}</p>
                @elseif ($filter === 'unread')
                    <h3>{{ __('All caught up') }} 🎉</h3>
                    <p>{{ __('You have no unread notifications. Nice work staying on top of things.') }}</p>
                @elseif ($filter === 'read')
                    <h3>{{ __('Nothing here yet') }}</h3>
                    <p>{{ __('Notifications you have read will show up here.') }}</p>
                @else
                    <h3>{{ __('No notifications yet') }}</h3>
                    <p>{{ __('When something needs your attention you will see it here.') }}</p>
                @endif
            </div>
        @else
            @foreach ($buckets as $key => $bucket)
                @if (count($bucket['items']) > 0)
                    <div class="mbs-notif-group">
                        <div class="mbs-notif-group__hdr">
                            <span>{{ $bucket['label'] }}</span>
                            <span class="mbs-notif-group__count">{{ count($bucket['items']) }}</span>
                        </div>
                        <div class="mbs-notif-group__body">
                            @foreach ($bucket['items'] as $n)
                                @php
                                    $data = $n->data;
                                    $unread = $n->read_at === null;
                                    $href = $data['url'] ?? '#';
                                    $icon = $data['icon'] ?? 'fa-bell';
                                    $color = $data['iconColor'] ?? '#10b981';
                                @endphp
                                <div class="mbs-notif-card {{ $unread ? 'is-unread' : '' }}" data-id="{{ $n->id }}">
                                    @if ($unread)
                                        <span class="mbs-notif-card__dot" aria-hidden="true"></span>
                                    @endif
                                    <a href="{{ $href }}" class="mbs-notif-card__main mbs-notif-row" data-id="{{ $n->id }}">
                                        <div class="mbs-notif-card__icon" style="background-color: {{ $color }};">
                                            <i class="fas {{ $icon }}"></i>
                                        </div>
                                        <div class="mbs-notif-card__body">
                                            <div class="mbs-notif-card__title">{{ $data['title'] ?? __('Notification') }}</div>
                                            @if (!empty($data['body']))
                                                <div class="mbs-notif-card__text">{{ $data['body'] }}</div>
                                            @endif
                                            <div class="mbs-notif-card__meta">
                                                <i class="far fa-clock"></i> {{ $n->created_at?->diffForHumans() }}
                                                @if (!empty($data['type_label']))
                                                    <span class="mbs-notif-card__chip">{{ $data['type_label'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                    <div class="mbs-notif-card__actions">
                                        @if ($unread)
                                            <button type="button" class="mbs-notif-iconbtn js-mark-read"
                                                    data-id="{{ $n->id }}" title="{{ __('Mark as read') }}">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        @endif
                                        <button type="button" class="mbs-notif-iconbtn js-delete"
                                                data-id="{{ $n->id }}" title="{{ __('Delete') }}">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </div>

    @if ($notifications->hasPages())
        <div class="mbs-notif-pager">
            {{ $notifications->links() }}
        </div>
    @endif
</div>

<style>
    .mbs-notif-page {
        padding: 12px 4px 32px;
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: #1c1a4a;
    }

    /* ===== Hero ===== */
    .mbs-notif-hero {
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;
        gap:16px;
        background: linear-gradient(135deg, #ffffff 0%, #f5f3ff 100%);
        border:1px solid #eef0f3;
        border-radius:16px;
        padding:22px 24px;
        margin-bottom:18px;
    }
    .mbs-notif-hero__title h1 {
        margin:0; font-size:22px; font-weight:700; color:#1c1a4a;
        display:flex; align-items:center; gap:10px;
    }
    .mbs-notif-hero__title h1 i { color:#10b981; font-size:20px; }
    .mbs-notif-hero__title p { margin:6px 0 0; color:#6b7280; font-size:13px; max-width:560px; }
    .mbs-notif-hero__pill {
        font-size:11px; font-weight:700;
        background:#ef4444; color:#fff;
        padding:3px 10px; border-radius:999px;
        margin-left:6px; letter-spacing:0.3px;
        text-transform:uppercase;
    }
    .mbs-notif-hero__actions { display:flex; gap:10px; flex-wrap:wrap; }

    .mbs-notif-btn {
        display:inline-flex; align-items:center; gap:8px;
        padding:10px 16px; border-radius:10px;
        font-size:13px; font-weight:600;
        border:1px solid transparent; cursor:pointer;
        text-decoration:none;
        transition:transform .15s, box-shadow .15s, background .15s, color .15s, border-color .15s;
    }
    .mbs-notif-btn:hover { transform: translateY(-1px); }
    .mbs-notif-btn--primary { background: linear-gradient(135deg,#10b981,#7a73ff); color:#fff; box-shadow: 0 4px 12px rgba(16, 185, 129,0.3); }
    .mbs-notif-btn--primary:hover { box-shadow: 0 6px 16px rgba(16, 185, 129,0.4); color:#fff; }
    .mbs-notif-btn--ghost { background:#fff; color:#374151; border-color:#e5e7eb; }
    .mbs-notif-btn--ghost:hover { background:#f9fafb; color:#1c1a4a; border-color:#d1d5db; }

    /* ===== Stats ===== */
    .mbs-notif-stats {
        display:grid;
        grid-template-columns: repeat(4, 1fr);
        gap:12px;
        margin-bottom:18px;
    }
    .mbs-notif-stat {
        background:#fff;
        border:1px solid #eef0f3;
        border-radius:14px;
        padding:16px;
        display:flex; align-items:center; gap:14px;
        transition: transform .15s, box-shadow .15s;
    }
    .mbs-notif-stat:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,23,42,0.06); }
    .mbs-notif-stat__icon {
        width:42px; height:42px; border-radius:12px;
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:16px; flex-shrink:0;
    }
    .mbs-notif-stat__num { font-size:22px; font-weight:700; color:#1c1a4a; line-height:1; }
    .mbs-notif-stat__lbl { font-size:12px; color:#6b7280; margin-top:4px; }

    @media (max-width: 900px) { .mbs-notif-stats { grid-template-columns: repeat(2, 1fr); } }

    /* ===== Toolbar ===== */
    .mbs-notif-toolbar {
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;
        gap:12px;
        background:#fff; border:1px solid #eef0f3; border-radius:14px;
        padding:10px 12px; margin-bottom:14px;
        position: sticky; top:64px; z-index:10;
    }
    .mbs-notif-tabs { display:flex; gap:4px; flex-wrap:wrap; }
    .mbs-notif-tab {
        display:inline-flex; align-items:center; gap:6px;
        padding:8px 14px; border-radius:10px;
        font-size:13px; font-weight:600; color:#6b7280;
        text-decoration:none;
        transition: background .15s, color .15s;
    }
    .mbs-notif-tab:hover { background:#f3f4f6; color:#1c1a4a; }
    .mbs-notif-tab.is-active { background:#ecfdf5; color:#10b981; }
    .mbs-notif-tab__count {
        background:#e5e7eb; color:#6b7280;
        font-size:11px; font-weight:700;
        padding:1px 8px; border-radius:999px;
    }
    .mbs-notif-tab.is-active .mbs-notif-tab__count { background:#10b981; color:#fff; }

    .mbs-notif-search {
        position:relative;
        margin:0;
        flex: 0 1 280px;
    }
    .mbs-notif-search__icon {
        position:absolute; left:12px; top:50%; transform:translateY(-50%);
        color:#9ca3af; font-size:12px;
    }
    .mbs-notif-search input {
        width:100%;
        padding: 9px 32px 9px 34px;
        background:#f9fafb; border:1px solid #e5e7eb;
        border-radius:10px;
        font-size:13px;
        outline:none;
        transition: background .15s, border-color .15s;
    }
    .mbs-notif-search input:focus {
        background:#fff;
        border-color:#10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129,0.12);
    }
    .mbs-notif-search__clear {
        position:absolute; right:8px; top:50%; transform:translateY(-50%);
        width:22px; height:22px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        color:#9ca3af; text-decoration:none; font-size:11px;
    }
    .mbs-notif-search__clear:hover { background:#f3f4f6; color:#1c1a4a; }

    /* ===== Groups + cards ===== */
    .mbs-notif-list-wrap { display:flex; flex-direction:column; gap:14px; }
    .mbs-notif-group { }
    .mbs-notif-group__hdr {
        display:flex; align-items:center; gap:8px;
        font-size:11px; font-weight:700; text-transform:uppercase;
        letter-spacing:0.6px; color:#9ca3af;
        padding: 6px 4px;
    }
    .mbs-notif-group__count {
        background:#f3f4f6; color:#6b7280;
        padding:1px 7px; border-radius:999px;
        font-size:10px;
    }
    .mbs-notif-group__body {
        background:#fff; border:1px solid #eef0f3;
        border-radius:14px; overflow:hidden;
    }

    .mbs-notif-card {
        position:relative;
        display:flex; align-items:stretch;
        border-bottom:1px solid #f3f4f6;
        transition: background .15s;
    }
    .mbs-notif-card:last-child { border-bottom:0; }
    .mbs-notif-card:hover { background:#fafbfc; }
    .mbs-notif-card.is-unread { background: linear-gradient(90deg, rgba(16, 185, 129,0.06) 0%, transparent 30%); }
    .mbs-notif-card.is-unread:hover { background: linear-gradient(90deg, rgba(16, 185, 129,0.10) 0%, #fafbfc 30%); }
    .mbs-notif-card.is-removing {
        opacity:0; transform: translateX(40px);
        max-height:0; padding:0; border-bottom:0;
        transition: opacity .25s, transform .25s, max-height .3s 0.05s, padding .3s 0.05s;
    }

    .mbs-notif-card__dot {
        position:absolute; left:6px; top:50%; transform:translateY(-50%);
        width:6px; height:6px; border-radius:50%;
        background:#10b981;
        box-shadow: 0 0 0 4px rgba(16, 185, 129,0.18);
    }

    .mbs-notif-card__main {
        flex:1; min-width:0;
        display:flex; gap:14px; align-items:flex-start;
        padding:16px 18px 16px 22px;
        text-decoration:none; color:inherit;
    }
    .mbs-notif-card__icon {
        flex-shrink:0;
        width:42px; height:42px; border-radius:12px;
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:15px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }
    .mbs-notif-card__body { flex:1; min-width:0; }
    .mbs-notif-card__title {
        font-size:14px; font-weight:600; color:#1c1a4a;
        margin-bottom:3px; line-height:1.4;
    }
    .mbs-notif-card.is-unread .mbs-notif-card__title { font-weight:700; }
    .mbs-notif-card__text {
        font-size:13px; color:#6b7280; line-height:1.5; margin-bottom:6px;
        overflow:hidden;
        display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
    }
    .mbs-notif-card__meta {
        font-size:11px; color:#9ca3af;
        display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    }
    .mbs-notif-card__chip {
        background:#ecfdf5; color:#10b981;
        padding:1px 8px; border-radius:999px;
        font-weight:600; font-size:10px; text-transform:uppercase; letter-spacing:0.4px;
    }

    .mbs-notif-card__actions {
        display:flex; align-items:center; gap:4px;
        padding:0 14px;
        opacity:0;
        transition: opacity .15s;
    }
    .mbs-notif-card:hover .mbs-notif-card__actions { opacity:1; }
    @media (max-width: 600px) { .mbs-notif-card__actions { opacity:1; } }

    .mbs-notif-iconbtn {
        width:32px; height:32px; border-radius:8px;
        background:transparent; border:1px solid transparent;
        color:#6b7280; font-size:12px;
        cursor:pointer;
        display:inline-flex; align-items:center; justify-content:center;
        transition: background .15s, color .15s, border-color .15s;
    }
    .mbs-notif-iconbtn:hover { background:#fff; border-color:#e5e7eb; color:#1c1a4a; }
    .mbs-notif-iconbtn.js-delete:hover { background:#fee2e2; color:#dc2626; border-color:#fecaca; }

    /* ===== Empty state ===== */
    .mbs-notif-empty {
        background:#fff; border:1px dashed #e5e7eb; border-radius:14px;
        padding:60px 24px; text-align:center;
    }
    .mbs-notif-empty__art {
        width:80px; height:80px; margin: 0 auto 16px;
        background: linear-gradient(135deg,#ecfdf5,#f5f3ff);
        border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        color:#10b981; font-size:30px;
    }
    .mbs-notif-empty h3 { margin:0 0 6px; color:#1c1a4a; font-size:16px; font-weight:700; }
    .mbs-notif-empty p { margin:0; color:#6b7280; font-size:13px; max-width:380px; margin-left:auto; margin-right:auto; }

    /* ===== Pager ===== */
    .mbs-notif-pager { margin-top:18px; display:flex; justify-content:center; }
    .mbs-notif-pager nav { display:flex; }

    /* ===== Dark mode ===== */
    [data-theme="dark"] .mbs-notif-page { color:#e5e7eb; }
    [data-theme="dark"] .mbs-notif-hero,
    [data-theme="dark"] .mbs-notif-stat,
    [data-theme="dark"] .mbs-notif-toolbar,
    [data-theme="dark"] .mbs-notif-group__body,
    [data-theme="dark"] .mbs-notif-empty,
    [data-theme="dark"] .mbs-notif-btn--ghost { background:#1f2937; border-color:#374151; color:#e5e7eb; }
    [data-theme="dark"] .mbs-notif-hero { background: linear-gradient(135deg,#1f2937 0%, #312e81 100%); }
    [data-theme="dark"] .mbs-notif-hero__title h1,
    [data-theme="dark"] .mbs-notif-stat__num,
    [data-theme="dark"] .mbs-notif-card__title,
    [data-theme="dark"] .mbs-notif-empty h3 { color:#f3f4f6; }
    [data-theme="dark"] .mbs-notif-search input { background:#111827; color:#e5e7eb; border-color:#374151; }
    [data-theme="dark"] .mbs-notif-tab.is-active { background: rgba(16, 185, 129,0.2); }
    [data-theme="dark"] .mbs-notif-card { border-color:#374151; }
    [data-theme="dark"] .mbs-notif-card:hover { background:#111827; }
</style>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
    const MARK_READ_URL = "{{ url('notifications') }}/{id}/read";
    const DESTROY_URL   = "{{ url('notifications') }}/{id}";

    function fadeOutAndRemove(card) {
        card.classList.add('is-removing');
        setTimeout(() => {
            const groupBody = card.parentElement;
            card.remove();
            // If the group is empty, hide its header too.
            if (groupBody && groupBody.children.length === 0) {
                groupBody.parentElement?.remove();
            }
            // If list is now empty, soft-reload to show the empty-state view.
            if (!document.querySelector('.mbs-notif-card')) {
                location.reload();
            }
        }, 300);
    }

    // Per-row "Mark as read" check button
    document.querySelectorAll('.js-mark-read').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const id = btn.dataset.id;
            if (!id) return;
            fetch(MARK_READ_URL.replace('{id}', encodeURIComponent(id)), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            }).then(() => {
                const card = btn.closest('.mbs-notif-card');
                if (card) {
                    card.classList.remove('is-unread');
                    card.querySelector('.mbs-notif-card__dot')?.remove();
                    btn.remove();
                }
            }).catch(() => {});
        });
    });

    // Per-row delete button
    document.querySelectorAll('.js-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const id = btn.dataset.id;
            if (!id) return;
            fetch(DESTROY_URL.replace('{id}', encodeURIComponent(id)), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            }).then(() => {
                const card = btn.closest('.mbs-notif-card');
                if (card) fadeOutAndRemove(card);
            }).catch(() => {});
        });
    });

    // Clicking the row body marks it read on the way out.
    document.querySelectorAll('.mbs-notif-row').forEach(el => {
        el.addEventListener('click', () => {
            const id = el.dataset.id;
            if (!id) return;
            fetch(MARK_READ_URL.replace('{id}', encodeURIComponent(id)), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            }).catch(() => {});
        });
    });

    // Keyboard shortcuts: "/" focus search, "r" mark all read
    document.addEventListener('keydown', (e) => {
        if (e.target.matches('input, textarea, [contenteditable]')) return;
        if (e.key === '/') {
            e.preventDefault();
            document.getElementById('mbs-notif-search-input')?.focus();
        } else if (e.key === 'r' && !e.ctrlKey && !e.metaKey) {
            document.getElementById('mbs-mark-all-form')?.requestSubmit?.();
        }
    });
})();
</script>
@endsection
