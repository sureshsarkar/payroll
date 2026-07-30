{{-- MBS unified typography (Plus Jakarta Sans). Loads first so component CSS still wins per-rule but fonts cascade consistently. --}}
<link rel="stylesheet" href="{{ asset('frontend/css/mbs-typography.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/animate.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/magnific-popup.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/fontawesome-all.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/flaticon-mbs.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/swiper-bundle.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/default-icons.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/odometer.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/aos.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/plyr.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/spacing.css') }}">
@if ($setting?->cursor_dot_status == 'active')
    <link rel="stylesheet" href="{{ asset('frontend/css/tg-cursor.css') }}">
@endif
<link rel="stylesheet" href="{{ asset('frontend/css/bootstrap-datepicker.min.css') }}">
<link rel="stylesheet" href="{{ asset('global/toastr/toastr.min.css') }}">
<link rel="stylesheet" href="{{ asset('global/nice-select/nice-select.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/main.min.css') }}?v={{ $setting?->version }}">
<link rel="stylesheet" href="{{ asset('frontend/css/frontend.min.css') }}?v={{ $setting?->version }}">

@if (Session::has('text_direction') && Session::get('text_direction') == 'rtl')
    <!-- RTL CSS -->
    <link rel="stylesheet" href="{{ asset('frontend/css/rtl.css') }}?v={{ $setting?->version }}">
@endif

{{-- 2026-05-29 UI/UX audit P0-3 + P0-7 — touch targets + table scroll indicator.
     Same file lives under public/backend/css/ and is reused across admin +
     frontend chrome; loading from a single location keeps cache hits high. --}}
<link rel="stylesheet" href="{{ asset('backend/css/ui-audit-2026-05.css') }}?v={{ $setting?->version ?? '1' }}">

{{-- Dynamic root colors --}}
<style>
    :root {
        {{-- 2026-05-21 P4 — per-coach white-label.
             Primary/secondary now come from $brand which composes
             coach overrides + platform defaults. Common colors stay
             platform-level (they're not in the coach brand profile
             yet — Phase 4b candidate if needed). --}}
        --tg-theme-primary: {{ $brand->primaryColor }};
        --tg-theme-secondary: {{ $brand->accentColor }};
        --tg-common-color-blue: {{ $setting->common_color_one }};
        --tg-common-color-blue-2: {{ $setting->common_color_two }};
        --tg-common-color-dark: {{ $setting->common_color_three }};
        --tg-common-color-black: {{ $setting->common_color_four }};
        --tg-common-color-dark-2: {{ $setting->common_color_five }};
    }
</style>
