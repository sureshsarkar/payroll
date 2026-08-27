{{--
    Modern dashboard topbar — used on both /student/* and /instructor/* pages.
    Matches the design the user requested: hamburger | search | + | share | tasks | avatar | clock | bell.
--}}
@auth('web')
{{-- Browser push notification bootstrap. The push.js script reads
     window.MBS_VAPID_PUBLIC and subscribes the user when they click the bell. --}}
@if (config('webpush.vapid.public_key'))
    <script>
        window.MBS_VAPID_PUBLIC = @json(config('webpush.vapid.public_key'));
        window.MBS_PUSH_ENABLED = true;
    </script>
    <script src="{{ asset('global/push/push.js') }}" defer></script>
@endif
@php
    $u = Auth::guard('web')->user();
    $isCoach = $u->role === 'instructor';
    // LMS removal phase 2 (2026-08-27) — these pointed at the course/order
    // dashboards, which no longer exist. HR & employee surfaces now.
    $dashRoute    = $isCoach ? 'hr.overview' : 'employee.overview';
    $profileRoute = $isCoach ? 'instructor.setting.index' : 'student.setting.index';
    $tasksRoute   = $isCoach ? 'hr.leave.index' : 'employee.leave.index';

    // "Tasks" badge — pending items the user should act on. Was pending course
    // approvals / pending orders; now pending leave requests, which is the real
    // action queue in the HR product.
    $tasksCount = 0;
    try {
        if ($isCoach) {
            // HR: leave requests awaiting approval, scoped to this HR's team via
            // EmployeeProfile::teamUserIds() (never a raw coach_id fallback).
            $tasksCount = \Modules\Leave\app\Models\Leave::whereIn(
                    'user_id',
                    \Modules\HrEmployee\app\Models\EmployeeProfile::teamUserIds($u)
                )
                ->where('status', \Modules\Leave\app\Models\Leave::PENDING)
                ->count();
        } else {
            // Employees: their own leave requests still awaiting a decision.
            $tasksCount = \Modules\Leave\app\Models\Leave::where('user_id', $u->id)
                ->where('status', \Modules\Leave\app\Models\Leave::PENDING)
                ->count();
        }
    } catch (\Throwable $e) {
        $tasksCount = 0;
    }
@endphp



<style>
    /* === MBS Modern Dashboard Topbar === */
    .mbs-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 20px;
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        position: sticky;
        top: 0;
        z-index: 100;
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    .mbs-topbar__left, .mbs-topbar__right {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .mbs-topbar__right { gap: 8px; }

    .mbs-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: #f3f4f6;
        color: #4b5563;
        font-size: 14px;
        border: 1px solid transparent;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.15s, color 0.15s, border-color 0.15s;
        position: relative;
    }
    .mbs-icon-btn:hover { background: #e5e7eb; color: #111827; }
    .mbs-icon-btn--badge { /* slightly emphasized */ }

    .mbs-sidebar-toggle { background: transparent; }
    .mbs-sidebar-toggle:hover { background: #f3f4f6; }

    .mbs-add-wrap { position: relative; }
    .mbs-add-btn {
        width: 38px; height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4f8ef7, #2563eb);
        color: #fff;
        border: none;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        transition: transform 0.15s, box-shadow 0.15s;
    }
    .mbs-add-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); }
    .mbs-add-btn:active { transform: translateY(0); }

    .mbs-add-menu {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        min-width: 220px;
        padding: 8px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        list-style: none;
        margin: 0;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-4px);
        transition: opacity 0.15s, transform 0.15s, visibility 0.15s;
        z-index: 200;
    }
    .mbs-add-menu.is-open {
        opacity: 1; visibility: visible; transform: translateY(0);
    }
    .mbs-add-menu li { margin: 0; }
    .mbs-add-menu a {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 12px;
        text-decoration: none;
        color: #374151;
        font-size: 13px;
        border-radius: 8px;
        transition: background 0.1s;
    }
    .mbs-add-menu a:hover { background: #f3f4f6; color: #1f2937; }
    .mbs-add-menu a i { width: 18px; color: #10b981; font-size: 13px; }

    .mbs-topbar-badge {
        position: absolute;
        top: -3px; right: -3px;
        background: #ef4444;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        min-width: 18px;
        height: 18px;
        line-height: 18px;
        border-radius: 9px;
        padding: 0 5px;
        text-align: center;
        box-shadow: 0 0 0 2px #fff;
    }
    .mbs-topbar-badge--orange { background: #f59e0b; }

    .mbs-avatar {
        display: inline-block;
        width: 38px; height: 38px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #10b981;
        text-decoration: none;
        background: #10b981;
    }
    .mbs-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .mbs-avatar__fallback {
        display: flex; align-items: center; justify-content: center;
        width: 100%; height: 100%;
        color: #fff; font-weight: 600; font-size: 14px;
    }

    /* === Topbar-native bell dropdown === */
    .mbs-bell-wrap { position: relative; display: inline-flex; align-items: center; }
    .mbs-bell-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
        width: 360px; max-width: 92vw;
        max-height: 480px;
        background: #fff;
        border: 1px solid #eef0f3;
        border-radius: 12px;
        box-shadow: 0 12px 36px rgba(15, 23, 42, 0.12);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-4px);
        transition: opacity 0.15s, transform 0.15s, visibility 0.15s;
        z-index: 300;
        display: flex; flex-direction: column;
        overflow: hidden;
    }
    .mbs-bell-menu.is-open { opacity: 1; visibility: visible; transform: translateY(0); }
    .mbs-bell-menu__hdr {
        display:flex; align-items:center; justify-content:space-between;
        padding: 14px 16px;
        border-bottom: 1px solid #eef0f3;
        background: #fafbfc;
    }
    .mbs-bell-menu__hdr strong { font-size: 14px; color: #1c1a4a; }
    .mbs-bell-menu__hdrlink {
        font-size: 12px; color: #10b981; text-decoration: none;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .mbs-bell-menu__hdrlink:hover { color: #065f46; text-decoration: underline; }
    .mbs-bell-menu__body { flex:1; overflow-y: auto; }
    .mbs-bell-menu__state {
        padding: 32px 16px;
        text-align: center;
        color: #9ca3af;
        font-size: 13px;
    }
    .mbs-bell-menu__state i { font-size: 26px; display: block; margin-bottom: 8px; color: #d1d5db; }
    .mbs-bell-menu__ftr {
        display:flex; align-items:center; justify-content:space-between;
        padding: 12px 16px;
        border-top: 1px solid #eef0f3;
        background: #fafbfc;
    }
    .mbs-bell-menu__ftr a {
        font-size: 13px;
        color: #10b981;
        text-decoration: none;
        font-weight: 500;
    }
    .mbs-bell-menu__ftr a:hover { color: #065f46; }

    .mbs-bell-item {
        display: flex; gap: 12px; align-items: flex-start;
        padding: 12px 16px;
        border-bottom: 1px solid #f3f4f6;
        text-decoration: none;
        color: inherit;
        transition: background .15s;
    }
    .mbs-bell-item:last-child { border-bottom: 0; }
    .mbs-bell-item:hover { background: #f9fafb; }
    .mbs-bell-item.is-unread { background: linear-gradient(90deg, rgba(16, 185, 129,0.06) 0%, transparent 40%); }
    .mbs-bell-item__icon {
        flex-shrink: 0;
        width: 36px; height: 36px;
        border-radius: 50%;
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size: 13px;
    }
    .mbs-bell-item__body { flex:1; min-width: 0; }
    .mbs-bell-item__title {
        font-size: 13px; font-weight: 600; color: #1c1a4a;
        line-height: 1.3;
        overflow: hidden; text-overflow: ellipsis;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    }
    .mbs-bell-item.is-unread .mbs-bell-item__title { font-weight: 700; }
    .mbs-bell-item__text {
        font-size: 12px; color: #6b7280;
        margin-top: 2px;
        overflow: hidden; text-overflow: ellipsis;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    }
    .mbs-bell-item__time { font-size: 11px; color: #9ca3af; margin-top: 4px; }

    @media (max-width: 768px) {
        .mbs-topbar { padding: 10px 12px; gap: 6px; }
        .mbs-topbar__right { gap: 4px; }
    }

    /* Theme toggle: show moon when light, sun when dark */
    .mbs-theme-icon-dark { display: inline-block; }
    .mbs-theme-icon-light { display: none; }
    [data-theme="dark"] .mbs-theme-icon-dark { display: none; }
    [data-theme="dark"] .mbs-theme-icon-light { display: inline-block; }

    /* === Dark mode (applies when <html data-theme="dark">) === */
    [data-theme="dark"] body {
        background: #0f172a;
        color: #e2e8f0;
    }
    [data-theme="dark"] .mbs-topbar {
        background: #1e293b;
        border-bottom-color: #334155;
    }
    [data-theme="dark"] .mbs-icon-btn {
        background: #334155;
        color: #cbd5e1;
    }
    [data-theme="dark"] .mbs-icon-btn:hover {
        background: #475569;
        color: #fff;
    }
    [data-theme="dark"] .mbs-add-menu {
        background: #1e293b;
        border-color: #334155;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    }
    [data-theme="dark"] .mbs-add-menu a { color: #cbd5e1; }
    [data-theme="dark"] .mbs-add-menu a:hover { background: #334155; color: #fff; }
    [data-theme="dark"] .mbs-bell-menu { background:#1e293b; border-color:#334155; }
    [data-theme="dark"] .mbs-bell-menu__hdr,
    [data-theme="dark"] .mbs-bell-menu__ftr { background:#0f172a; border-color:#334155; }
    [data-theme="dark"] .mbs-bell-menu__hdr strong { color:#e2e8f0; }
    [data-theme="dark"] .mbs-bell-item { border-bottom-color:#334155; }
    [data-theme="dark"] .mbs-bell-item:hover { background:#334155; }
    [data-theme="dark"] .mbs-bell-item.is-unread { background: rgba(16, 185, 129,0.18); }
    [data-theme="dark"] .mbs-bell-item__title { color:#f1f5f9; }
    [data-theme="dark"] .mbs-bell-item__text { color:#94a3b8; }
</style>


<header class="mbs-topbar" id="mbsTopbar">
    {{-- ===== LEFT CLUSTER ===== --}}
    <div class="mbs-topbar__left">
        <button class="mbs-icon-btn mbs-sidebar-toggle" id="mbsSidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    {{-- ===== RIGHT CLUSTER ===== --}}
    <div class="mbs-topbar__right">
        {{-- Share current page URL --}}
        <button class="mbs-icon-btn" id="mbsShareBtn" aria-label="Share" title="{{ __('Share this page') }}">
            <i class="fas fa-share-alt"></i>
        </button>

        {{-- Tasks (pending items badge) --}}
        <a class="mbs-icon-btn mbs-icon-btn--badge" href="{{ route($tasksRoute) }}"
           title="{{ __('Pending tasks') }}" aria-label="Tasks">
            <i class="fas fa-check"></i>
            @if ($tasksCount > 0)
                <span class="mbs-topbar-badge mbs-topbar-badge--orange">{{ $tasksCount > 99 ? '99+' : $tasksCount }}</span>
            @endif
        </a>

        {{-- User avatar (links to profile) --}}
        <a class="mbs-avatar" href="{{ route($profileRoute) }}" title="{{ $u->name }}">
            @if ($u->image)
                <img src="{{ asset($u->image) }}" alt="{{ $u->name }}">
            @else
                <span class="mbs-avatar__fallback">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
            @endif
        </a>

        {{-- Dark mode toggle --}}
        <button class="mbs-icon-btn mbs-theme-toggle" id="mbsThemeToggle"
                title="{{ __('Toggle dark mode') }}" aria-label="Toggle dark mode">
            <i class="fas fa-moon mbs-theme-icon-dark"></i>
            <i class="fas fa-sun mbs-theme-icon-light"></i>
        </button>

        {{-- Notification bell (topbar-native, with badge + dropdown) --}}
        @php
            $bellUnread = $u->unreadNotifications()->count();
        @endphp
        <div class="mbs-bell-wrap">
            <button type="button" class="mbs-icon-btn mbs-icon-btn--badge" id="mbsBellTrigger"
                    aria-haspopup="true" aria-expanded="false" aria-label="{{ __('Notifications') }}"
                    title="{{ __('Notifications') }}">
                <i class="fas fa-bell"></i>
                <span class="mbs-topbar-badge" id="mbsBellBadge"
                      data-count="{{ $bellUnread }}"
                      style="display: {{ $bellUnread > 0 ? 'inline-block' : 'none' }};">
                    {{ $bellUnread > 99 ? '99+' : $bellUnread }}
                </span>
            </button>
            <div class="mbs-bell-menu" id="mbsBellMenu" role="menu" aria-hidden="true">
                <div class="mbs-bell-menu__hdr">
                    <strong>{{ __('Notifications') }}</strong>
                    <a href="javascript:;" id="mbsBellMarkAll" class="mbs-bell-menu__hdrlink"
                       style="display:none;" title="{{ __('Mark all as read') }}">
                        <i class="fas fa-check-double"></i> {{ __('Mark all read') }}
                    </a>
                </div>
                <div class="mbs-bell-menu__body">
                    <div id="mbsBellLoading" class="mbs-bell-menu__state">{{ __('Loading…') }}</div>
                    <div id="mbsBellEmpty" class="mbs-bell-menu__state" style="display:none;">
                        <i class="fas fa-bell-slash"></i>
                        <div>{{ __('No notifications yet') }}</div>
                    </div>
                    <div id="mbsBellItems"></div>
                </div>
                <div class="mbs-bell-menu__ftr">
                    <a href="{{ route('notifications.index') }}">{{ __('View all') }}</a>
                    <a href="{{ route('notifications.preferences.index') }}" title="{{ __('Preferences') }}">
                        <i class="fas fa-cog"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>


<script>
    (function () {
        // Sidebar toggle — toggles a class on the dashboard sidebar element
        const sidebarToggle = document.getElementById('mbsSidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                // Try a few common selectors used in this app's dashboards
                const targets = document.querySelectorAll(
                    '.student-sidebar, .instructor-sidebar, .dashboard__sidebar, .sidebar-nav, [data-sidebar]'
                );
                targets.forEach(el => el.classList.toggle('mbs-sidebar-collapsed'));
                document.body.classList.toggle('mbs-sidebar-collapsed');
            });
        }

        // Quick-add dropdown
        const addBtn = document.getElementById('mbsAddBtn');
        const addMenu = document.getElementById('mbsAddMenu');
        if (addBtn && addMenu) {
            addBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                addMenu.classList.toggle('is-open');
                addBtn.setAttribute('aria-expanded', addMenu.classList.contains('is-open'));
            });
            document.addEventListener('click', (e) => {
                if (!addMenu.contains(e.target) && e.target !== addBtn) {
                    addMenu.classList.remove('is-open');
                    addBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // Dark mode toggle
        const themeToggle = document.getElementById('mbsThemeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                const next = cur === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                try { localStorage.setItem('mbs-theme', next); } catch (e) {}
            });
        }

        // Share button — uses Web Share API where available, falls back to clipboard
        const shareBtn = document.getElementById('mbsShareBtn');
        if (shareBtn) {
            shareBtn.addEventListener('click', async () => {
                const data = { title: document.title, url: window.location.href };
                try {
                    if (navigator.share) {
                        await navigator.share(data);
                    } else {
                        await navigator.clipboard.writeText(window.location.href);
                        if (window.toastr) {
                            window.toastr.success('Link copied to clipboard');
                        } else {
                            alert('Link copied to clipboard');
                        }
                    }
                } catch (e) { /* user cancelled */ }
            });
        }

        // ===== Notification bell dropdown =====
        const bellTrigger = document.getElementById('mbsBellTrigger');
        const bellMenu    = document.getElementById('mbsBellMenu');
        const bellBadge   = document.getElementById('mbsBellBadge');
        const bellItems   = document.getElementById('mbsBellItems');
        const bellEmpty   = document.getElementById('mbsBellEmpty');
        const bellLoading = document.getElementById('mbsBellLoading');
        const bellMarkAll = document.getElementById('mbsBellMarkAll');
        const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
        const RECENT_URL = "{{ route('notifications.recent') }}";
        const MARK_READ_URL = "{{ url('notifications') }}/{id}/read";
        const MARK_ALL_URL  = "{{ route('notifications.mark-all-read') }}";
        let bellLoaded = false;

        function setBellBadge(n) {
            if (!bellBadge) return;
            bellBadge.dataset.count = n;
            bellBadge.textContent = n > 99 ? '99+' : String(n);
            bellBadge.style.display = n > 0 ? 'inline-block' : 'none';
            if (bellMarkAll) bellMarkAll.style.display = n > 0 ? 'inline-flex' : 'none';
        }

        function renderBellItems(items) {
            if (!bellItems) return;
            bellLoading.style.display = 'none';
            if (!items.length) {
                bellItems.innerHTML = '';
                bellEmpty.style.display = 'block';
                return;
            }
            bellEmpty.style.display = 'none';
            const safe = (s) => (s || '').replace(/[<>&"']/g, c => (
                { '<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;' }[c]
            ));
            bellItems.innerHTML = items.map(n => `
                <a class="mbs-bell-item ${n.read ? '' : 'is-unread'}"
                   href="${n.url || 'javascript:;'}" data-id="${n.id}">
                    <div class="mbs-bell-item__icon" style="background-color:${n.iconColor || '#10b981'};">
                        <i class="fas ${n.icon || 'fa-bell'}"></i>
                    </div>
                    <div class="mbs-bell-item__body">
                        <div class="mbs-bell-item__title">${safe(n.title)}</div>
                        ${n.body ? `<div class="mbs-bell-item__text">${safe(n.body)}</div>` : ''}
                        <div class="mbs-bell-item__time">${n.time_ago || ''}</div>
                    </div>
                </a>
            `).join('');

            bellItems.querySelectorAll('.mbs-bell-item').forEach(el => {
                el.addEventListener('click', () => {
                    const id = el.dataset.id;
                    if (!id) return;
                    fetch(MARK_READ_URL.replace('{id}', encodeURIComponent(id)), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                    }).catch(() => {});
                });
            });
        }

        function loadBell() {
            fetch(RECENT_URL, { headers: { 'Accept':'application/json' }, credentials:'same-origin' })
                .then(r => r.json())
                .then(data => {
                    setBellBadge(data.unread_count || 0);
                    renderBellItems(data.items || []);
                })
                .catch(() => { if (bellLoading) bellLoading.textContent = '{{ __('Failed to load') }}'; });
        }

        if (bellTrigger && bellMenu) {
            const openBell = () => {
                if (!bellLoaded) { bellLoaded = true; loadBell(); }
                bellMenu.classList.add('is-open');
                bellTrigger.setAttribute('aria-expanded', 'true');
                bellMenu.setAttribute('aria-hidden', 'false');
            };
            const closeBell = () => {
                bellMenu.classList.remove('is-open');
                bellTrigger.setAttribute('aria-expanded', 'false');
                bellMenu.setAttribute('aria-hidden', 'true');
            };

            bellTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                bellMenu.classList.contains('is-open') ? closeBell() : openBell();
            });
            document.addEventListener('click', (e) => {
                if (!bellMenu.contains(e.target) && e.target !== bellTrigger) closeBell();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && bellMenu.classList.contains('is-open')) closeBell();
            });
        }

        if (bellMarkAll) {
            bellMarkAll.addEventListener('click', () => {
                fetch(MARK_ALL_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    credentials: 'same-origin'
                }).then(() => {
                    setBellBadge(0);
                    bellLoaded = false;
                    bellItems.querySelectorAll('.mbs-bell-item.is-unread').forEach(el => el.classList.remove('is-unread'));
                });
            });
        }

        // Real-time bump via Echo when Pusher is active
        window.addEventListener('DOMContentLoaded', () => {
            if (!window.Echo) return;
            const userId = {{ auth('web')->id() ?? 0 }};
            if (!userId) return;
            window.Echo.private('App.Models.User.' + userId)
                .notification(() => {
                    const cur = parseInt(bellBadge?.dataset.count || '0', 10);
                    setBellBadge(cur + 1);
                    bellLoaded = false;
                });
        });
    })();
</script>
@endauth
