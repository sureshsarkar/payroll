{{-- MBS unified typography (Plus Jakarta Sans). Loads first so cascade defaults are consistent across admin too. --}}
<link rel="stylesheet" href="{{ asset('frontend/css/mbs-typography.css') }}">
<link rel="stylesheet" href="{{ asset('backend/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('backend/fontawesome/css/all.min.css') }}">
<link rel="stylesheet" href="{{ asset('backend/css/style.css') }}?v={{$setting?->version}}">
<link rel="stylesheet" href="{{ asset('backend/css/bootstrap-social.css') }}">
<link rel="stylesheet" href="{{ asset('backend/css/components.css') }}?v={{$setting?->version}}">

<link rel="stylesheet" href="{{ asset('global/toastr/toastr.min.css') }}">
<link rel="stylesheet" href="{{ asset('backend/css/bootstrap4-toggle.min.css') }}">
<link rel="stylesheet" href="{{ asset('backend/css/dev.css') }}?v={{$setting?->version}}">
<link rel="stylesheet" href="{{ asset('backend/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('backend/css/tagify.css') }}">
<link rel="stylesheet" href="{{ asset('backend/css/bootstrap-tagsinput.css') }}">
<link rel="stylesheet" href="{{ asset('backend/css/fontawesome-iconpicker.min.css') }}">
<link rel="stylesheet" href="{{ asset('backend/css/bootstrap-datepicker.min.css') }}">
<link rel="stylesheet" href="{{ asset('backend/clockpicker/dist/bootstrap-clockpicker.css') }}">
<link rel="stylesheet" href="{{ asset('backend/datetimepicker/jquery.datetimepicker.css') }}">
<link rel="stylesheet" href="{{ asset('backend/css/iziToast.min.css') }}">
<link rel="stylesheet" href="{{ asset('global/nice-select/nice-select.css') }}">
@if (session()->has('text_direction') && session()->get('text_direction') !== 'ltr')
    <link rel="stylesheet" href="{{ asset('backend/css/rtl.css') }}?v={{$setting?->version}}">
    <link rel="stylesheet" href="{{ asset('backend/css/dev_rtl.css') }}?v={{$setting?->version}}">
@endif
{{-- 2026-05-29 UI/UX audit P0-3 + P0-7 — touch targets + table scroll indicator.
     Loaded last so its rules override Bootstrap defaults on mobile breakpoint. --}}
<link rel="stylesheet" href="{{ asset('backend/css/ui-audit-2026-05.css') }}?v={{ $setting?->version ?? '1' }}">
{{-- 2026-06-25 — Super-Admin LIGHT premium theme. Loaded LAST so it re-skins the
     dark sidebar to a clean corporate light surface. Scoped to .main-sidebar;
     remove this one line to fully revert to the dark theme.

     2026-07-20 — cache-bust on the FILE's mtime, not $setting->version. The
     version rarely changes, so browsers kept serving a stale copy after every
     CSS deploy ("deployed but nothing changed"). filemtime() changes the moment
     the file is re-uploaded, so the browser always refetches a changed file and
     never refetches an unchanged one. --}}
@php
    $mbsThemeCss = public_path('backend/css/admin-light-theme-2026-06.css');
    $mbsThemeVer = is_file($mbsThemeCss) ? filemtime($mbsThemeCss) : ($setting?->version ?? '1');
@endphp
<link rel="stylesheet" href="{{ asset('backend/css/admin-light-theme-2026-06.css') }}?v={{ $mbsThemeVer }}">