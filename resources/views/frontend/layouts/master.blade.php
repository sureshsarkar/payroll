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
    @if (request()->is('instructor', 'instructor/*', 'student', 'student/*', 'membership', 'membership/*', 'referral', 'referral/*'))
        @include('frontend.layouts.partials._dashboard-dark')
    @endif

    {{-- dynamic header scripts --}}
    @include('frontend.layouts.header-scripts')

    @php
        setEnrollmentIdsInSession();
        setInstructorCourseIdsInSession();
        $theme_name = session()->has('demo_theme') ? session()->get('demo_theme') : DEFAULT_HOMEPAGE;
    @endphp
</head>

<body class="{{ isRoute('home', "home_{$theme_name}") }}">
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

    {{-- Public header on non-dashboard pages. Dashboards (/instructor/*, /student/*)
         render their own modern topbar via frontend/layouts/partials/dashboard-topbar
         which is injected by their respective master layouts. --}}
    @if (!request()->is('instructor*') && !request()->is('student*') && !request()->is('notifications*') && !request()->is('referral*'))
        {{-- 2026-06-11 — On a coach domain (resolved_coach_id stamped by
             ResolveCoachByDomain) a platform-themed page like /course/{slug}
             must show the COACH's branded header, not the platform tgmenu with
             the platform nav. Swap only the header; the body keeps the platform
             theme it was built for. Platform domain (id 0) is unchanged. --}}
        @if ((int) request()->attributes->get('resolved_coach_id') > 0)
            @include('frontend.layouts.coach-public-header')
        @else
            @include('frontend.layouts.header')
        @endif
    @endif

    <!-- header-area-end -->

    <!-- main-area -->
    <main class="main-area fix">
        @yield('contents')
    </main>
    <!-- main-area-end -->

    <!-- modal-area -->
    @include('frontend.partials.modal')
    @if (request()->is('instructor*'))
        @include('frontend.instructor-dashboard.course.partials.add-new-section-modal')
    @endif
    <!-- modal-area -->

    <!-- footer-area -->

      @if (!request()->is('instructor*') && !request()->is('student*') && !request()->is('notifications*') && !request()->is('referral*'))
        {{-- 2026-07-16 — On a coach domain, show the coach's GLOBAL footer (same
             as every other coach page) instead of the platform footer, so pages
             like /course/{slug} stay consistent. Falls back to the platform
             footer on the platform domain (id 0) or when the coach set none. --}}
        @if ((int) request()->attributes->get('resolved_coach_id') > 0 && !empty($globalFooterHtml))
            {!! $globalFooterHtml !!}
        @else
            @include('frontend.layouts.footer')
        @endif
    @endif
 
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
