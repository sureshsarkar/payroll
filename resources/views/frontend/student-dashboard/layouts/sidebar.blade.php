<style>
/* ============================================
   STUDENT DASHBOARD SIDEBAR — Professional UI
   ============================================ */
 
:root {
    --sidebar-bg-bar: #083083;
    --sidebar-width-bar: 270px;
    --accent-bar: #dddcff;
    --accent-bar-soft: rgba(79, 142, 247, 0.12);
    --accent-bar-glow: rgba(79, 142, 247, 0.25);
    --text-primary-bar: #e8edf5;
    --text-light-bar: #fff;
    --text-muted-bar: #6b7a99;
    --text-label-bar: #d2d2d2;
    --border-bar: rgba(255,255,255,0.06);
    --hover-bg-bar: #00297d;
    --active-bg-bar: #ffffff;
    --active-border-bar: #dddcff;
    --danger-bar: #ff6b6b;
    --radius-bar: 10px;
}

.student-sidebar {
    font-family: 'Plus Jakarta Sans', sans-serif;
    width:100%;/* var(--sidebar-width-bar);*/
    background: var(--sidebar-bg-bar);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    padding: 0;
    border-right: 1px solid var(--border-bar);
    position: relative;
    overflow: hidden;
    border-top-left-radius: 14px;
    border-bottom-left-radius: 14px;
}


/* Scrollbar width */
::-webkit-scrollbar {
    width: 8px;
}

/* Scrollbar track */
::-webkit-scrollbar-track {
    background: #f1f1f1;
}

/* Scrollbar thumb */
::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg,#0d6efd,#0044cc);
    border-radius: 10px;
}

/* Hover effect */
::-webkit-scrollbar-thumb:hover {
    background: #0b5ed7;
}
/* Firefox */
html {
    scrollbar-width: thin;
    scrollbar-color: #0d6efd #f1f1f1;
}

.dashboard__aread{
background: #dbe7ff;
}
.dashboard__sidebar-menu a:hover{
    color: #ffffff !important;
}

.dashboard__sidebar-menu .active a:hover{
    color: #10b981 !important;
}

/* Subtle background texture */
.student-sidebar::before {
    content: '';
    position: absolute;
    top: -80px;
    left: -80px;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(79,142,247,0.08) 0%, transparent 70%);
    pointer-events: none;
}

/* ── Profile Header ── */
.sidebar-profile {
    padding: 28px 24px 20px;
    border-bottom: 1px solid var(--border-bar);
}

.sidebar-profile-inner {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #dddcff, #1f56c6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    letter-spacing: -0.5px;
}

.sidebar-profile-info {
    min-width: 0;
}

.sidebar-greeting {
    font-size: 10.5px;
    font-weight: 500;
    color: var(--text-label-bar);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 2px;
}

.sidebar-username {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary-bar);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-badge {
    display: inline-flex;
    align-items: center;
    background: rgba(79, 142, 247, 0.15);
    color: var(--accent-bar);
    border: 1px solid rgba(79,142,247,0.3);
    border-radius: 20px;
    font-size: 9.5px;
    font-weight: 700;
    padding: 2px 8px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    margin-top: 4px;
}

/* ── Navigation ── */
.sidebar-nav-section {
    padding: 10px 16px 0px 8px;
}

.sidebar-nav-label {
    font-size: 10px;
    font-weight: 700;
    color: var(--text-label-bar);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 0 8px;
    margin-bottom: 6px;
}

.sidebar-nav ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.sidebar-nav ul li a {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 12px;
    border-radius: var(--radius-bar);
    font-size: 13.5px;
    font-weight: 500;
    color: var(--text-muted-bar);
    text-decoration: none;
    transition: all 0.18s ease;
    position: relative;
    border: 1px solid transparent;
}

.sidebar-nav ul li a:hover {
    background: var(--hover-bg-bar);
    color: var(--text-light-bar);
    border-color: var(--border-bar);
}

.sidebar-nav ul li a img,
.sidebar-nav ul li a i {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    opacity: 0.55;
    font-size: 17px;
    transition: opacity 0.18s;
    filter: brightness(0) invert(1);
}

.sidebar-nav ul li a:hover img,
.sidebar-nav ul li a:hover i {
    opacity: 0.85;
}

/* Active state */
.sidebar-nav ul li.active a {
    background: var(--active-bg-bar);
    color: var(--accent-bar);
    border-color: rgba(79,142,247,0.2);
    font-weight: 600;
}

