{{--
    Modern bell-icon notification dropdown for the frontend dashboard topbar.
    Used for both coach (instructor) and student dashboards. Auto-hides for guests.

    Real-time updates: when Pusher is configured (BROADCAST_DRIVER=pusher) the
    badge count + dropdown update without a page refresh — see resources/views/frontend/layouts/inner-header.blade.php for the Echo listener.
--}}
@auth('web')
    @php
        $bellUser = Auth::guard('web')->user();
        $unreadCount = $bellUser ? $bellUser->unreadNotifications()->count() : 0;
    @endphp
    <li class="mini-cart-icon user_icon mbs-notif-bell">
        <a href="javascript:;" class="cart-count" id="mbs-bell-trigger" aria-label="Notifications">
            <i class="fa fa-bell"></i>
            <span class="mbs-notif-badge" id="mbs-notif-badge" data-count="{{ $unreadCount }}"
                  style="display: {{ $unreadCount > 0 ? 'inline-block' : 'none' }};">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        </a>
        <ul class="menu_user_list mbs-notif-list" id="mbs-notif-list" style="min-width: 360px; max-height: 480px; overflow-y: auto; padding: 0;">
            <li class="mbs-notif-header" style="display:flex; align-items:center; justify-content:space-between; padding: 14px 16px; border-bottom: 1px solid #eef0f3; background:#fafbfc;">
                <strong style="font-size:14px; color:#1c1a4a;">{{ __('Notifications') }}</strong>
                <a href="javascript:;" id="mbs-mark-all-read"
                   style="font-size:12px; color:#10b981; text-decoration:none; display:none; align-items:center; gap:4px;"
                   title="{{ __('Mark all as read') }}">
                    <i class="fas fa-check-double" style="font-size:11px;"></i>
                    <span>{{ __('Mark all as read') }}</span>
                </a>
            </li>
            <li id="mbs-notif-loading" style="padding: 24px 16px; text-align:center; color:#9ca3af; font-size: 13px;">
                {{ __('Loading…') }}
            </li>
            <li id="mbs-notif-empty" style="display:none; padding: 32px 16px; text-align:center; color:#9ca3af; font-size: 13px;">
                <i class="fa fa-bell-slash" style="font-size:24px; display:block; margin-bottom:8px;"></i>
                {{ __('No notifications yet') }}
            </li>
            <li id="mbs-notif-items" style="padding: 0;"></li>
            <li class="mbs-notif-footer" style="display:flex; align-items:center; justify-content:space-between; padding: 12px 16px; border-top: 1px solid #eef0f3; background:#fafbfc;">
                <a href="{{ route('notifications.index') }}" style="font-size: 13px; color:#10b981; font-weight: 500; text-decoration:none;">{{ __('View all') }}</a>
                <a href="{{ route('notifications.preferences.index') }}" style="font-size: 12px; color:#6b7280; text-decoration:none;" title="{{ __('Notification preferences') }}">
                    <i class="fas fa-cog"></i>
                </a>
            </li>
        </ul>
    </li>

    {{-- Bell dropdown styles + behavior --}}
    <style>
        .mbs-notif-bell { position: relative; }
        .mbs-notif-badge {
            position: absolute;
            top: -4px; right: -4px;
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
        .mbs-notif-item {
            display: flex; gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            text-decoration: none;
            color: inherit;
            transition: background-color 0.15s;
        }
        .mbs-notif-item:hover { background: #f9fafb; }
        .mbs-notif-item.is-unread { background: #f0f7ff; }
        .mbs-notif-item .mbs-notif-icon {
            flex-shrink: 0;
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 14px;
        }
        .mbs-notif-item .mbs-notif-content { flex: 1; min-width: 0; }
        .mbs-notif-item .mbs-notif-title {
            font-size: 13px; font-weight: 600; color: #1c1a4a;
            margin: 0 0 2px;
            line-height: 1.3;
            overflow: hidden; text-overflow: ellipsis;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        }
        .mbs-notif-item .mbs-notif-body {
            font-size: 12px; color: #6b7280;
            margin: 0;
            line-height: 1.4;
            overflow: hidden; text-overflow: ellipsis;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        }
        .mbs-notif-item .mbs-notif-time {
            font-size: 11px; color: #9ca3af;
            margin-top: 4px;
        }
    </style>

    <script>
        (function () {
            const NOTIF_RECENT_URL = "{{ route('notifications.recent') }}";
            const NOTIF_MARK_READ_URL = "{{ url('notifications') }}/{id}/read";
            const NOTIF_MARK_ALL_URL = "{{ route('notifications.mark-all-read') }}";
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;

            let firstOpen = true;

            function renderItems(items) {
                const wrap = document.getElementById('mbs-notif-items');
                const empty = document.getElementById('mbs-notif-empty');
                const loading = document.getElementById('mbs-notif-loading');
                loading.style.display = 'none';
                if (!items.length) {
                    wrap.innerHTML = '';
                    empty.style.display = 'block';
                    return;
                }
                empty.style.display = 'none';
                wrap.innerHTML = items.map(n => {
                    const safeTitle = (n.title || '').replace(/[<>&]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]));
                    const safeBody  = (n.body  || '').replace(/[<>&]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]));
                    const href = n.url || 'javascript:;';
                    return `
                        <a class="mbs-notif-item ${n.read ? '' : 'is-unread'}"
                           href="${href}"
                           data-id="${n.id}">
                            <div class="mbs-notif-icon" style="background-color: ${n.iconColor};">
                                <i class="fa ${n.icon}"></i>
                            </div>
                            <div class="mbs-notif-content">
                                <div class="mbs-notif-title">${safeTitle}</div>
                                <div class="mbs-notif-body">${safeBody}</div>
                                <div class="mbs-notif-time">${n.time_ago || ''}</div>
                            </div>
                        </a>
                    `;
                }).join('');

                // Click an item → mark it read on the way out (if it has a URL)
                wrap.querySelectorAll('.mbs-notif-item').forEach(el => {
                    el.addEventListener('click', (e) => {
                        const id = el.dataset.id;
                        if (!id || el.classList.contains('is-read-marked')) return;
                        el.classList.add('is-read-marked');
                        // Fire-and-forget mark-read
                        fetch(NOTIF_MARK_READ_URL.replace('{id}', encodeURIComponent(id)), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                        }).catch(() => {});
                    });
                });
            }

            function setBadge(n) {
                const badge = document.getElementById('mbs-notif-badge');
                if (badge) {
                    badge.dataset.count = n;
                    if (n > 0) {
                        badge.textContent = n > 99 ? '99+' : String(n);
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
                // Show/hide "Mark all as read" button based on whether there's anything to mark.
                const markAllBtn = document.getElementById('mbs-mark-all-read');
                if (markAllBtn) {
                    markAllBtn.style.display = n > 0 ? 'inline-flex' : 'none';
                }
            }

            function loadRecent() {
                fetch(NOTIF_RECENT_URL, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(data => {
                        setBadge(data.unread_count || 0);
                        renderItems(data.items || []);
                    })
                    .catch(() => {
                        document.getElementById('mbs-notif-loading').textContent = '{{ __('Failed to load') }}';
                    });
            }

            // Lazy-load on first hover/click of the bell
            const trigger = document.getElementById('mbs-bell-trigger');
            if (trigger) {
                trigger.addEventListener('click', () => {
                    if (firstOpen) { firstOpen = false; loadRecent(); }
                });
                trigger.addEventListener('mouseenter', () => {
                    if (firstOpen) { firstOpen = false; loadRecent(); }
                });
            }

            // Mark all as read
            const markAll = document.getElementById('mbs-mark-all-read');
            if (markAll) {
                markAll.addEventListener('click', () => {
                    fetch(NOTIF_MARK_ALL_URL, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                    })
                    .then(() => {
                        setBadge(0);
                        document.querySelectorAll('.mbs-notif-item.is-unread')
                            .forEach(el => el.classList.remove('is-unread'));
                    })
                    .catch(() => {});
                });
            }

            // Real-time push via Echo (only fires when Pusher is configured)
            window.addEventListener('DOMContentLoaded', () => {
                if (!window.Echo) return;
                const userId = {{ auth('web')->id() ?? 0 }};
                if (!userId) return;
                window.Echo.private('App.Models.User.' + userId)
                    .notification((n) => {
                        // Bump badge + reload dropdown silently
                        const cur = parseInt(document.getElementById('mbs-notif-badge').dataset.count || '0', 10);
                        setBadge(cur + 1);
                        firstOpen = true;  // force reload on next open
                        // Optional: toast
                        if (window.toastr && n.title) {
                            window.toastr.info(n.body || '', n.title);
                        }
                    });
            });
        })();
    </script>
@endauth
