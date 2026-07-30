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
                <li class="{{ isRoute('admin.payroll.*', 'active') }}">
                    <a class="nav-link" href="{{ route('admin.payroll.index') }}"><i class="fas fa-file-invoice-dollar"></i>
                        <span>{{ __('Payroll Approvals') }}</span>
                    </a>
                </li>
            @endif

            {{-- ───────────────────────────── COACHES ─────────────────────────────
                 2026-07-20 Phase 2 — regrouped to the approved 9-group structure.
                 Every block below is MOVED verbatim: its own @if / Module::isEnabled
                 guard travels with it, so no item changes visibility. --}}
            @if (checkAdminHasPermission('instructor.request.list') ||
                    checkAdminHasPermission('instructor.request.setting') ||
                    checkAdminHasPermission('membership-plan.view') ||
                    checkAdminHasPermission('user-membership.view') ||
                    checkAdminHasPermission('coach-landing-page.view') ||
                    checkAdminHasPermission('custom_domain.view') ||
                    checkAdminHasPermission('dashboard.view'))
                <li class="menu-header">{{ __('Coaches') }}</li>

                @if (checkAdminHasPermission('membership-plan.view') || checkAdminHasPermission('user-membership.view'))
                    {{-- Membership menu trimmed to the pricing-spec (MBS_Guru_Pricing.pdf):
                         Pricing Plans / Assign Coach Plan / Coach Billing. --}}
                    <li class="dropdown {{ isRoute(['admin.membership-plans.*','admin.user-memberships.*'], 'active') }}">
                        <a href="javascript:;" class="nav-link has-dropdown"><i class="fas fa-shield-alt"></i><span>{{ __('Membership') }}</span></a>
                        <ul class="dropdown-menu">
                            <li class="{{ isRoute('admin.membership-plans.*', 'active') }}"><a href="{{ route('admin.membership-plans.index') }}">{{ __('Pricing Plans') }}</a></li>
                            <li class="{{ isRoute('admin.coach-trial-settings.*', 'active') }}"><a href="{{ route('admin.coach-trial-settings.edit') }}">{{ __('Trial Settings') }}</a></li>
                            <li class="{{ isRoute('admin.user-memberships.assign.*', 'active') }}"><a href="{{ route('admin.user-memberships.assign.form') }}">{{ __('Assign Coach Plan') }}</a></li>
                            <li class="{{ isRoute('admin.user-memberships.billing', 'active') }}"><a href="{{ route('admin.user-memberships.billing') }}">{{ __('Coach Billing') }}</a></li>
                            {{-- 2026-07-20 (Super Admin menu-coverage audit) — these two pages
                                 existed but had no menu entry (reachable only from a dashboard
                                 widget), so delegated admins could not find them. --}}
                            @if (Route::has('admin.user-memberships.index'))
                                <li class="{{ isRoute('admin.user-memberships.index', 'active') }}"><a href="{{ route('admin.user-memberships.index') }}">{{ __('Coach Memberships') }}</a></li>
                            @endif
                            @if (Route::has('admin.user-memberships.conversion'))
                                <li class="{{ isRoute('admin.user-memberships.conversion', 'active') }}"><a href="{{ route('admin.user-memberships.conversion') }}">{{ __('Plan Conversion') }}</a></li>
                            @endif
                        </ul>
                    </li>
                @endif

                {{-- Phase 5/7 — Coach landing-page oversight + template performance (per-coach). --}}
                @if (checkAdminHasPermission('coach-landing-page.view'))
                    <li class="dropdown {{ isRoute('admin.coach-landing-pages.*', 'active') }}">
                        <a href="javascript:;" class="nav-link has-dropdown">
                            <i class="fas fa-rocket"></i><span>{{ __('Coach Landing Pages') }}</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="{{ isRoute('admin.coach-landing-pages.index', 'active') }}">
                                <a href="{{ route('admin.coach-landing-pages.index') }}">{{ __('All landing pages') }}</a>
                            </li>
                            <li class="{{ isRoute('admin.coach-landing-pages.templates-report', 'active') }}">
                                <a href="{{ route('admin.coach-landing-pages.templates-report') }}">{{ __('Template performance') }}</a>
                            </li>
                        </ul>
                    </li>
                @endif

                {{-- 2026-06-09 — coach custom-domain manager --}}
                @if (checkAdminHasPermission('custom_domain.view'))
                    <li class="dropdown {{ isRoute('admin.custom-domains.*', 'active') }}">
                        <a href="javascript:;" class="nav-link has-dropdown"><i class="fas fa-globe"></i><span>{{ __('Custom Domains') }}</span></a>
                        <ul class="dropdown-menu">
                            <li class="{{ isRoute('admin.custom-domains.index', 'active') }}"><a href="{{ route('admin.custom-domains.index') }}">{{ __('All domains') }}{!! adminMenuBadge($mbsCounts['domains_active'] ?? null) !!}</a></li>
                            @if (checkAdminHasPermission('custom_domain.settings'))
                                <li class="{{ isRoute('admin.custom-domains.settings', 'active') }}"><a href="{{ route('admin.custom-domains.settings') }}">{{ __('Settings') }}</a></li>
                            @endif
                        </ul>
                    </li>
                @endif

                @if (
                    (Module::isEnabled('InstructorRequest') && checkAdminHasPermission('instructor.request.list')) ||
                        checkAdminHasPermission('instructor.request.setting'))
                    @include('instructorrequest::sidebar')
                @endif

                {{-- Audit 2026-05-18 Req 3 — per-coach commission management --}}
                @if (checkAdminHasPermission('dashboard.view'))
                    <li class="{{ isRoute('admin.coach-commissions.*', 'active') }}">
                        <a href="{{ route('admin.coach-commissions.index') }}" class="nav-link">
                            <i class="fas fa-percentage"></i><span>{{ __('Coach Commissions') }}</span>
                        </a>
                    </li>
                @endif

                {{-- 2026-07-20 (Super Admin menu-coverage audit) — Zoom Health shipped as a
                     TRUE orphan: no link in any view, reachable only by typing the URL.
                     Gated on dashboard.view, matching the other coach-ops items.
                     NOTE: `admin.coach-payment-gateways.index` is deliberately NOT listed
                     here — its URI is admin/coaches/{coach}/payment-gateways, i.e. a
                     per-coach page opened from a coach row, not a top-level destination. --}}
                @if (checkAdminHasPermission('dashboard.view') && Route::has('admin.zoom-health.index'))
                    <li class="{{ isRoute('admin.zoom-health.*', 'active') }}">
                        <a class="nav-link" href="{{ route('admin.zoom-health.index') }}"><i class="fas fa-video"></i>
                            <span>{{ __('Zoom Health') }}</span>
                        </a>
                    </li>
                @endif
            @endif

            @if (checkAdminHasPermission('customer.view') || checkAdminHasPermission('location.view'))
                <li class="menu-header">{{ __('Students & Users') }}</li>

                @if (Module::isEnabled('Customer') && checkAdminHasPermission('customer.view'))
                    @include('customer::sidebar')
                @endif

                @if (Module::isEnabled('Location') && checkAdminHasPermission('location.view'))
                    @include('location::sidebar')
                @endif
            @endif

            @if (checkAdminHasPermission('course.management') ||
                    checkAdminHasPermission('course.certificate.management') ||
                    checkAdminHasPermission('badge.management') ||
                    checkAdminHasPermission('blog.view'))
                <li class="menu-header">{{ __('Content') }}</li>

                @if (Module::isEnabled('Course') && checkAdminHasPermission('course.management'))
                    @include('course::sidebar')
                @endif

                {{-- Audit 2026-05-18 — Course Batches + Announcements admin oversight.
                     Gated by dashboard.view (any admin who sees the dashboard).
                     "Announcements" is further gated by announcement.view so the
                     "Announcement Manager" delegated role can land directly. --}}
                @if (checkAdminHasPermission('dashboard.view'))
                    <li class="dropdown {{ isRoute(['admin.batches.*','admin.batch-attendance.*','admin.announcements.*','admin.attendance-settings.*'], 'active') }}">
                        <a href="javascript:;" class="nav-link has-dropdown">
                            <i class="fas fa-bullhorn"></i><span>{{ __('Batches & Announcements') }}</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="{{ isRoute('admin.batches.index', 'active') }}">
                                <a href="{{ route('admin.batches.index') }}">{{ __('Course Batches') }}</a>
                            </li>
                            @if (checkAdminHasPermission('announcement.view'))
                                <li class="{{ isRoute('admin.announcements.*', 'active') }}">
                                    <a href="{{ route('admin.announcements.index') }}">{{ __('Announcements') }}</a>
                                </li>
                            @endif
                            {{-- Audit 2026-05-18 phase 5 — attendance threshold knob --}}
                            <li class="{{ isRoute('admin.attendance-settings.*', 'active') }}">
                                <a href="{{ route('admin.attendance-settings.show') }}">{{ __('Attendance Settings') }}</a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if (Module::isEnabled('CertificateBuilder') && checkAdminHasPermission('course.certificate.management'))
                    @include('certificatebuilder::sidebar')
                @endif

                @if (Module::isEnabled('Badges') && checkAdminHasPermission('badge.management'))
                    @include('badges::sidebar')
                @endif

                @if (Module::isEnabled('Blog'))
                    @include('blog::sidebar')
                @endif
            @endif

            @if (checkAdminHasPermission('order.management') ||
                    checkAdminHasPermission('coupon.management') ||
                    checkAdminHasPermission('withdraw.management'))
                <li class="menu-header">{{ __('Commerce') }}</li>

                @if (Module::isEnabled('Order') && checkAdminHasPermission('order.management'))
                    @include('order::sidebar')
                @endif

                @if (Module::isEnabled('Coupon') && checkAdminHasPermission('coupon.management'))
                    @include('coupon::sidebar')
                @endif

                @if (Module::isEnabled('PaymentWithdraw') && checkAdminHasPermission('withdraw.management'))
                    @include('paymentwithdraw::admin.sidebar')
                @endif

            @endif

            @if (checkAdminHasPermission('referral.view') ||
                    checkAdminHasPermission('order.management') ||
                    checkAdminHasPermission('trial-session.view'))
                <li class="menu-header">{{ __('Subscriptions') }}</li>

                @if (checkAdminHasPermission('referral.view'))
                    <li class="dropdown {{ isRoute('admin.referrals.*', 'active') }}">
                        <a href="javascript:;" class="nav-link has-dropdown"><i class="fas fa-gift"></i><span>{{ __('Referrals') }}</span></a>
                        <ul class="dropdown-menu">
                            <li class="{{ isRoute('admin.referrals.index', 'active') }}"><a href="{{ route('admin.referrals.index') }}">{{ __('All referrals') }}{!! adminMenuBadge($mbsCounts['referrals'] ?? null) !!}</a></li>
                            {{-- 2026-07-20 — referral commission review/payout had no menu entry. --}}
                            @if (Route::has('admin.referral-commissions.index'))
                                <li class="{{ isRoute('admin.referral-commissions.*', 'active') }}"><a href="{{ route('admin.referral-commissions.index') }}">{{ __('Referral Commissions') }}{!! adminMenuBadge($mbsCounts['referrals_review'] ?? null, true) !!}</a></li>
                            @endif
                            <li class="{{ isRoute('admin.referrals.settings', 'active') }}"><a href="{{ route('admin.referrals.settings') }}">{{ __('Settings') }}</a></li>
                        </ul>
                    </li>
                @endif


                {{-- 2026-07-20 — the Subscription module ships its own sidebar partial but
                     was never @included, so Subscriptions / Subscription History had no
                     menu entry at all. Same module + permission guard style as its peers. --}}
                @if (Module::isEnabled('Subscription') && checkAdminHasPermission('order.management'))
                    @include('subscription::sidebar')
                @endif

                {{-- 2026-07-03 — cross-coach Trial Session enquiries & payments --}}
                @if (checkAdminHasPermission('trial-session.view'))
                    <li class="{{ isRoute('admin.trial-sessions.*', 'active') }}">
                        <a href="{{ route('admin.trial-sessions.index') }}" class="nav-link">
                            <i class="fas fa-calendar-check"></i><span>{{ __('Trial Sessions') }}</span>
                        </a>
                    </li>
                @endif
            @endif

            @if (checkAdminHasPermission('theme.view') ||
                    checkAdminHasPermission('appearance.management') ||
                    checkAdminHasPermission('section.management') ||
                    checkAdminHasPermission('brand.management') ||
                    checkAdminHasPermission('footer.management') ||
                    checkAdminHasPermission('menu.view') ||
                    checkAdminHasPermission('page.management') ||
                    checkAdminHasPermission('social.link.management') ||
                    checkAdminHasPermission('faq.view') ||
                    checkAdminHasPermission('coach-landing-page.view') ||
                    checkAdminHasPermission('custom_domain.view'))
                <li class="menu-header">{{ __('Website') }}</li>

                {{-- Theme Studio — White-Label Theme Builder (theme.view). --}}
                @if (checkAdminHasPermission('theme.view'))
                    <li class="dropdown {{ isRoute('admin.themes.*', 'active') }}">
                        <a href="javascript:;" class="nav-link has-dropdown">
                            <i class="fas fa-palette"></i><span>{{ __('Theme Studio') }}</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="{{ isRoute('admin.themes.index', 'active') }}">
                                <a href="{{ route('admin.themes.index') }}">{{ __('All themes') }}</a>
                            </li>
                            @if (checkAdminHasPermission('theme.create'))
                                <li class="{{ isRoute('admin.themes.create', 'active') }}">
                                    <a href="{{ route('admin.themes.create') }}">{{ __('Create theme') }}</a>
                                </li>
                            @endif
                            <li class="{{ isRoute('admin.themes.categories.*', 'active') }}">
                                <a href="{{ route('admin.themes.categories.index') }}">{{ __('Categories') }}</a>
                            </li>
                        </ul>
                    </li>
                @endif

                {{-- `siteappearance::sidebar` was a 0-byte dead include — removed 2026-06-25. --}}

                @if (Module::isEnabled('Frontend') && checkAdminHasPermission('section.management'))
                    @include('frontend::sidebar')
                @endif

                @if (Module::isEnabled('Brand') && checkAdminHasPermission('brand.management'))
                    @include('brand::sidebar')
                @endif

                @if (Module::isEnabled('Brand') && checkAdminHasPermission('brand.management'))
                    @include('marquee::sidebar')
                @endif

                @if (Module::isEnabled('FooterSetting') && checkAdminHasPermission('footer.management'))
                    @include('footersetting::sidebar')
                @endif

                @if (Module::isEnabled('MenuBuilder') && checkAdminHasPermission('menu.view'))
                    @include('menubuilder::sidebar')
                @endif

                @if (Module::isEnabled('PageBuilder') && checkAdminHasPermission('page.management'))
                    @include('pagebuilder::sidebar')
                @endif

                @if (Module::isEnabled('PageTemplateBuilder') && checkAdminHasPermission('page.management'))
                    @include('pagetemplatebuilder::sidebar')
                @endif

                @if (Module::isEnabled('SocialLink') && checkAdminHasPermission('social.link.management'))
                    @include('sociallink::sidebar')
                @endif

                @if (Module::isEnabled('Faq') && checkAdminHasPermission('faq.view'))
                    @include('faq::sidebar')
                @endif


            @endif

            {{-- coach-landing-page.view admits Booking Enquiries, which moved here
                 from Overview; referral.view is no longer needed (Referrals moved
                 to Subscriptions) but stays harmless if a role only holds it. --}}
            @if (checkAdminHasPermission('newsletter.view') ||
                    checkAdminHasPermission('testimonial.view') ||
                    checkAdminHasPermission('contect.message.view') ||
                    checkAdminHasPermission('coach-landing-page.view'))
                <li class="menu-header">{{ __('Engagement') }}</li>

                {{-- 2026-07-14 — cross-coach booking enquiries (Pricing & Schedule) --}}
                @adminCan('coach-landing-page.view')
                    <li class="{{ isRoute('admin.booking-enquiries', 'active') }}">
                        <a class="nav-link" href="{{ route('admin.booking-enquiries') }}"><i class="fas fa-calendar-check"></i>
                            <span>{{ __('Booking Enquiries') }}</span>{!! adminMenuBadge($mbsCounts['booking_enq'] ?? null) !!}
                        </a>
                    </li>
                @endadminCan

                @if (Module::isEnabled('NewsLetter') && checkAdminHasPermission('newsletter.view'))
                    @include('newsletter::sidebar')
                @endif

                @if (Module::isEnabled('Testimonial') && checkAdminHasPermission('testimonial.view'))
                    @include('testimonial::sidebar')
                @endif

                @if (Module::isEnabled('ContactMessage') && checkAdminHasPermission('contect.message.view'))
                    @include('contactmessage::sidebar')
                @endif

                @if (Module::isEnabled('LandingPageMessage'))
                    @include('landingpagemessage::sidebar')
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
        'Overview': 'fa-th-large', 'Coaches': 'fa-chalkboard-teacher',
        'Students & Users': 'fa-users', 'Content': 'fa-book',
        'Commerce': 'fa-shopping-cart', 'Subscriptions': 'fa-id-card',
        'Website': 'fa-window-maximize', 'Engagement': 'fa-bullhorn',
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