.sidebar-nav ul li.active a img,
.sidebar-nav ul li.active a i {
    opacity: 1;
    filter: brightness(0) saturate(100%) invert(47%) sepia(96%) saturate(529%) hue-rotate(195deg) brightness(103%) contrast(97%);
}

/* Active left indicator */
.sidebar-nav ul li.active a::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 60%;
    background: var(--tg-theme-primary);
    border-radius: 0 3px 3px 0;
}

/* Divider between sections */
.sidebar-divider {
    height: 1px;
    background: var(--border-bar);
    margin: 8px 16px;
}

/* Logout special */
.sidebar-nav ul li a.logout-link {
    color: #ff6b6b;
    opacity: 0.7;
}
.sidebar-nav ul li a.logout-link:hover {
    background: rgba(255,107,107,0.1);
    border-color: rgba(255,107,107,0.2);
    color: #ff6b6b;
    opacity: 1;
}
.sidebar-nav ul li a.logout-link img {
    filter: brightness(0) saturate(100%) invert(50%) sepia(86%) saturate(450%) hue-rotate(314deg) brightness(110%) contrast(97%);
    opacity: 0.7;
}
.sidebar-nav ul li a.logout-link:hover img {
    opacity: 1;
}

/* ── Footer ── */
.sidebar-footer {
    margin-top: auto;
    padding: 16px;
    border-top: 1px solid var(--border-bar);
}

.sidebar-footer-text {
    font-size: 11px;
    color: var(--text-label-bar);
    text-align: center;
}

.dashboard__sidebar-menu .list-wrap li{
    border-bottom: none;
    padding-bottom: 5px !important;
    margin: 0px !important;
}
.fa-film{
margin-top: 10px;
}

.sb-pill {
    margin-left: auto;
    padding: 2px 7px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 700;
}
.sb-pill.red {
    background: rgba(248, 113, 113, 0.13);
    color: #f87171;
    border: 1px solid rgba(248, 113, 113, 0.2);
}

.pr-0 {
    padding-right: 0px !important;
}


/* mobile frindly css start  */
/* ── Hamburger Toggle ── */
.sidebar-toggle {
    display: none;
    position: fixed;
    top: 7px; left: 7px;
    z-index: 1001;
    background: var(--sidebar-bg-bar);
    border: none;
    border-radius: 10px;
    width: 35px; height: 35px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(8,48,131,0.3);
    transition: 0.28s cubic-bezier(0.4,0,0.2,1);
}
.sidebar-toggle span {
    display: block;
    width: 20px; height: 2px;
    background: #fff;
    border-radius: 2px;
    transition: 0.28s cubic-bezier(0.4,0,0.2,1);
}
.sidebar-toggle.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.sidebar-toggle.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.sidebar-toggle.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── Overlay ── */
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(5,20,60,0.55);
    backdrop-filter: blur(2px);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.28s ease;
    pointer-events: none;
}
.sidebar-overlay.active { opacity: 1; pointer-events: all; }

