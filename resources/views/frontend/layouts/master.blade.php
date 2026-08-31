<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    {{-- 2026-05-21 P4 — per-coach white-label.
         $brand is auto-injected by the view composer + respects
         host-based tenant resolution (P2). Title + favicon now
         render coach1's brand when students visit coach1.com,
         platform brand otherwise. --}}
    <title>@yield('meta_title', $brand->name)</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', '')">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Custom Meta -->
    @stack('custom_meta')
    <!-- Favicon -->
    @if ($brand->faviconUrl())
        <link rel="shortcut icon" type="image/x-icon" href="{{ $brand->faviconUrl() }}">
    @endif
    <!-- CSS here -->
    @include('frontend.layouts.styles')
    <!-- CustomCSS here -->
    @stack('styles')
    @if (customCode()?->css)
        <style>
            {!! customCode()->css !!}
        </style>
    @endif

    {{-- Apply saved dark/light theme BEFORE the body renders to avoid theme flash. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('mbs-theme');
                if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            } catch (e) {}
        })();
    </script>

    {{-- 2026-07-10 (New Changes for UI #4) — application-wide Dark Mode CSS.
         Loaded in <head> (before paint, so no light flash) but ONLY on the
         Coach / Staff / Student dashboard surfaces, so the white-label public
         marketing site is never re-themed. --}}
    {{-- LMS removal phase 2 (2026-08-27) — added hr*/employee*: the HR surfaces
         are dashboards too and were missing the dark theme entirely. --}}
    @if (request()->is('instructor', 'instructor/*', 'student', 'student/*', 'hr', 'hr/*', 'employee', 'employee/*'))
        @include('frontend.layouts.partials._dashboard-dark')
    @endif

    {{-- dynamic header scripts --}}
    @include('frontend.layouts.header-scripts')

    {{-- LMS removal phase 2 (2026-08-27) — this used to call
         setEnrollmentIdsInSession() and setInstructorCourseIdsInSession(), two
         LMS helpers that queried enrollments/courses on EVERY page render,
         including every HR and payroll screen. Both are gone with the LMS. --}}
</head>

<body>
    @if ($setting->google_tagmanager_status == 'active')
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $setting->google_tagmanager_id }}"
                height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
    @endif

    @if ($setting->preloader_status == 1)
        <!--Preloader-->
        <div id="preloader">
            <div id="loader" class="loader">
                <div class="loader-container">
                    <div class="loader-icon"><img src="{{ asset($setting->preloader) }}" alt="Preloader">
                    </div>
                </div>
            </div>
        </div>
        <!--Preloader-end -->
    @endif

    <!-- Scroll-top -->
    <button class="scroll__top scroll-to-target" data-target="html" aria-label="Scroll Top">
        <i class="tg-flaticon-arrowhead-up"></i>
    </button>
    <!-- Scroll-top-end-->

    <!-- header-area -->

    {{-- LMS removal phase 2 (2026-08-31) — removed the public marketing header
         include entirely (both the coach-branded and platform variants —
         frontend/layouts/coach-public-header.blade.php and
         frontend/layouts/header.blade.php are both deleted). This layout's
         only remaining direct consumers are the standalone auth-card pages
         (login/register/forgot-password/reset-password/2FA) and the error
         pages, none of which need a nav header — each one is a self-contained
         centered card. Dashboards (/instructor/*, /student/*, /hr/*,
         /employee/*) render their own topbar via
         frontend/layouts/partials/dashboard-topbar from their own master
         layouts and never reached this include anyway. --}}

    <!-- header-area-end -->

    <!-- main-area -->
    <main class="main-area fix">
        @yield('contents')
    </main>
    <!-- main-area-end -->

    <!-- modal-area -->
    @include('frontend.partials.modal')
    {{-- LMS removal phase 2 (2026-08-31) — removed the "add new section"
         modal include (it belonged to the deleted course-content builder;
         instructor.setting.* is the only surviving instructor* route and
         doesn't use it). --}}
    <!-- modal-area -->

    <!-- footer-area -->

    {{-- LMS removal phase 2 (2026-08-31) — removed the public marketing
         footer include (frontend/layouts/footer.blade.php is deleted, along
         with the coach global-footer variant). Same reasoning as the header
         above: every remaining consumer of this layout is a self-contained
         auth-card or error page. --}}

    <!-- footer-area-end -->


    <!-- JS here -->
    @include('frontend.layouts.scripts')

    <!-- Language Translation Variables -->
    @include('global.dynamic-js-variables')

    <!-- Page specific js -->
    @if (session('registerUser') && $setting->google_tagmanager_status == 'active' && $marketing_setting?->register)
        @php
            $registerUser = session('registerUser');
            session()->forget('registerUser');
        @endphp
        <script>
            $(function() {
                dataLayer.push({
                    'event': 'newStudent',
                    'student_info': @json($registerUser)
                });
            });
        </script>
    @endif
    @stack('scripts')
    @if (customCode()?->javascript)
        <script>
            "use strict";
            {!! customCode()->javascript !!}
        </script>
    @endif
</body>

</html>
