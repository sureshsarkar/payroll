            {{-- 2026-07-20 Phase 6 — group membership aligned to the approved
                 design. Blocks are MOVED verbatim with their own guards; nothing
                 is re-gated, renamed or dropped. --}}
{{-- 2026-07-20 Phase 5 — one cached lookup for every sidebar badge. The
     module partials used to each run their own uncached ->count() inline. --}}
@php $mbsCounts = adminMenuCounts(); @endphp
<div class="main-sidebar">
    <aside id="sidebar-wrapper">
        {{-- 2026-07-20 — brand block matches the approved design: a rounded mark
             tile beside the wordmark + role. Stays WHITE-LABEL — the tile shows
             the tenant's configured favicon, falling back to the app-name
             initial; the wordmark is the configured app_name. Nothing here is
             hardcoded to "MBSGuru". --}}
            @php $mbsAppName = $setting->app_name ?? config('app.name', 'Admin'); @endphp
        <a href="{{ route('admin.dashboard') }}" class="mbs-brand">
            {{-- Clean initial-on-accent tile, exactly like the approved design.
                 Derived from app_name, so it stays white-label per tenant. --}}
            <span class="mbs-brand-tile">{{ strtoupper(mb_substr($mbsAppName, 0, 1)) }}</span>
            <span class="mbs-brand-txt">
                <span class="mbs-brand-name">{{ $mbsAppName }}</span>
                <span class="sidebar-brand-role">{{ __('Platform admin') }}</span>
            </span>
        </a>
        <div class="sidebar-brand">
            {{-- kept for the vendor's collapsed-rail logic; visually replaced by
                 .mbs-brand above. --}}
            <a href="{{ route('admin.dashboard') }}"><img class="admin_logo" style="filter:brightness(0%);"
                    src="{{ asset($setting->logo) ?? '' }}" alt="{{ $mbsAppName }}"></a>
        </div>

        <div class="sidebar-brand sidebar-brand-sm">
            <a href="{{ route('admin.dashboard') }}"><img src="{{ asset($setting->favicon) ?? '' }}"
                    alt="{{ $setting->app_name ?? '' }}"></a>
        </div>

        {{--
            ============================================================================
            ENTERPRISE NAVIGATION — restructured 2026-06-25 (Super Admin panel audit).
            Sprawling 10 ad-hoc groups collapsed into 8 role-aligned sections:
              Overview · Learning & Content · Commerce · Monetization ·
              Users & Coaches · Website & Branding · Engagement · Settings.
            REORGANIZE ONLY — no feature/route removed; every @include + its own
            module/permission guard is preserved, just relocated to a sane group.
            Fixes applied:
              • Membership/Referrals/Commissions were wrongly gated on order/coupon/
                withdraw perms (copy-paste) — now gated on their real perms.
              • Dead `siteappearance::sidebar` (0-byte partial) include removed.
              • Typo guard `brand.managemen` → `brand.management`.
              • All 12 website-building tools unified under "Website & Branding".
            ============================================================================
        --}}
        <ul class="sidebar-menu">

            {{-- ───────────────────────── OVERVIEW ───────────────────────── --}}
            @if (checkAdminHasPermission('dashboard.view') || checkAdminHasPermission('activity-log.view'))
                <li class="menu-header">{{ __('Overview') }}</li>

                @adminCan('dashboard.view')
                    <li class="{{ isRoute('admin.dashboard', 'active') }}">
                        <a class="nav-link" href="{{ route('admin.dashboard') }}"><i class="fas fa-home"></i>
                            <span>{{ __('Dashboard') }}</span>
                        </a>
                    </li>
                @endadminCan

                {{-- Enterprise H-A — activity / audit log (Super Admin) --}}
                @adminCan('activity-log.view')
                    <li class="{{ isRoute('admin.activity-logs', 'active') }}">
                        <a class="nav-link" href="{{ route('admin.activity-logs') }}"><i class="fas fa-history"></i>
                            <span>{{ __('Activity Log') }}</span>
                        </a>
                    </li>
                @endadminCan

                {{-- 2026-07-20 (Phase 2) — admin.notifications.index shipped with no
                     menu entry; it was reachable only from the topbar bell. --}}
                @if (Route::has('admin.notifications.index'))
                    <li class="{{ isRoute('admin.notifications.*', 'active') }}">
                        <a class="nav-link" href="{{ route('admin.notifications.index') }}"><i class="fas fa-bell"></i>
                            <span>{{ __('Notifications') }}</span>
                        </a>
                    </li>
                @endif

            @endif

            {{-- Payroll conversion — Super Admin payroll approval --}}
            @if (Route::has('admin.payroll.index'))
                <li class="menu-header">{{ __('Payroll') }}</li>
                @if (Route::has('admin.payroll.dashboard'))
                    <li class="{{ isRoute('admin.payroll.dashboard', 'active') }}">
                        <a class="nav-link" href="{{ route('admin.payroll.dashboard') }}"><i class="fas fa-chart-line"></i>
                            <span>{{ __('Payroll Dashboard') }}</span>
                        </a>
                    </li>
                @endif
                <li class="{{ isRoute(['admin.payroll.index','admin.payroll.show'], 'active') }}">
                    <a class="nav-link" href="{{ route('admin.payroll.index') }}"><i class="fas fa-file-invoice-dollar"></i>
                        <span>{{ __('Payroll Approvals') }}</span>
                    </a>
                </li>
            @endif

            {{-- LMS removal phase 2 (2026-08-27) — removed seven whole sidebar
                 groups that administered the coach/LMS business:
                   Coaches       — pricing plans, trial settings, coach plan
                                   assignment + billing, coach memberships and
                                   plan conversion, coach landing pages and their
                                   template-performance report, custom domains,
                                   instructor requests, coach commissions,
                                   Zoom OAuth health.
                   Students & Users — the Customer module.
                   Content       — courses and their taxonomy, course batches,
                                   batch announcements, attendance settings,
                                   certificate builder, badges, blog.
                   Commerce      — orders, coupons, payment withdrawals.
                   Subscriptions — referrals + referral commissions + settings,
                                   the Subscription module, trial sessions.
                   Website       — the Theme Studio, the homepage section
                                   builder (Frontend), Brand, FooterSetting,
                                   MenuBuilder, PageBuilder,
                                   PageTemplateBuilder, SocialLink, FAQ.
                   Engagement    — booking enquiries, newsletter, testimonials,
                                   contact messages, landing-page messages.
                 Every module they included is deleted, so the guards had nothing
                 left to guard. Overview, Payroll, Location and Settings survive.
                 --}}

            @if (checkAdminHasPermission('location.view'))
                <li class="menu-header">{{ __('Users & Locations') }}</li>

                @if (Module::isEnabled('Location') && checkAdminHasPermission('location.view'))
                    @include('location::sidebar')
                @endif
            @endif

            @if (Module::isEnabled('Language') && checkAdminHasPermission('language.view'))
                <li class="menu-header">{{ __('Localization') }}</li>
                @include('language::sidebar')

                @if (Module::isEnabled('Currency') && checkAdminHasPermission('currency.view'))
                    @include('currency::sidebar')
                @endif
            @endif


            @if (checkAdminHasPermission('setting.view') ||
                    checkAdminHasPermission('basic.payment.view') ||
                    checkAdminHasPermission('payment.view') ||
                    checkAdminHasPermission('currency.view') ||
                    checkAdminHasPermission('role.view') ||
                    checkAdminHasPermission('admin.view') ||
                    checkAdminHasPermission('addon.view'))
                <li class="menu-header">{{ __('Settings') }}</li>
                <li class="{{ isRoute('admin.settings', 'active') }}">
                    <a class="nav-link" href="{{ route('admin.settings') }}"><i class="fas fa-cog"></i>
                        <span>{{ __('Settings') }}</span>
                    </a>
                </li>
            @endif


        </ul>

    </aside>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     2026-07-20 Phase 2b — collapsible group accordion.

     Deliberately a PROGRESSIVE ENHANCEMENT rather than a markup change: the
     Blade above still emits a flat `menu-header` + sibling `<li>` list, so
     every permission gate and Module::isEnabled guard stays exactly where it
     was. This script only re-parents what the server already decided to
     render — it can never reveal an item the gates hid.

     If the script fails, the sidebar degrades to the previous flat list.
     ══════════════════════════════════════════════════════════════════════ --}}
