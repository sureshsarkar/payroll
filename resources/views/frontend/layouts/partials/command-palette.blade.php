{{--
    Command palette — press Ctrl+K / Cmd+K to open.
    Fuzzy search across role-aware quick actions. Keyboard-first navigation.
--}}
@auth('web')
@php
    $u = Auth::guard('web')->user();
    $isCoach = $u->role === 'instructor';

    // Curated command list (role-aware). { label, hint, url, icon }
    // LMS removal phase 2 (2026-08-27) — the palette used to list course /
    // batch / order / website-builder commands. Those routes no longer exist;
    // this is now the HR & Payroll surface for coaches (HR) and employees.
    $cmds = $isCoach ? [
        ['Dashboard',          'Home',            route('hr.dashboard'),              'fa-home'],
        ['Employees',          'Directory',       route('hr.employees.index'),        'fa-users'],
        ['Add Employee',       'Create',          route('hr.employees.create'),       'fa-user-plus'],
        ['Departments',        'Teams',           route('hr.departments.index'),      'fa-sitemap'],
        ['Attendance Sheet',   'Monthly grid',    route('hr.attendance.sheet'),       'fa-calendar-check'],
        ['Team Attendance',    'Today',           route('hr.attendance.team'),        'fa-user-clock'],
        ['Leave Requests',     'Approve / reject',route('hr.leave.index'),            'fa-plane-departure'],
        ['Payroll Runs',       'Prepare & submit',route('hr.payroll.index'),          'fa-money-check-alt'],
        ['Salary Structures',  'CTC templates',   route('hr.salary.index'),           'fa-file-invoice-dollar'],
        ['Companies',          'Establishments',  route('hr.companies.index'),        'fa-building'],
        ['Profile Settings',   'Account',         route('instructor.setting.index'),  'fa-cog'],
        ['Notifications',      'Bell inbox',      route('notifications.index'),       'fa-bell'],
        ['Toggle Dark Mode',   'Theme',           '#toggle-dark',                     'fa-moon'],
        ['Logout',             'Sign out',        '#logout',                          'fa-sign-out-alt'],
    ] : [
        ['Dashboard',          'Home',            route('employee.overview'),         'fa-home'],
        ['My Attendance',      'Attendance sheet',route('employee.attendance.my'),     'fa-calendar-check'],
        ['My Leave',           'Requests',        route('employee.leave.index'),      'fa-plane-departure'],
        ['My Payslips',        'Download',        route('employee.payslips.index'),   'fa-file-invoice-dollar'],
        ['Profile Settings',   'Account',         route('student.setting.index'),     'fa-cog'],
        ['Notifications',      'Bell inbox',      route('notifications.index'),       'fa-bell'],
        ['Toggle Dark Mode',   'Theme',           '#toggle-dark',                     'fa-moon'],
        ['Logout',             'Sign out',        '#logout',                          'fa-sign-out-alt'],
    ];
@endphp

<div class="mbs-cmdk-backdrop" id="mbsCmdkBackdrop" hidden>
    <div class="mbs-cmdk" role="dialog" aria-label="Command palette">
        <div class="mbs-cmdk__search">
            <i class="fa fa-search"></i>
            <input type="text" id="mbsCmdkInput" placeholder="{{ __('Type to search…') }}"
                   autocomplete="off" spellcheck="false">
            <kbd>ESC</kbd>
        </div>
        <ul class="mbs-cmdk__list" id="mbsCmdkList">
            @foreach ($cmds as $c)
                <li class="mbs-cmdk__item" data-url="{{ $c[2] }}" data-label="{{ strtolower($c[0] . ' ' . $c[1]) }}">
                    <i class="fa {{ $c[3] }}"></i>
                    <span class="mbs-cmdk__label">{{ $c[0] }}</span>
                    <span class="mbs-cmdk__hint">{{ $c[1] }}</span>
                </li>
            @endforeach
        </ul>
        <div class="mbs-cmdk__footer">
            <span><kbd>↑</kbd><kbd>↓</kbd> {{ __('navigate') }}</span>
            <span><kbd>↵</kbd> {{ __('select') }}</span>
            <span><kbd>ESC</kbd> {{ __('close') }}</span>
        </div>
    </div>
</div>

