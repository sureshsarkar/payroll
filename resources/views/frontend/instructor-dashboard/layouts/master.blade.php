@extends('frontend.layouts.master')

<!-- meta -->
@section('meta_title', __(panelModuleTitle($metadta['title'] ?? null)) . ' | ' . ($brand->name ?? config('app.name')))
<!-- end meta -->

@section('contents')
    {{-- Modern dashboard topbar (+, share, tasks, avatar, clock, bell, theme toggle) --}}
    @include('frontend.layouts.partials.dashboard-topbar')
    {{-- Command palette (Ctrl+K / Cmd+K) --}}
    @include('frontend.layouts.partials.command-palette')
    {{-- Auto-breadcrumb (path-derived) --}}
    @include('frontend.layouts.partials.auto-breadcrumb')

    <!-- breadcrumb-area -->
    {{-- <x-frontend.breadcrumb :title="__('')" :links="[]" /> --}}
    <!-- breadcrumb-area-end -->
<style>
    .pr-0{
        padding-right: 0px !important;
    }

    /* SECURITY/WHITE-LABEL (audit 2026-05-22)
       Coach brand-color override.
       The corp design system (_corporate.blade.php) defines --corp-brand:
       #10b981 platform-default. Here we re-declare those same custom
       properties at a HIGHER-specificity scope (.dashboard__aread) so
       every descendant element using var(--corp-brand) auto-retints to
       the current coach's primary color on white-label subdomains.
       Sites still using literal hex colors won't be touched — but every
       new component built on var(--corp-brand) is brand-aware for free.

       2026-05-22 sweep also replaces legacy `#10b981` (the OLDER
       platform purple from before _corporate landed) with
       var(--corp-brand) across 61 instructor-dashboard sites, making
       the analytics charts + landing-page-enquiry views + fees views
       all brand-aware. */
    @php
        /* 2026-07-04 — Coach-panel accent. Use the coach's OWN brand colour when
           they've actually set one (white-label context); otherwise the panel's
           EMERALD — matching the sidebar + logo — NOT the legacy purple platform
           theme colour. This is the "brand-aware, emerald fallback" the whole
           corporate skin now follows. */
        $__isPlatformBrand = $brand->isPlatformDefault ?? true;
        $__panelPrimary = $__isPlatformBrand ? '#10b981' : ($brand->primaryColor ?: '#10b981');
        $__panelAccent  = $__isPlatformBrand ? '#059669' : ($brand->accentColor  ?: '#059669');
    @endphp
    .dashboard__aread {
        --corp-brand:       {{ $__panelPrimary }};
        --corp-brand-2:     {{ $__panelAccent }};
        --corp-brand-deep:  color-mix(in srgb, {{ $__panelPrimary }} 68%, #000000);
        --corp-brand-bg:    color-mix(in srgb, {{ $__panelPrimary }} 10%, #ffffff);
        --corp-brand-border:color-mix(in srgb, {{ $__panelPrimary }} 40%, #ffffff);
        --corp-brand-grad:  linear-gradient(135deg, {{ $__panelPrimary }} 0%, {{ $__panelAccent }} 100%);
        --corp-brand-glow:  0 4px 14px -2px color-mix(in srgb, {{ $__panelPrimary }} 35%, transparent);
    }

    /* 2026-07-04 — Corporate polish. Legacy dashboard pages (course create/edit,
       coupons, fees, live classes, …) inherit the PUBLIC frontend theme, whose
       primary is purple — clashing with the emerald panel. Retint the common
       offenders to the brand accent. Scoped to .dashboard__aread so the public
       site is untouched. */
    .dashboard__aread .input-group-text:has(.file-manager-image),
    .dashboard__aread .input-group-text:has(.file-manager),
    .dashboard__aread .input-group-text#cloud-btn {
        background: var(--corp-brand) !important;
        border-color: var(--corp-brand) !important;
        color: #fff !important;
    }
    .dashboard__aread .input-group-text:has(.file-manager-image) a,
    .dashboard__aread .input-group-text:has(.file-manager) a,
    .dashboard__aread .file-manager-image,
    .dashboard__aread .file-manager { color: #fff !important; }

    .dashboard__aread .btn-primary,
    .dashboard__aread button.btn-primary,
    .dashboard__aread a.btn-primary {
        background: var(--corp-brand-grad) !important;
        border-color: transparent !important;
        color: #fff !important;
        box-shadow: 0 4px 14px -2px color-mix(in srgb, var(--corp-brand) 40%, transparent) !important;
    }
    .dashboard__aread .btn-primary:hover { filter: brightness(1.05); color:#fff !important; }
    .dashboard__aread .btn-outline-primary {
        color: var(--corp-brand-deep) !important; border-color: var(--corp-brand) !important;
    }
    .dashboard__aread .btn-outline-primary:hover {
        background: var(--corp-brand) !important; color:#fff !important;
    }

    .dashboard__aread .form-control:focus,
    .dashboard__aread .form-select:focus,
    .dashboard__aread .file-manager-input:focus {
        border-color: var(--corp-brand) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--corp-brand) 18%, transparent) !important;
    }

    .dashboard__aread .nav-tabs .nav-link.active {
        color: var(--corp-brand-deep) !important;
        border-bottom-color: var(--corp-brand) !important;
    }
    /* Bootstrap semantic helpers that render the theme purple as an accent */
    .dashboard__aread .text-primary { color: var(--corp-brand-deep) !important; }
    .dashboard__aread .bg-primary { background-color: var(--corp-brand) !important; }
    .dashboard__aread .badge.bg-primary { background-color: var(--corp-brand) !important; }
</style>
    <!-- dashboard-area -->
    <section class="dashboard__aread pb-4">
        <div class="container-fluid pt-3">
            {{-- <div class="dashboard__top-wrap">
                <div class="dashboard__top-bg"></div>
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
                                    <img src="{{ asset('frontend/img/icons/envelope.svg') }}" alt="img"
                                        class="injectable">
                                    {{ auth()->user()->email }}
                                </li>
                                @if (auth()->user()->phone)
                                    <li>
                                        <img src="{{ asset('frontend/img/icons/phone.svg') }}" alt="img"
                                            class="injectable">
                                        {{ auth()->user()->phone }}
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                    <div class="dashboard__instructor-info-right">
                        <a href="{{ route('student.dashboard') }}" class="btn btn-two arrow-btn">{{ __('Student Dashboard') }} <img
                            src="{{ asset('frontend/img/icons/right_arrow.svg') }}" alt="img" class="injectable"></a>
                    </div>
                </div>
            </div> --}}
            <div class="row">
                <div class="col-lg-2 pr-0 overflow--scroll">
                    @include('frontend.instructor-dashboard.layouts.sidebar')
                </div>
                <div class="col-lg-10 position-relative overflow--scroll">
                    <div class="preloader d-none">
                        <div class="loader-icon"><img src="{{ asset(Cache::get('setting')->preloader) }}" alt="Preloader">
                        </div>
                    </div>

                    @php
                        // Show the settings 2-col layout (left rail with section nav + right
                        // column for the page content) on every settings sub-page. Routes
                        // listed here mirror the items in settings/partials/side-nav.blade.php.
                        $isSettingsRoute = \Illuminate\Support\Facades\Route::is(
                            'instructor.setting.*',
                            'instructor.zoom-setting.*',
                            'instructor.youtube-setting.*',
                            'instructor.coach-staff.*',
                            'instructor.coach-staff-role.*',
                            'instructor.coach-staff-permission.*',
                            'instructor.brand-settings.*',
                            'instructor.website-builder.*',
                            'instructor.subscription-histories.*',
                            'instructor.payout.*',
                            'instructor.tax.*',
                            'instructor.email-templates.*',
                            'instructor.blogs.*',
                            'instructor.pricing-enquiries.*',
                            'instructor.trial-sessions.*',
                            'instructor.payment-gateways.*',
                            'membership.*',
                            'referral.*'
                        );
                    @endphp

                    @if ($isSettingsRoute)
                        <div class="row g-3">
                            <div class="col-lg-3 col-md-4 mb-3">
                                @include('frontend.instructor-dashboard.settings.partials.side-nav')
                            </div>
                            <div class="col-lg-9 col-md-8">
                                @yield('dashboard-contents')
                            </div>
                        </div>
                    @else
                        @yield('dashboard-contents')
                    @endif
                </div>
            </div>
        </div>
    </section>
    <!-- dashboard-area-end -->
@endsection
@push('scripts')
<script src="{{ asset('frontend/js/tinymce/js/tinymce/tinymce.min.js') }}"></script>
<script src="{{ asset('frontend/js/custom-tinymce.js') }}"></script>
@endpush