@push('js')
<script>
(function () {
    'use strict';

    var ICONS = {
        'Overview': 'fa-th-large', 'Payroll': 'fa-file-invoice-dollar',
        'Users & Locations': 'fa-users', 'Localization': 'fa-language',
        'Settings': 'fa-cog'
    };
    var STORE = 'mbsAdminSidebarGroup';

    function build() {
        var menu = document.querySelector('#sidebar-wrapper .sidebar-menu');
        if (!menu || menu.dataset.grouped === '1') return;

        var headers = [].slice.call(menu.querySelectorAll('li.menu-header'));
        if (!headers.length) return;

        headers.forEach(function (header) {
            var label = (header.textContent || '').trim();

            // Collect this group's items: every sibling up to the next header.
            var items = [], node = header.nextElementSibling;
            while (node && !node.classList.contains('menu-header')) {
                var next = node.nextElementSibling;
                items.push(node);
                node = next;
            }
            if (!items.length) { header.style.display = 'none'; return; }

            var group = document.createElement('li');
            group.className = 'nav-group';
            group.dataset.group = label;

            var toggle = document.createElement('a');
            toggle.className = 'nav-group-toggle';
            toggle.href = 'javascript:;';
            toggle.setAttribute('aria-expanded', 'false');
            toggle.innerHTML =
                '<i class="fas ' + (ICONS[label] || 'fa-folder') + '"></i>' +
                '<span></span><i class="fas fa-chevron-down chev"></i>';
            toggle.querySelector('span').textContent = label;   // never inject label as HTML

            var list = document.createElement('ul');
            list.className = 'nav-group-items';

            // FLATTEN to match the approved design: the modules render a
            // "Manage X" dropdown parent wrapping the real links. The design is a
            // flat 2-level menu (group → item), so promote every dropdown child
            // to a direct item and drop the intermediate parent. Permissions and
            // badges are untouched — the server already rendered only the items
            // this admin may see, and each child keeps its markup.
            items.forEach(function (li) {
                var sub = li.querySelector(':scope > ul.dropdown-menu, :scope > .dropdown-menu');
                if (sub) {
                    [].slice.call(sub.children).forEach(function (child) {
                        if (child.tagName === 'LI') list.appendChild(child);
                    });
                    // Drop the now-empty "Manage X" parent so it isn't left
                    // behind as an orphan sibling in the DOM.
                    if (li.parentNode) li.parentNode.removeChild(li);
                } else {
                    list.appendChild(li);
                }
            });

            group.appendChild(toggle);
            group.appendChild(list);
            header.parentNode.replaceChild(group, header);

        });

        menu.dataset.grouped = '1';

        // Open EXACTLY ONE group on load (true accordion). Priority:
        //   1. the group whose child carries the server `active` class
        //   2. the group whose child link's URL === the current page
        //   3. the last group the operator opened (sessionStorage)
        //   4. the first group (Overview) — never leave the menu fully collapsed
        var groups = menu.querySelectorAll('.nav-group');
        var here   = location.pathname.replace(/\/+$/, '');
        var active = null;

        [].forEach.call(groups, function (g) {
            if (!active && g.querySelector('.nav-group-items li.active, .nav-group-items a.active')) active = g;
        });
        if (!active) {
            [].forEach.call(groups, function (g) {
                if (active) return;
                var hit = [].some.call(g.querySelectorAll('.nav-group-items a[href]'), function (a) {
                    var raw = a.getAttribute('href') || '';
                    // Skip non-navigational hrefs — "#" / "javascript:;" resolve to
                    // the CURRENT url and would falsely match every dropdown parent.
                    if (raw === '' || raw === '#' || raw.indexOf('javascript:') === 0) return false;
                    try { return new URL(a.href).pathname.replace(/\/+$/, '') === here; }
                    catch (e) { return false; }
                });
                if (hit) active = g;
            });
        }
        if (!active) {
            var saved = null;
            try { saved = sessionStorage.getItem(STORE); } catch (e) {}
            active = (saved && menu.querySelector('.nav-group[data-group="' + saved.replace(/"/g, '\\"') + '"]'));
        }
        open(active || groups[0], true);   // exclusive — only one open

        menu.addEventListener('click', function (e) {
            var toggle = e.target.closest && e.target.closest('.nav-group-toggle');
            if (!toggle) return;
            e.preventDefault();
            var group = toggle.parentNode;
            group.classList.contains('open') ? close(group) : open(group, true);
        });
    }

    function open(group, exclusive) {
        if (!group) return;
        if (exclusive) {
            // Accordion: one group at a time.
            [].forEach.call(group.parentNode.querySelectorAll('.nav-group.open'), function (o) {
                if (o !== group) close(o);
            });
        }
        group.classList.add('open');
        var t = group.querySelector('.nav-group-toggle');
        if (t) t.setAttribute('aria-expanded', 'true');
        try { sessionStorage.setItem(STORE, group.dataset.group); } catch (e) {}
    }

    function close(group) {
        group.classList.remove('open');
        var t = group.querySelector('.nav-group-toggle');
        if (t) t.setAttribute('aria-expanded', 'false');
    }

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', build)
        : build();
})();
</script>
@endpush
