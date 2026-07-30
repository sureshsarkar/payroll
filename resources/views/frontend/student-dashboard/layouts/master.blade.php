@extends('frontend.layouts.master')

<!-- meta -->
@section('meta_title', __(panelModuleTitle()) . ' | ' . ($brand->name ?? config('app.name')))
<!-- end meta -->

@section('contents')
    {{-- Modern dashboard topbar (+, share, tasks, avatar, clock, bell, theme toggle) --}}
    @include('frontend.layouts.partials.dashboard-topbar')
    {{-- Command palette (Ctrl+K / Cmd+K) --}}
    @include('frontend.layouts.partials.command-palette')
    {{-- Auto-breadcrumb (path-derived) --}}
    @include('frontend.layouts.partials.auto-breadcrumb')

    <!-- breadcrumb-area -->
    {{-- <x-frontend.breadcrumb
        :title="__('')"
        :links="[]"
    /> --}}
    <!-- breadcrumb-area-end -->

    {{-- 2026-06-01 responsive audit: below the lg breakpoint the
         sidebar/content columns stack, and the Bootstrap `.row` negative
         gutters (-15px) can sit a few px wider than the theme's reduced
         container padding. Clip just that gutter bleed on the shared
         dashboard section — `overflow-x: clip` (not hidden) so it does
         NOT create a scroll container and position:sticky descendants
         like the topbar keep working. The primary mobile overflow source
         (a global `header{position:absolute}` rule leaking onto the
         dashboard's <header>) is fixed at the element instead. Desktop
         (≥992px) is untouched. --}}
    <style>
        /* 2026-06-01 responsive audit — systematic fix for the global
           `header { position: absolute }` theme rule (meant for the public
           site's overlay nav) leaking onto dashboard page headers. Several
           student pages use a semantic <header class="…-header"> for their
           page title (sd-header, ec-header, orders/reviews/wishlist, …);
           each inherited absolute positioning, escaped flow, and caused a
           ~12px phantom horizontal page scroll on mobile. Re-assert normal
           flow for ANY <header> inside the dashboard content area. The
           public nav <header> lives OUTSIDE .dashboard__aread, and the
           sticky topbar is a sibling before it — both are unaffected. */
        .dashboard__aread header { position: relative; }

        @media (max-width: 991.98px) {
            .dashboard__aread { overflow-x: clip; }
        }
    </style>

    <!-- dashboard-area -->
    <section class="dashboard__aread  pb-4">
        <div class="container-fluid pt-3">
             {{--<div class="dashboard__top-wrap">
                <div class="dashboard__top-bg" data-background="{{ asset(auth()->user()->cover) }}"></div>
               <div class="dashboard__instructor-info">
                    <div class="dashboard__instructor-info-left">
                         <div class="thumb">
                            <img src="{{ asset(auth()->user()->image) }}" alt="img">
                        </div>
                          <div class="content">
                            <h4 class="title">{{ auth()->user()->name }}</h4>
                            <ul class="list-wrap">
                                <li>
                                    <img src="{{ asset('frontend/img/icons/envelope.svg') }}" alt="img" class="injectable">
                                    {{ auth()->user()->email }}
                                </li>
                                @if(auth()->user()->phone)
                                <li>
                                    <img  src="{{ asset('frontend/img/icons/phone.svg') }}" alt="img" class="injectable">
                                    {{ auth()->user()->phone }}
                                </li>
                                @endif

                            </ul>
                        </div>
                    </div>
                    <div class="dashboard__instructor-info-right">
                        @if (instructorStatus() == 'approved')
                        <a href="{{ route('instructor.dashboard') }}" class="btn btn-two arrow-btn">{{ __('Instructor Dashboard') }} <img
                            src="{{ asset('frontend/img/icons/right_arrow.svg') }}" alt="img" class="injectable"></a>
                        @elseif (instructorStatus() != 'pending')
                          <a href="{{ route('become-instructor') }}" class="btn btn-two arrow-btn">{{ __('Become an Instructor') }} <img
                            src="{{ asset('frontend/img/icons/right_arrow.svg') }}" alt="img" class="injectable"></a>
                        @endif
                    </div>
                </div>
            </div>--}}
            <div class="row">
                <div class="col-lg-3 pr-0">
                    @include('frontend.student-dashboard.layouts.sidebar')
                </div>
                <div class="col-lg-9">
                    @yield('dashboard-contents')
                </div>
            </div>
        </div>
    </section>
    <!-- dashboard-area-end -->
@endsection
