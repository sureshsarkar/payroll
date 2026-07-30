@php
    $nav_menu = menu_get_by_slug('nav-menu');
    $categories = \Modules\Course\app\Models\CourseCategory::with('translation')
        ->where('status', 1)
        ->whereNull('parent_id')
        ->get();
@endphp
<!-- header-area -->
<header class="inner-header">
    @if ($setting?->header_topbar_status == 'active')
        <div class="tg-header__top">
            <div class="container custom-container xl_container">
                <div class="row">
                    <div class="col-lg-6">
                        <ul class="tg-header__top-info list-wrap">
                            @if ($setting?->site_address)
                                <li><img src="{{ asset('frontend/img/icons/map_marker.svg') }}" alt="Icon">
                                    <span>{{ $setting?->site_address }}</span>
                                </li>
                            @endif
                            @if ($setting?->site_email)
                                <li><img src="{{ asset('frontend/img/icons/envelope.svg') }}" alt="Icon"> <a
                                        href="mailto:{{ $setting?->site_email }}">{{ $setting?->site_email }}</a>
                                </li>
                            @endif
                        </ul>
                    </div>
                    <div class="col-lg-6">
                        <div class="tg-header__top-right">
                            @if ($setting?->header_social_status == 'active')
                                <ul class="tg-header__top-social list-wrap">
                                    <li>{{ __('Follow Us On') }} :</li>
                                    @foreach (getSocialLinks() as $socialLink)
                                        <li class="header-social">
                                            <a href="{{ $socialLink->link }}" target="_blank">
                                                <img src="{{ asset($socialLink->icon) }}" alt="img">
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="header_language_area d-flex flex-wrap d-none d-xl-flex">

                                <ul>
                                    <li>
                                        @if (count(allLanguages()?->where('status', 1)) > 1)
                                            <form action="{{ route('set-language') }}" id="setLanguageHeader">
                                                <select name="code" class="select_js">
                                                    @forelse (allLanguages()?->where('status', 1) as $language)
                                                        <option value="{{ $language->code }}"
                                                            {{ getSessionLanguage() == $language->code ? 'selected' : '' }}>
                                                            {{ $language->name }}
                                                        </option>
                                                    @empty
                                                        <option value="en"
                                                            {{ getSessionLanguage() == 'en' ? 'selected' : '' }}>
                                                            {{ __('English') }}
                                                        </option>
                                                    @endforelse
                                                </select>
                                            </form>
                                        @endif
                                    </li>
                                    <li>
                                        @if (count(allCurrencies()?->where('status', 'active')) > 1)
                                            <form action="{{ route('set-currency') }}" class="set-currency-header"
                                                method="GET">
                                                <select name="currency" class="change-currency select_js">
                                                    @forelse (allCurrencies()?->where('status', 'active') as $currency)
                                                        <option value="{{ $currency->currency_code }}"
                                                            {{ getSessionCurrency() == $currency->currency_code ? 'selected' : '' }}>
                                                            {{ $currency->currency_name }}
                                                        </option>
                                                    @empty
                                                        <option value="USD"
                                                            {{ getSessionCurrency() == 'USD' ? 'selected' : '' }}>
                                                            {{ __('USD') }}
                                                        </option>
                                                    @endforelse
                                                </select>
                                            </form>
                                        @endif
                                    </li>
                                </ul>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <div id="header-fixed-height"></div>
    <div id="sticky-header" class="tg-header__area">
        <div class="container ">
            <div class="row">
                <div class="col-12">
                    <div class="tgmenu__wrap">
                        <nav class="tgmenu__nav">
                            <div class="logo">
                                {{-- <a href="{{ route('home') }}"><img src="{{ asset('frontend/img/logos.png') }}"
                                        alt="Logo"></a> --}}
                                <a href="{{ route('home') }}">
                                    <!--  <img src="{{ asset($setting->logo) ?? '' }}"
                                        alt="{{ $setting?->app_name }}"> -->
                                    <h2>MBSGuru</h2>
                                </a>
                            </div>
                            <div class="tgmenu__navbar-wrap tgmenu__main-menu d-none d-xl-flex">
                                @if ($nav_menu)
                                    <ul class="navigation">
                                        @foreach ($nav_menu->menuItems as $menu)
                                            @if ($menu?->link == '/' && $setting?->show_all_homepage == 1)
                                                <li class="menu-item-has-children">
                                                    <a href="{{ url('/') }}"
                                                        title="">{{ __('Home') }}</a> 
                                                </li>
                                            @else
                                                <li
                                                    class="{{ $menu->child && count($menu->child) ? 'menu-item-has-children' : '' }}">
                                                    <a href="{{ $menu->child && count($menu->child) ? 'javascript:;' : url($menu?->link) }}"
                                                        title="">{{ $menu?->label }}</a>
                                                    @if ($menu->child && count($menu->child))
                                                        <ul class="sub-menu">
                                                            @foreach ($menu?->child as $child)
                                                                <li class=""><a href="{{ url($child?->link) }}"
                                                                        title="">{{ $child?->label }}</a></li>
                                                            @endforeach
                                                        </ul><!-- /.sub-menu -->
                                                    @endif
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul><!-- /.menu -->
                                @endif

                            </div>
                             
                            <div class="tgmenu__action">
                                 <div class="nav-right">
                                    <a class="btn-ghost" href="https://api.whatsapp.com/send?phone=919560508412&text=Hi%21%20I%20want%20to%20book%20a%20demo%20for%20MBSGuru.%20Please%20share%20available%20time%20slots.">Demo</a>
                                    <a class="btn---primary" href="{{ route('login') }}">Get Started</a>
                                </div>

                                <ul class="list-wrap">

                                    {{-- Notification bell (auto-renders only for authenticated web users) --}}
                                    @include('frontend.layouts.partials.notification-bell')

                                    <li class="mini-cart-icon user_icon">
                                        <a href="javascript:;" class="cart-count">
                                            <i class="fa fa-user"></i>
                                        </a>
                                        <ul class="menu_user_list">

                                            {{-- ================= ADMIN ================= --}}
                                            @if (Auth::guard('admin')->check())
                                                <li>
                                                    <a href="{{ route('admin.dashboard') }}">
                                                        {{ __('Admin Dashboard') }}
                                                    </a>
                                                </li>

                                                {{-- <li>
                                                    <a href="javascript:;" class="text-danger logout-btn">
                                                        {{ __('Logout') }}
                                                    </a>
                                                </li> --}}

                                                {{-- ================= USER (WEB GUARD) ================= --}}
                                            @elseif (Auth::guard('web')->check())
                                                @php
                                                    $user = userAuth();
                                                    $role = $user->role;
                                                @endphp

                                                {{-- Instructor --}}
                                                @if ($role === 'instructor')
                                                    <li>
                                                        <a href="{{ route('instructor.dashboard') }}">
                                                            {{ __('Coach Dashboard') }}
                                                        </a>
                                                    </li>
                                                @endif

                                                {{-- Student --}}
                                                @if ($role === 'student')
                                                    <li>
                                                        <a href="{{ route('student.dashboard') }}">
                                                            {{ __('Student Dashboard') }}
                                                        </a>
                                                    </li>
                                                @endif

                                                {{-- Staff / Others --}}
                                                @if (!in_array($role, ['instructor', 'student']))
                                                    <li>
                                                        <a href="{{ route('instructor.dashboard') }}">
                                                            {{ __('Staff Dashboard') }}
                                                        </a>
                                                    </li>
                                                @endif

                                                {{-- Profile --}}
                                                <li>
                                                    <a
                                                        href="{{ $role === 'student' ? route('student.setting.index') : route('instructor.setting.index') }}">
                                                        {{ __('Profile') }}
                                                    </a>
                                                </li>

                                                {{-- Courses --}}
                                                <li>
                                                    <a
                                                        href="{{ $role === 'student' ? route('student.enrolled-courses') : route('instructor.courses.index') }}">
                                                        {{ __('Courses') }}
                                                    </a>
                                                </li>

                                                {{-- Logout --}}
                                                <li>
                                                    <a href="javascript:;" class="text-danger logout-btn">
                                                        {{ __('Logout') }}
                                                    </a>
                                                </li>

                                                {{-- ================= GUEST ================= --}}
                                            @else
                                                <li><a href="{{ route('login') }}">{{ __('Sign in') }}</a></li>
                                                <li><a href="{{ route('register') }}">{{ __('Sign Up') }}</a></li>
                                            @endif

                                        </ul>
                                    </li>
                                </ul>
                            </div>
                            <div class="mobile-nav-toggler"><i class="tg-flaticon-menu-1"></i></div>
                        </nav>
                    </div>

                    <!-- Mobile Menu  -->
                    <div class="tgmobile__menu">
                        <nav class="tgmobile__menu-box">
                            <div class="close-btn"><i class="tg-flaticon-close-1"></i></div>
                            <div class="nav-logo">
                                <a href="{{ route('home') }}"><img src="{{ asset(Cache::get('setting')->logo) }}"
                                        alt="Logo"></a>
                            </div>

                            <div class="header_language_area d-flex flex-wrap">

                                <ul>
                                    <li>
                                        @if (count(allLanguages()?->where('status', 1)) > 1)
                                            <form action="{{ route('set-language') }}"
                                                class="change-language-header-mobile" method="GET">
                                                <select name="code" class="select_js set-language-header-mobile">
                                                    @forelse (allLanguages()?->where('status', 1) as $language)
                                                        <option value="{{ $language->code }}"
                                                            {{ getSessionLanguage() == $language->code ? 'selected' : '' }}>
                                                            {{ $language->name }}
                                                        </option>
                                                    @empty
                                                        <option value="en"
                                                            {{ getSessionLanguage() == 'en' ? 'selected' : '' }}>
                                                            {{ __('English') }}
                                                        </option>
                                                    @endforelse
                                                </select>
                                            </form>
                                        @endif
                                    </li>
                                    <li>
                                        @if (count(allCurrencies()?->where('status', 'active')) > 1)
                                            <form action="{{ route('set-currency') }}"
                                                class="change-currency-header-mobile" method="GET">
                                                <select name="currency" class="set-currency-header-mobile select_js">
                                                    @forelse (allCurrencies()?->where('status', 'active') as $currency)
                                                        <option value="{{ $currency->currency_code }}"
                                                            {{ getSessionCurrency() == $currency->currency_code ? 'selected' : '' }}>
                                                            {{ $currency->currency_name }}
                                                        </option>
                                                    @empty
                                                        <option value="USD"
                                                            {{ getSessionCurrency() == 'USD' ? 'selected' : '' }}>
                                                            {{ __('USD') }}
                                                        </option>
                                                    @endforelse
                                                </select>
                                            </form>
                                        @endif
                                    </li>
                                </ul>
                            </div>
                            <ul class="mobile_menu_login d-flex flex-wrap">
                                @auth('admin')
                                    <li><a href="{{ route('admin.dashboard') }}">{{ __('Admin Dashboard') }}</a></li>
                                @endauth
                                @guest
                                    <li><a href="{{ route('login') }}">{{ __('login') }}</a></li>
                                    <li><a href="{{ route('register') }}">{{ __('register') }}</a></li>
                                @endguest

                                @auth('web')
                                    @php
                                        $user = Auth::guard('web')->user();
                                        $dashboardRoute =
                                            $user->role == 'instructor' ? 'instructor.dashboard' : 'student.dashboard';
                                        $coursesRoute =
                                            $user->role == 'instructor'
                                                ? 'instructor.courses.index'
                                                : 'student.enrolled-courses';
                                    @endphp
                                    <li><a href="{{ route($dashboardRoute) }}">{{ __('Dashboard') }}</a></li>
                                    <li><a href="{{ route($coursesRoute) }}">{{ __('Courses') }}</a></li>
                                @endauth
                            </ul>

                            <div class="tgmobile__search">
                                <form action="{{ route('courses') }}">
                                    <select class="form-select w_150px" aria-label="Default select example"
                                        name="main_category">
                                        <option selected disabled>{{ __('Categories') }}</option>
                                        @foreach ($categories as $category)
                                            <option @selected(request('main_category') == $category->slug) value="{{ $category->slug }}">
                                                {{ $category?->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" placeholder="{{ __('Search here') }}..." name="search">
                                    <button aria-label="Search"><i class="fas fa-search"></i></button>
                                </form>
                            </div>
                            <div class="tgmobile__menu-outer">
                                <!--Here Menu Will Come Automatically Via Javascript / Same Menu as in Header-->
                            </div>
                            {{-- <div class="social-links">
                                @if (count(getSocialLinks()) > 0)
                                    <ul class="list-wrap">
                                        @foreach (getSocialLinks() as $socialLink)
                                            <li>
                                                <a href="{{ $socialLink->link }}" target="_blank">
                                                    <img src="{{ asset($socialLink->icon) }}" alt="img">
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div> --}}
                        </nav>
                    </div>
                    <div class="tgmobile__menu-backdrop"></div>
                    <!-- End Mobile Menu -->

                    {{-- start admin logout form --}}
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                    {{-- end admin logout form --}}
                </div>
            </div>
        </div>
    </div>
</header>
<!-- header-area-end -->