/* ── Bottom Nav ── */
.bottom-nav {
    display: none;
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: 64px;
    background: var(--sidebar-bg-bar);
    border-top: 1px solid var(--border-bar);
    z-index: 998;
    justify-content: space-around;
    align-items: center;
    box-shadow: 0 -4px 20px rgba(8,48,131,0.2);
    padding-bottom: 8px;
}
.bottom-nav-item {
    display: flex; flex-direction: column; align-items: center;
    gap: 3px; padding: 8px 10px; border-radius: 10px;
    text-decoration: none; color: var(--text-muted-bar);
    transition: all 0.18s; flex: 1; position: relative;
    font-size: 9.5px; font-weight: 600;
}
.bottom-nav-item i, .bottom-nav-item img {
    font-size: 20px; width: 20px; height: 20px; opacity: 0.6;
    filter: brightness(0) invert(1);
}
.bottom-nav-item.active { color: var(--accent-bar); border-bottom: 2px solid #fff; }
.bottom-nav-item.active i, .bottom-nav-item.active img { opacity: 1; }
.bottom-nav-badge {
    position: absolute; top: 4px; right: 10px;
    background: #f87171; color: #fff;
    border-radius: 10px; font-size: 8px; font-weight: 800;
    padding: 1px 5px; border: 1.5px solid var(--sidebar-bg-bar);
}

/* ── MOBILE BREAKPOINT ── */


@media (max-width: 991.98px) {
    .comman-div-containter {
        margin-top: 40px;
    }
}
@media (max-width: 768px) {
    body { padding-bottom: 64px; }

    .sidebar-toggle { display: flex; }
    .sidebar-overlay { display: block; }

    .student-sidebar {
        position: fixed;
        top: 0; left: 0;
        height: 100dvh;
        z-index: 1000;
        border-radius: 0 14px 14px 0;
        transform: translateX(-100%);
        transition: transform 0.28s cubic-bezier(0.4,0,0.2,1);
        box-shadow: 4px 0 40px rgba(8,48,131,0.3);
        min-height: unset;
        /* 2026-06-04 — the mobile drawer is a fixed 100dvh box; without a
           scroll a long menu pushed the Logout link (last item) below the
           fold, so it was unreachable on phones (the coach drawer already
           scrolls). Make it scroll + pad the bottom so Logout is always
           reachable. */
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 28px;
    }
    .student-sidebar.open { transform: translateX(0); }

    .bottom-nav { display: flex; }

    /* Push content down below toggle button */
    .dashboard__area { padding-top: 64px; }
}
/* mobile frindly css end   */

</style>

<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
    <span></span><span></span><span></span>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>


<aside class="student-sidebar dashboard__sidebar-wrap11 top-0" id="studentSidebar">

    {{-- Profile Header --}}
    <div class="sidebar-profile">
        <div class="sidebar-profile-inner">
            <div class="sidebar-avatar">
                {{ strtoupper(substr(userAuth()->name, 0, 1)) }}
            </div>
            <div class="sidebar-profile-info">
                <div class="sidebar-greeting">{{ __('Welcome back') }}</div>
                <div class="sidebar-username">{{ userAuth()->name }}</div>
                <div class="sidebar-badge">Student</div>
            </div>
        </div>
    </div>

    {{-- Main Navigation --}}
    <div class="sidebar-nav-section">
        <div class="sidebar-nav-label">{{ __('Main Menu') }}</div>
        <nav class="sidebar-nav dashboard__sidebar-menu">
            <ul class="list-wrap">
                <li class="{{ Route::is('student.dashboard') ? 'active' : '' }} m-0">
                    <a href="{{ route('student.dashboard') }}">
                        <img src="{{ asset('uploads/website-images/dashboard.svg') }}" alt="">
                        {{ __('Dashboard') }}
                    </a>
                </li>
                 <li class="{{ Route::is('student.enrolled-courses') ? 'active' : '' }} m-0">
                    <a href="{{ route('student.enrolled-courses') }}">
                        <i class="flaticon-mortarboard"></i>
                        {{ __('My Courses') }}
                    </a>
                </li>
                  <li class="{{ Route::is('student.live-classes.index') ? 'active' : '' }} m-0">
                    <a href="{{ route('student.live-classes.index') }}">
                        <i class="fas fa-film"></i>
                        {{ __('Live Classes') }}
                        <span class="sb-pill red">{{ $totalStudentUpcomingLive??0 }}  Live </span>
                    </a>
                </li>
                {{-- Audit 2026-05-18 Req 1 — student sees their own attendance --}}
                <li class="{{ Route::is('student.attendance.index') ? 'active' : '' }} m-0">
                    <a href="{{ route('student.attendance.index') }}">
                        <i class="fas fa-clipboard-check"></i>
                        {{ __('My Attendance') }}
                    </a>
                </li>
                {{-- Phase 4C 2026-05-19 — Fee Management student view --}}
                <li class="{{ Route::is('student.fees.*') ? 'active' : '' }} m-0">
                    <a href="{{ route('student.fees.index') }}">
                        <i class="fas fa-coins" style="color:#f59e0b;"></i>
                        {{ __('My Fees') }}
                    </a>
                </li>
                {{-- 2026-05-20 — Student announcements (Phase E) --}}
                <li class="{{ Route::is('student.announcements.*') ? 'active' : '' }} m-0">
                    <a href="{{ route('student.announcements.index') }}">
                        <i class="fas fa-bullhorn" style="color:#10b981;"></i>
                        {{ __('Announcements') }}
                    </a>
                </li>
                <li class="{{ Route::is('student.orders.index') ? 'active' : '' }} m-0">
                    <a href="{{ route('student.orders.index') }}">
                        <img src="{{ asset('uploads/website-images/order-history.svg') }}" alt="">
                        {{ __('Order History') }}
                    </a>
                </li>
               
                <li class="{{ Route::is('student.wishlist') ? 'active' : '' }} m-0">
                    <a href="{{ route('student.wishlist') }}">
                        <img src="{{ asset('uploads/website-images/heart.svg') }}" alt="">
                        {{ __('Wishlist') }}
                    </a>
                </li>
                <li class="{{ Route::is('student.reviews.index') ? 'active' : '' }} m-0">
                    <a href="{{ route('student.reviews.index') }}">
                        <img src="{{ asset('uploads/website-images/reviews.svg') }}" alt="">
                        {{ __('Reviews') }}
                    </a>
                </li>
                {{-- Audit 2026-05-18 Req 2 — membership hidden from student panel.
                     Students buy courses directly; no membership subscription concept
                     on the student side. The route still exists for backwards
                     compatibility with bookmarks, but the menu entry is removed.
                <li class="{{ Route::is('membership.*') ? 'active' : '' }} m-0">
                    <a href="{{ route('membership.index') }}">
                        <i class="fas fa-shield-alt"></i>
                        {{ __('Membership') }}
                    </a>
                </li>
                --}}
                <li class="{{ Route::is('referral.*') ? 'active' : '' }} m-0">
                    <a href="{{ route('referral.index') }}">
                        <i class="fas fa-gift"></i>
                        {{ __('Refer & Earn') }}
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    {{-- <div class="sidebar-divider"></div> --}}

    {{-- User Section --}}
    <div class="sidebar-nav-section" style="padding-top:12px;">
        <div class="sidebar-nav-label">{{ __('Account') }}</div>
        <nav class="sidebar-nav dashboard__sidebar-menu">
            <ul class="list-wrap">
                <li class="{{ Route::is('student.setting.index') ? 'active' : '' }}">
                    <a href="{{ route('student.setting.index') }}">
                        <i class="flaticon-user"></i>
                        {{ __('Profile Settings') }}
                    </a>
                </li>
                <li>
                    <a href="{{ route('logout') }}" class="logout-link"
                        onclick="event.preventDefault(); $('#logout-form').trigger('submit');">
                        <img src="{{ asset('uploads/website-images/logout.svg') }}" alt="">
                        {{ __('Logout') }}
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-footer-text">Student Portal v2.0</div>
    </div>

</aside>


{{-- mobile menu start   --}}
<nav class="bottom-nav">
    <a href="{{ route('student.dashboard') }}" class="bottom-nav-item {{ Route::is('student.dashboard') ? 'active' : '' }}">
        <img src="{{ asset('uploads/website-images/dashboard.svg') }}" alt=""> Dashboard
    </a>
    <a href="{{ route('student.enrolled-courses') }}" class="bottom-nav-item {{ Route::is('student.enrolled-courses') ? 'active' : '' }}">
        <i class="flaticon-mortarboard"></i> Courses
    </a>
    <a href="{{ route('student.live-classes.index') }}" class="bottom-nav-item {{ Route::is('student.live-classes.index') ? 'active' : '' }}">
        <i class="fas fa-film"></i> Live
        @if(($totalStudentUpcomingLive ?? 0) > 0)
            <span class="bottom-nav-badge">{{ $totalStudentUpcomingLive }}</span>
        @endif
    </a>
    <a href="{{ route('student.wishlist') }}" class="bottom-nav-item {{ Route::is('student.wishlist') ? 'active' : '' }}">
        <img src="{{ asset('uploads/website-images/heart.svg') }}" alt=""> Wishlist
    </a>
    <a href="{{ route('student.setting.index') }}" class="bottom-nav-item {{ Route::is('student.setting.index') ? 'active' : '' }}">
        <i class="flaticon-user"></i> Profile
    </a>
</nav>
{{-- mobile menu end  --}}

<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
    @csrf
</form>



<script>
const toggleMenuT = document.getElementById('sidebarToggle');
const sidebarMob = document.getElementById('studentSidebar');
const overlayMob = document.getElementById('sidebarOverlay');

const open = () => { sidebarMob.classList.add('open'); overlayMob.classList.add('active'); toggleMenuT.classList.add('open'); document.body.style.overflow = 'hidden'; };
const close = () => { sidebarMob.classList.remove('open'); overlayMob.classList.remove('active'); toggleMenuT.classList.remove('open'); document.body.style.overflow = ''; };

toggleMenuT.addEventListener('click', () => sidebarMob.classList.contains('open') ? close() : open());
overlayMob.addEventListener('click', close);
sidebarMob.querySelectorAll('a').forEach(a => a.addEventListener('click', () => { if (window.innerWidth <= 768) close(); }));
</script>