<style>
    .mbs-cmdk-backdrop {
        position: fixed; inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
        z-index: 9999;
        display: flex; align-items: flex-start; justify-content: center;
        padding-top: 12vh;
    }
    .mbs-cmdk {
        background: #fff;
        width: 100%; max-width: 600px;
        border-radius: 14px;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
        overflow: hidden;
        font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
        animation: mbs-cmdk-in 120ms ease-out;
    }
    @keyframes mbs-cmdk-in { from { opacity: 0; transform: translateY(-12px); } to { opacity: 1; transform: translateY(0); } }
    .mbs-cmdk__search {
        display: flex; align-items: center; gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid #e5e7eb;
    }
    .mbs-cmdk__search i { color: #9ca3af; font-size: 14px; }
    .mbs-cmdk__search input {
        flex: 1;
        border: 0; outline: 0;
        background: transparent;
        font-size: 15px;
        color: #1f2937;
    }
    .mbs-cmdk__search kbd {
        background: #f3f4f6;
        color: #6b7280;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-family: 'SF Mono', Consolas, monospace;
    }
    .mbs-cmdk__list {
        list-style: none; margin: 0; padding: 8px;
        max-height: 60vh;
        overflow-y: auto;
    }
    .mbs-cmdk__item {
        display: flex; align-items: center; gap: 14px;
        padding: 10px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.1s;
    }
    .mbs-cmdk__item:hover, .mbs-cmdk__item.is-active {
        background: #f3f4f6;
    }
    .mbs-cmdk__item.is-active {
        background: #ede9fe;
    }
    .mbs-cmdk__item i {
        width: 20px; color: #10b981; font-size: 14px;
    }
    .mbs-cmdk__label {
        font-size: 14px; color: #1f2937; font-weight: 500;
    }
    .mbs-cmdk__hint {
        margin-left: auto;
        font-size: 12px; color: #9ca3af;
    }
    .mbs-cmdk__item.is-hidden { display: none; }
    .mbs-cmdk__footer {
        display: flex; gap: 16px;
        padding: 10px 16px;
        border-top: 1px solid #e5e7eb;
        font-size: 11px; color: #6b7280;
        background: #fafbfc;
    }
    .mbs-cmdk__footer kbd {
        background: #fff; border: 1px solid #e5e7eb;
        padding: 1px 5px; border-radius: 3px;
        font-family: inherit; font-size: 10px;
        margin: 0 4px 0 0;
    }
    /* Dark mode */
    [data-theme="dark"] .mbs-cmdk { background: #1e293b; }
    [data-theme="dark"] .mbs-cmdk__search { border-bottom-color: #334155; }
    [data-theme="dark"] .mbs-cmdk__search input { color: #f1f5f9; }
    [data-theme="dark"] .mbs-cmdk__search kbd { background: #334155; color: #cbd5e1; }
    [data-theme="dark"] .mbs-cmdk__item:hover, [data-theme="dark"] .mbs-cmdk__item.is-active { background: #334155; }
    [data-theme="dark"] .mbs-cmdk__label { color: #f1f5f9; }
    [data-theme="dark"] .mbs-cmdk__hint { color: #94a3b8; }
    [data-theme="dark"] .mbs-cmdk__footer { background: #0f172a; border-top-color: #334155; color: #94a3b8; }
    [data-theme="dark"] .mbs-cmdk__footer kbd { background: #1e293b; border-color: #334155; }
</style>

<script>
    (function () {
        const backdrop = document.getElementById('mbsCmdkBackdrop');
        const input = document.getElementById('mbsCmdkInput');
        const list = document.getElementById('mbsCmdkList');
        if (!backdrop || !input || !list) return;
        const items = Array.from(list.querySelectorAll('.mbs-cmdk__item'));
        let activeIdx = 0;

        function open() {
            backdrop.hidden = false;
            input.value = '';
            filter('');
            input.focus();
        }
        function close() {
            backdrop.hidden = true;
        }
        function filter(q) {
            const norm = q.toLowerCase().trim();
            let firstVisible = null;
            items.forEach((el, i) => {
                const match = !norm || el.dataset.label.includes(norm);
                el.classList.toggle('is-hidden', !match);
                if (match && firstVisible === null) firstVisible = i;
            });
            activeIdx = firstVisible ?? 0;
            updateActive();
        }
        function updateActive() {
            items.forEach((el, i) => el.classList.toggle('is-active', i === activeIdx && !el.classList.contains('is-hidden')));
            const active = items[activeIdx];
            if (active && !active.classList.contains('is-hidden')) {
                active.scrollIntoView({ block: 'nearest' });
            }
        }
        function visibleItems() { return items.filter(el => !el.classList.contains('is-hidden')); }
        function moveActive(delta) {
            const vis = visibleItems();
            if (!vis.length) return;
            const curIdx = vis.indexOf(items[activeIdx]);
            const newIdx = (curIdx + delta + vis.length) % vis.length;
            activeIdx = items.indexOf(vis[newIdx]);
            updateActive();
        }
        function execute(item) {
            const url = item.dataset.url;
            close();
            if (url === '#toggle-dark') {
                document.getElementById('mbsThemeToggle')?.click();
            } else if (url === '#logout') {
                document.getElementById('logout-form')?.submit();
            } else if (url) {
                window.location.href = url;
            }
        }

        // Global hotkey
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                if (backdrop.hidden) open(); else close();
                return;
            }
            if (backdrop.hidden) return;
            if (e.key === 'Escape') { e.preventDefault(); close(); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(-1); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                const active = items[activeIdx];
                if (active && !active.classList.contains('is-hidden')) execute(active);
            }
        });
        backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });
        input.addEventListener('input', (e) => filter(e.target.value));
        items.forEach(el => el.addEventListener('click', () => execute(el)));
    })();
</script>
@endauth
