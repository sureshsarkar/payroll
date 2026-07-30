@php
    $header_admin = Auth::guard('admin')->user();
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    {{-- L-batch (2026-05-12) — removed the empty <link rel="shortcut icon" href="">.
         It was a duplicate of the populated `<link rel="icon">` below (using
         $setting->favicon), and an empty href issues a redundant request
         back to the current page that browsers log as a 200 on the same
         document. --}}
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Audit fix H7 (2026-05-12) — the `<meta name="mode">` tag used to
         expose env('PROJECT_MODE') (DEV/STAGING/LIVE) directly to the
         client. Tiny fingerprint that helps attackers confirm they're on
         prod. If any client-side code needs deployment mode, gate behind
         a server-side check instead. --}}
    <!-- Custom Meta -->
    @yield('custom_meta')

    @yield('title')
    <link rel="icon" href="{{ asset($setting->favicon) }}">
    @include('admin.partials.styles')
    @stack('css')
    @yield('vite')
</head>

<body>
    <div id="app">
        <div class="main-wrapper">
            <!-- <div class="navbar-bg"></div> -->
            <nav class="navbar navbar-expand-lg main-navbar">
                <div class="mr-2 form-inline">
                    <ul class="mr-3 navbar-nav d-flex align-items-center">
                        <li><a href="#" data-toggle="sidebar" class="nav-link nav-link-lg"><i
                                    class="fas fa-bars"></i></a></li>
                        {{-- Audit fix H1 + follow-on (2026-05-12) — page title is
                             yielded from @section('page-title','…') when the view
                             declares one, otherwise derived from the route name
                             via currentAdminPageTitle(). This means a new admin
                             page automatically gets a sensible title without
                             needing a per-view section. --}}
                        <h1 class="page-title-new">{{ __(currentAdminPageTitle(\View::yieldContent('page-title') ?: null)) }}</h1>

                    </ul>
                </div>
                {{-- M3 fix (2026-05-12) — the menu-search was discoverable only by
                     a tiny placeholder. Added a magnifying-glass icon prefix and
                     an aria-label so users (and screen readers) realize it's a
                     keyboard-driven nav shortcut. --}}
                <div class="ml-auto search-box position-relative" style="display:flex; align-items:center;">
                    <i class="fas fa-search" aria-hidden="true"
                       style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; font-size:13px;"></i>
                    <label for="search_menu" class="sr-only">{{ __('Search admin menu') }}</label>
                    <input type="text" id="search_menu" class="form-control"
                        style="padding-left:34px;"
                        placeholder="{{ __('Search options…') }}"
                        aria-label="{{ __('Search admin menu') }}"
                        autocomplete="off">
                    <div id="admin_menu_list" class="position-absolute d-none rounded-2">
                        @foreach (adminSearchRouteList() as $route_item)
                            @if (checkAdminHasPermission($route_item?->permission) || empty($route_item?->permission))
                                <a @isset($route_item->tab) 
                                        data-active-tab="{{ $route_item->tab }}" class="border-bottom search-menu-item" 
                                    @else 
                                        class="border-bottom" 
                                    @endisset
                                    href="{{ $route_item?->route }}">{{ $route_item?->name }}</a>
                            @endif
                        @endforeach
                        <a class="not-found-message d-none" href="javascript:;">{{ __('Not Found!') }}</a>
                    </div>
                </div>

                <ul class="navbar-nav navbar-right">


                     <li><a href="#" data-toggle="search" class="nav-link nav-link-lg d-none"><i
                                    class="fas fa-search"></i></a></li>
                        {{-- M13 fix (2026-05-12) — these dropdowns relied on JS to submit on change
                             (Select2 onchange handler). Without JS the user could see the dropdown
                             but selecting an option did nothing. Wrapped both in <noscript> fallback
                             that exposes a visible submit button, plus added aria-labels. --}}
                        @if (Module::isEnabled('Language') && Route::has('set-language'))
                            @if (count(allLanguages()?->where('status', 1)) > 1)
                                <form id="setLanguageHeader" action="{{ route('set-language') }}">
                                    <label for="set-language-select" class="sr-only">{{ __('Language') }}</label>
                                    <select id="set-language-select" class="bg-transparent form-control-sm border-light select_js"
                                        name="code" aria-label="{{ __('Language') }}">
                                        @forelse (allLanguages()?->where('status', 1) as $language)
                                            <option class="text-dark" value="{{ $language->code }}"
                                                {{ getSessionLanguage() == $language->code ? 'selected' : '' }}>
                                                {{ $language->name }}
                                            </option>
                                        @empty
                                            <option value="en" {{ getSessionLanguage() == 'en' ? 'selected' : '' }}>
                                                English
                                            </option>
                                        @endforelse
                                    </select>
                                    <noscript><button type="submit" class="btn btn-sm btn-light ml-1">{{ __('Set') }}</button></noscript>
                                </form>
                            @endif
                        @endif

                        @if (count(allCurrencies()?->where('status', 'active')) > 1)
                            <form action="{{ route('set-currency') }}" class="set-currency-header">
                                <label for="set-currency-select" class="sr-only">{{ __('Currency') }}</label>
                                <select id="set-currency-select" name="currency"
                                    class="change-currency bg-transparent form-control-sm border-light ml-2 select_js"
                                    aria-label="{{ __('Currency') }}">
                                    @forelse (allCurrencies()?->where('status', 'active') as $currency)
                                        <option class="text-dark" value="{{ $currency->currency_code }}"
                                            {{ getSessionCurrency() == $currency->currency_code ? 'selected' : '' }}>
                                            {{ $currency->currency_name }}
                                        </option>
                                    @empty
                                        <option value="USD" {{ getSessionCurrency() == 'USD' ? 'selected' : '' }}>
                                            {{ __('USD') }}
                                        </option>
                                    @endforelse
                                </select>
                                <noscript><button type="submit" class="btn btn-sm btn-light ml-1">{{ __('Set') }}</button></noscript>
                            </form>
                        @endif


                    <li class="dropdown dropdown-list-toggle">
                        <a target="_blank" href="{{ route('home') }}" class="nav-link nav-link-lg">
                            <i class="fas fa-home"></i> {{ __('Visit Website') }}</i>
                        </a>
                    </li>

                    {{-- Notification bell (auto-renders only for authenticated admin) --}}
                    @include('admin.partials.notification-bell')

                    <li class="dropdown"><a href="#" data-toggle="dropdown"
                            class="nav-link dropdown-toggle nav-link-lg nav-link-user">
                            {{-- 2026-07-21 — flat initial circle, matching the approved
                                 design's clean avatar. The theme CSS shows this span and
                                 hides the photo + name; remove the CSS to restore both. --}}
                            <span class="mbs-av-initial">{{ strtoupper(mb_substr($header_admin->name ?? 'A', 0, 1)) }}</span>
                            @if ($header_admin->image)
                                <img alt="{{ $header_admin->name }} {{ __('avatar') }}"
                                    src="{{ asset($header_admin->image) }}"
                                    class="mr-1 rounded-circle">
                            @else
                                <img alt="{{ $header_admin->name }} {{ __('avatar') }}"
                                    src="{{ asset('backend/img/avatar-1.png') }}"
                                    class="mr-1 rounded-circle">
                            @endif

                            {{-- 2026-06-01 responsive audit: was `d-sm-none
                                 d-lg-inline-block`, which (inverted) HID the
                                 name on tablets but SHOWED it below 576px,
                                 where the default display:block forced the
                                 user dropdown ~270px wide and overflowed the
                                 navbar on phones. `d-none d-lg-inline-block`
                                 correctly hides it until the lg breakpoint. --}}
                            <div class="d-none d-lg-inline-block">{{ $header_admin->name }}</div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a href="{{ route('admin.settings') }}" class="dropdown-item has-icon">
                                <i class="fas fa-cog"></i> {{ __('Settings') }}
                            </a>


                            @adminCan('admin.profile.view')
                                <a href="{{ route('admin.edit-profile') }}" class="dropdown-item has-icon">
                                    <i class="far fa-user"></i> {{ __('Profile') }}
                                </a>
                            @endadminCan
                            <a href="javascript:;" class="dropdown-item has-icon d-flex align-items-center text-danger"
                                onclick="event.preventDefault(); $('#admin-logout-form').trigger('submit');">
                                <i class="fas fa-sign-out-alt"></i> {{ __('Logout') }}
                            </a>
                        </div>
                    </li>

                </ul>
            </nav>

            {{-- Audit fix H5 (2026-05-12) — settings-vs-main sidebar swap
                 used to inline an 18-route hardcoded list here. New
                 settings pages silently used the wrong sidebar until
                 someone updated this block. List is now in
                 isAdminSettingsRoute() (see helper.php) where it can be
                 tested and where adding a new settings route is one line. --}}
            @if (isAdminSettingsRoute())
                @include('admin.settings.sidebar')
            @else
                @include('admin.sidebar')
            @endif
            @yield('admin-content')

            <footer class="main-footer">
                <div class="footer-left">
                    {{-- M14 fix (2026-05-12) — $setting is a globally bound Blade
                         variable provided by AppServiceProvider's view composer
                         (sourced from cache key "setting" / GlobalSetting row).
                         Future readers shouldn't have to grep to find where it
                         comes from. --}}
                    {{ $setting->copyright_text }}
                </div>

            </footer>

        </div>
    </div>

    {{-- start admin logout form --}}
    <form id="admin-logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
        @csrf
    </form>
    {{-- end admin logout form --}}
    @include('admin.partials.modal')
    @include('admin.partials.javascripts')
    @include('global.dynamic-js-variables')

    @stack('js')

</body>

</html>
