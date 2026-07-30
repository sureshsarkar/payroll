{{--
    Admin notification bell — sits in the admin navbar next to the user dropdown.
    Same UX as the frontend bell, but uses admin routes + admin guard.
--}}
@auth('admin')
@php
    $bellAdmin = Auth::guard('admin')->user();
    $unreadCount = $bellAdmin ? $bellAdmin->unreadNotifications()->count() : 0;
@endphp
<li class="dropdown dropdown-list-toggle mbs-admin-bell" style="position:relative;">
    <a href="javascript:;" id="mbs-admin-bell-trigger"
       class="nav-link nav-link-lg dropdown-toggle"
       style="padding:0 14px; position:relative;"
       data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-bell" style="font-size:16px; color:#6b7280;"></i>
        <span id="mbs-admin-bell-badge"
              style="position:absolute; top:8px; right:6px; background:#ef4444; color:#fff; font-size:10px; font-weight:700; min-width:16px; height:16px; line-height:16px; border-radius:8px; padding:0 4px; text-align:center; box-shadow:0 0 0 2px #fff; display:{{ $unreadCount > 0 ? 'inline-block' : 'none' }};"
              data-count="{{ $unreadCount }}">
            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
        </span>
    </a>
    <div class="dropdown-menu dropdown-menu-right" style="min-width:340px; max-height:480px; overflow-y:auto; padding:0;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid #eef0f3; background:#fafbfc;">
            <strong style="font-size:14px; color:#1c1a4a;">{{ __('Notifications') }}</strong>
            <a href="javascript:;" id="mbs-admin-mark-all-read"
               style="font-size:12px; color:#5751e1; text-decoration:none; display:none; gap:4px; align-items:center;">
                <i class="fa fa-check-double" style="font-size:11px;"></i>
                {{ __('Mark all read') }}
            </a>
        </div>
        <div id="mbs-admin-bell-loading" style="padding:24px 16px; text-align:center; color:#9ca3af; font-size:13px;">
            {{ __('Loading…') }}
        </div>
        <div id="mbs-admin-bell-empty" style="display:none; padding:32px 16px; text-align:center; color:#9ca3af; font-size:13px;">
            <i class="fa fa-bell-slash" style="font-size:24px; display:block; margin-bottom:8px;"></i>
            {{ __('No notifications yet') }}
        </div>
        <div id="mbs-admin-bell-items"></div>
        <div style="padding:12px 16px; border-top:1px solid #eef0f3; background:#fafbfc; text-align:center;">
            <a href="{{ route('admin.notifications.index') }}" style="font-size:13px; color:#5751e1; font-weight:500; text-decoration:none;">{{ __('View all') }}</a>
        </div>
    </div>
</li>

<script>
(function () {
    const RECENT_URL   = "{{ route('admin.notifications.recent') }}";
    const MARK_READ    = "{{ url('admin/notifications') }}/{id}/read";
    const MARK_ALL_URL = "{{ route('admin.notifications.mark-all-read') }}";
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
    let firstOpen = true;

    function setBadge(n) {
        const b = document.getElementById('mbs-admin-bell-badge');
        if (b) {
            b.dataset.count = n;
            b.textContent = n > 99 ? '99+' : String(n);
            b.style.display = n > 0 ? 'inline-block' : 'none';
        }
        const m = document.getElementById('mbs-admin-mark-all-read');
        if (m) m.style.display = n > 0 ? 'inline-flex' : 'none';
    }

    function renderItems(items) {
        const wrap = document.getElementById('mbs-admin-bell-items');
        const empty = document.getElementById('mbs-admin-bell-empty');
        const loading = document.getElementById('mbs-admin-bell-loading');
        loading.style.display = 'none';
        if (!items.length) { wrap.innerHTML = ''; empty.style.display = 'block'; return; }
        empty.style.display = 'none';
        wrap.innerHTML = items.map(n => {
            const safe = (s) => (s || '').replace(/[<>&]/g, c => ({ '<':'&lt;', '>':'&gt;', '&':'&amp;' }[c]));
            return `
                <a class="mbs-admin-bell-item d-flex align-items-start p-3 border-bottom text-decoration-none text-reset ${n.read ? '' : 'bg-light'}"
                   href="${n.url || 'javascript:;'}" data-id="${n.id}"
                   style="${n.read ? '' : 'background:#f0f7ff !important;'}">
                    <div style="flex-shrink:0; width:32px; height:32px; border-radius:50%; background:${n.iconColor}; display:flex; align-items:center; justify-content:center; color:#fff; margin-right:12px;">
                        <i class="fa ${n.icon}" style="font-size:13px;"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:13px; font-weight:600; color:#1c1a4a; line-height:1.3;">${safe(n.title)}</div>
                        <div style="font-size:12px; color:#6b7280; margin-top:2px;">${safe(n.body)}</div>
                        <div style="font-size:11px; color:#9ca3af; margin-top:4px;">${n.time_ago || ''}</div>
                    </div>
                </a>
            `;
        }).join('');

        wrap.querySelectorAll('.mbs-admin-bell-item').forEach(el => {
            el.addEventListener('click', () => {
                const id = el.dataset.id;
                if (!id) return;
                fetch(MARK_READ.replace('{id}', encodeURIComponent(id)), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                }).catch(() => {});
            });
        });
    }

    function loadRecent() {
        fetch(RECENT_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                setBadge(data.unread_count || 0);
                renderItems(data.items || []);
            })
            .catch(() => {
                document.getElementById('mbs-admin-bell-loading').textContent = '{{ __('Failed to load') }}';
            });
    }

    const trigger = document.getElementById('mbs-admin-bell-trigger');
    if (trigger) {
        const handler = () => { if (firstOpen) { firstOpen = false; loadRecent(); } };
        trigger.addEventListener('click', handler);
        trigger.addEventListener('mouseenter', handler);
    }

    document.getElementById('mbs-admin-mark-all-read')?.addEventListener('click', () => {
        fetch(MARK_ALL_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(() => { setBadge(0); firstOpen = true; });
    });
})();
</script>
@endauth
