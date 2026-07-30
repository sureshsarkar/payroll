{{-- Public master layout for a coach's marketing site —
     reads CoachSiteSettings for analytics, favicon, sticky CTA, etc. --}}
@php
    // SECURITY/branding (audit 2026-06-12) — read the REAL Brand accessors.
    // The old `$brand->primary_color/accent_color/logo` properties don't exist
    // on the Brand value object (it exposes primaryColor/accentColor/logoUrl()),
    // so coach colours silently fell back to platform defaults and the logo
    // never rendered. The logo is gated on ownLogo && !isPlatformDefault so the
    // PLATFORM (MBSGuru) logo can never leak onto a coach's branded domain.
    $brandName = $brand->name ?? config('app.name');
    $primary   = $brand->primaryColor ?? '#6366F1';
    $accent    = $brand->accentColor  ?? '#8B5CF6';
    $brandLogo = (($brand->ownLogo ?? false) && ! ($brand->isPlatformDefault ?? false) && method_exists($brand, 'logoUrl'))
        ? $brand->logoUrl()
        : null;
    $coachId   = $coach->id ?? ($page->coach_id ?? null);

    // Site-wide settings — only loaded if coach_id resolvable
    $settings = null;
    if ($coachId) {
        try { $settings = app(\App\Services\Site\SiteSettingsService::class)->for((int) $coachId); }
        catch (\Throwable $e) { $settings = null; }
    }

    // SEO defaults compose: page meta + site defaults + brand fallback
    $metaT = $page->meta_title ?? null;
    if (! $metaT) {
        $metaT = $page->title . ' — ' . $brandName;
    } elseif (! empty($settings?->seo_default_title_suffix)) {
        $metaT .= ' ' . $settings->seo_default_title_suffix;
    }
    $metaD = $page->meta_description ?? ($settings->seo_default_description ?? ($page->title . ' · ' . $brandName));
    $ogImg = $page->og_image ?? ($settings->seo_og_image_default ?? $brandLogo);
    $favicon = $settings?->favicon_url ?? $brandLogo;
    $canonical = url(($page->slug === 'home' ? '' : '/' . $page->slug));

    $whatsappUrl = $settings ? app(\App\Services\Site\SiteSettingsService::class)->whatsappUrl($settings) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="--brand-primary: {{ $primary }}; --brand-accent: {{ $accent }};">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="{{ $page->robots ?? 'index' }}{{ ($page->robots ?? 'index') === 'noindex' ? ',nofollow' : ',follow' }}">

    <title>{{ $metaT }}</title>
    <meta name="description" content="{{ $metaD }}">
    <link rel="canonical" href="{{ $canonical }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $metaT }}">
    <meta property="og:description" content="{{ $metaD }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if($ogImg) <meta property="og:image" content="{{ $ogImg }}"> @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaT }}">
    <meta name="twitter:description" content="{{ $metaD }}">
    @if($ogImg) <meta name="twitter:image" content="{{ $ogImg }}"> @endif

    {{-- Favicon --}}
    @if($favicon)
        <link rel="icon" href="{{ $favicon }}">
    @endif

    {{-- Fonts — base set (Inter + Plus Jakarta Sans) ALWAYS loaded; any
         per-section custom fonts the coach picked are appended dynamically
         so only fonts ACTUALLY in use on this page get loaded. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php
        // Base fonts always loaded
        $fontFamilies = ['Inter:wght@400;500;600;700;800', 'Plus+Jakarta+Sans:wght@500;600;700;800'];
        // Append per-section picks from the page's sections (if available)
        try {
            if (isset($page) && method_exists($page, 'sections')) {
                $extra = \App\Services\Site\SectionRegistry::collectFontSlugs($page->sections);
                foreach ($extra as $slug) {
                    if (! in_array($slug, $fontFamilies, true)) $fontFamilies[] = $slug;
                }
            }
        } catch (\Throwable $e) { /* fail-safe: just keep base fonts */ }
        $fontsUrl = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $fontFamilies) . '&display=swap';
    @endphp
    <link rel="stylesheet" href="{{ $fontsUrl }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/fontawesome-all.min.css') }}">

    {{-- Coach site CSS --}}
    <link rel="stylesheet" href="{{ asset('frontend/css/coach-site.css') }}?v={{ @filemtime(public_path('frontend/css/coach-site.css')) ?: '1' }}">

    {{-- JSON-LD: Person --}}
    @if(!empty($brand) && !empty($brandName))
    <script type="application/ld+json">
    @php
        $ld = ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => $brandName];
        if ($brandLogo) { $ld['image'] = $brandLogo; }
        if (!empty($brand->supportEmail)) { $ld['email'] = $brand->supportEmail; }
        $ld['url'] = url('/');
        $socials = [];
        if (!empty($settings?->social_facebook))  $socials[] = $settings->social_facebook;
        if (!empty($settings?->social_instagram)) $socials[] = $settings->social_instagram;
        if (!empty($settings?->social_youtube))   $socials[] = $settings->social_youtube;
        if (!empty($settings?->social_twitter))   $socials[] = $settings->social_twitter;
        if (!empty($settings?->social_linkedin))  $socials[] = $settings->social_linkedin;
        if ($socials) { $ld['sameAs'] = $socials; }
    @endphp
    {!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @endif

    {{-- Analytics: GTM (preferred container) --}}
    @if(!empty($settings?->analytics_gtm_id))
        <script nonce="{{ csp_nonce() }}">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','{{ $settings->analytics_gtm_id }}');</script>
    @endif

    {{-- Analytics: GA4 (standalone) --}}
    @if(!empty($settings?->analytics_ga4_id))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $settings->analytics_ga4_id }}"></script>
        <script nonce="{{ csp_nonce() }}">
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $settings->analytics_ga4_id }}');
        </script>
    @endif

    {{-- Analytics: Meta Pixel --}}
    @if(!empty($settings?->analytics_meta_pixel_id))
        <script nonce="{{ csp_nonce() }}">!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
            document,'script','https://connect.facebook.net/en_US/fbevents.js');
            fbq('init','{{ $settings->analytics_meta_pixel_id }}');fbq('track','PageView');</script>
    @endif

    {{-- Site-wide Typography (coach-configured font sizes, responsive). Only
         emits the values the coach set; loaded BEFORE custom_css so a power
         user's custom CSS can still override it. --}}
    @php
        $typographyCss = '';
        try { $typographyCss = app(\App\Services\Site\SiteSettingsService::class)->typographyCss($settings); }
        catch (\Throwable $e) { $typographyCss = ''; }
    @endphp
    @if($typographyCss !== '')
        <style nonce="{{ csp_nonce() }}">{!! $typographyCss !!}</style>
    @endif

    {{-- Coach's custom CSS (power-user override) --}}
    @if(!empty($settings?->custom_css))
        <style nonce="{{ csp_nonce() }}">{!! $settings->custom_css !!}</style>
    @endif

    {{-- Coach's custom <head> scripts (tracking pixels, etc.) --}}
    @if(!empty($settings?->custom_head_scripts))
        {!! $settings->custom_head_scripts !!}
    @endif

    @stack('head')
</head>
<body class="cs-body">

    {{-- Owner draft-preview banner — visible ONLY to the owner / staff
         when the page they're viewing is not yet published. Public
         visitors never see this. --}}
    @if(!empty($isDraftPreview))
        <div style="background:linear-gradient(90deg,#F59E0B,#EAB308);color:#0F172A;padding:10px 24px;font-size:13px;font-weight:600;display:flex;justify-content:center;align-items:center;gap:14px;letter-spacing:-0.005em;">
            <i class="fa-solid fa-eye"></i>
            <span>{{ __('Draft preview — only you can see this. Publish the page to make it live.') }}</span>
            @if(!empty($page) && !empty($page->id))
                <a href="{{ url('/instructor/web-page/pages/' . $page->id) }}"
                   style="background:#0F172A;color:#fff;padding:5px 12px;border-radius:6px;text-decoration:none;font-weight:600;font-size:12px;">
                    <i class="fa-solid fa-pen"></i> {{ __('Edit page') }}
                </a>
            @endif
        </div>
    @endif

    {{-- GTM noscript fallback --}}
    @if(!empty($settings?->analytics_gtm_id))
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $settings->analytics_gtm_id }}"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    {{-- Post-payment success banner. Shown when payment_success redirected
         the student back here with ?paid=1. The student remains on the
         coach's branded page — only the actual gateway hop touched MBSguru
         chrome. Includes a button to the student's enrolled courses so they
         can immediately access their purchase. (2026-06-26 — was /student/
         my-courses which 404s at the host root; the working host-resolved,
         brand-aware route is /student/enrolled-courses.) --}}
    @if(request()->boolean('paid'))
        <div style="background:linear-gradient(90deg,#16A34A,#10B981);color:#fff;padding:14px 24px;font-size:14px;font-weight:600;display:flex;justify-content:center;align-items:center;gap:18px;letter-spacing:-0.005em;">
            <i class="fa-solid fa-circle-check" style="font-size:20px;"></i>
            <span>{{ __('Payment successful! Your course is ready to watch.') }}</span>
            <a href="{{ url('/student/enrolled-courses') }}"
               style="background:#fff;color:#16A34A;padding:6px 14px;border-radius:6px;text-decoration:none;font-weight:700;font-size:13px;">
                <i class="fa-solid fa-graduation-cap"></i> {{ __('Open my courses') }}
            </a>
        </div>
    @endif

    {{-- 2026-07-14 — Pricing & Plans booking outcome banner. The pricing modal
         redirects back here with ?booking=success|failed|cancelled after the
         payment gateway. Thank-You is shown only on a verified success. --}}
    @if(request()->filled('booking'))
        @php $__bk = strtolower((string) request('booking')); @endphp
        @if($__bk === 'success')
            <div style="background:linear-gradient(90deg,#16A34A,#10B981);color:#fff;padding:14px 24px;font-size:14px;font-weight:600;display:flex;justify-content:center;align-items:center;gap:14px;letter-spacing:-0.005em;">
                <i class="fa-solid fa-circle-check" style="font-size:20px;"></i>
                <span>{{ __('Payment successful! Your booking is confirmed. We will contact you shortly.') }}</span>
            </div>
        @elseif($__bk === 'failed')
            <div style="background:linear-gradient(90deg,#DC2626,#EF4444);color:#fff;padding:14px 24px;font-size:14px;font-weight:600;display:flex;justify-content:center;align-items:center;gap:14px;letter-spacing:-0.005em;">
                <i class="fa-solid fa-circle-xmark" style="font-size:20px;"></i>
                <span>{{ __('Payment failed. Your booking is saved — please try the payment again.') }}</span>
            </div>
        @elseif($__bk === 'cancelled')
            <div style="background:linear-gradient(90deg,#D97706,#F59E0B);color:#fff;padding:14px 24px;font-size:14px;font-weight:600;display:flex;justify-content:center;align-items:center;gap:14px;letter-spacing:-0.005em;">
                <i class="fa-solid fa-circle-info" style="font-size:20px;"></i>
                <span>{{ __('Payment cancelled. Your booking is saved — you can complete the payment anytime.') }}</span>
            </div>
        @endif
    @endif

    {{-- Top nav --}}
    @php
        // 2026-06-01 (audit [12]) — white-label brand link must point at the
        // COACH'S home, not the platform root. Path-based access exposes a
        // slug (?coachSlug / ?site_slug or an explicit $coachSlug), so link to
        // the coach path-home route. Subdomain / custom-domain access has no
        // slug param and url('/') is ALREADY the coach's home there — so the
        // fallback stays url('/'). This strictly fixes path mode and leaves
        // subdomain mode untouched.
        $brandHomeSlug = $coachSlug
            ?? optional(request()->route())->parameter('coachSlug')
            ?? optional(request()->route())->parameter('site_slug')
            ?? null;
        $brandHomeUrl = $brandHomeSlug
            ? route('coach.site.path', ['site_slug' => $brandHomeSlug])
            : url('/');
    @endphp
    <header class="cs-nav">
        <div class="cs-container cs-nav__inner">
            <a href="{{ $brandHomeUrl }}" class="cs-nav__brand">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}">
                @else
                    <span>{{ $brandName }}</span>
                @endif
            </a>
            <nav class="cs-nav__links" aria-label="{{ __('Primary') }}">
                @foreach(($siteNav ?? []) as $n)
                    @if(!empty($n['children']) && ($n['layout'] ?? 'dropdown') === 'mega')
                        {{-- MEGA MENU — wide multi-column panel. Children are columns; each column's children are its links. --}}
                        <div class="cs-nav__item cs-nav__has-children cs-nav__has-mega">
                            <a href="{{ $n['url'] }}" class="cs-nav__toplink {{ ($n['active'] ?? false) ? 'is-active' : '' }}"
                               @if(!empty($n['external'])) target="_blank" rel="noopener" @endif
                               aria-haspopup="true" aria-expanded="false">
                                {{ $n['label'] }} <i class="fa-solid fa-chevron-down cs-nav__caret" aria-hidden="true"></i>
                            </a>
                            <div class="cs-nav__mega" role="menu">
                                <div class="cs-nav__mega-inner">
                                    @foreach($n['children'] as $col)
                                        <div class="cs-nav__mega-col">
                                            @if(($col['link_type'] ?? '') === 'none' || empty($col['url']))
                                                <span class="cs-nav__mega-h">{{ $col['label'] }}</span>
                                            @else
                                                <a class="cs-nav__mega-h" href="{{ $col['url'] }}"
                                                   @if(!empty($col['external'])) target="_blank" rel="noopener" @endif>{{ $col['label'] }}</a>
                                            @endif
                                            @if(!empty($col['children']))
                                                <ul class="cs-nav__mega-links">
                                                    @foreach($col['children'] as $lnk)
                                                        <li><a href="{{ $lnk['url'] }}" role="menuitem"
                                                               @if(!empty($lnk['external'])) target="_blank" rel="noopener" @endif
                                                               @if($lnk['active'] ?? false) aria-current="page" @endif>{{ $lnk['label'] }}</a></li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @elseif(!empty($n['children']))
                        {{-- Simple dropdown --}}
                        <div class="cs-nav__item cs-nav__has-children">
                            <a href="{{ $n['url'] }}" class="cs-nav__toplink {{ ($n['active'] ?? false) ? 'is-active' : '' }}"
                               @if(!empty($n['external'])) target="_blank" rel="noopener" @endif
                               aria-haspopup="true" aria-expanded="false">
                                {{ $n['label'] }} <i class="fa-solid fa-chevron-down cs-nav__caret" aria-hidden="true"></i>
                            </a>
                            <div class="cs-nav__dropdown" role="menu">
                                @foreach($n['children'] as $c)
                                    @if(($c['link_type'] ?? '') === 'none' || empty($c['url']))
                                        <span class="cs-nav__dropdown-label">{{ $c['label'] }}</span>
                                    @else
                                        <a href="{{ $c['url'] }}" role="menuitem"
                                           @if(!empty($c['external'])) target="_blank" rel="noopener" @endif
                                           @if($c['active'] ?? false) aria-current="page" @endif>{{ $c['label'] }}</a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ $n['url'] }}"
                           @if(!empty($n['external'])) target="_blank" rel="noopener" @endif
                           @if(($n['active'] ?? false)) aria-current="page" @endif>{{ $n['label'] }}</a>
                    @endif
                @endforeach
            </nav>
            {{-- Cart icon + count badge. Clicking opens an in-page slide-out
                 drawer instead of navigating away — students stay on the
                 coach's branded site through cart review. Only the final
                 "Proceed to checkout" button hands off to the platform's
                 payment flow (one short hop, then back here on success). --}}
            @php
                $headerCartItems = [];
                try {
                    if (auth()->check() && method_exists(auth()->user(), 'carts')) {
                        $headerCartItems = auth()->user()->carts()
                            ->with('course:id,title,slug,price,discount,thumbnail')
                            ->get()
                            ->map(fn ($row) => (object) [
                                'course_id'   => $row->course_id,
                                'cart_row_id' => $row->id,            // carts.id (for remove)
                                'title'       => $row->course?->title,
                                'thumbnail'   => $row->course?->thumbnail,
                                'price'       => ($row->course?->discount ?? 0) > 0
                                    ? $row->course->discount
                                    : ($row->course?->price ?? 0),
                                'slug'        => $row->course?->slug,
                            ])->all();
                    } else {
                        $headerCartItems = \Gloudemans\Shoppingcart\Facades\Cart::content()
                            ->map(fn ($row) => (object) [
                                'course_id'   => $row->id,
                                'cart_row_id' => $row->rowId,         // session-cart rowId (for remove)
                                'title'       => $row->name,
                                'thumbnail'   => $row->options['thumbnail'] ?? null,
                                'price'       => $row->price,
                                'slug'        => $row->options['slug'] ?? null,
                            ])->values()->all();
                    }
                } catch (\Throwable $e) { $headerCartItems = []; }
                $headerCartCount = count($headerCartItems);
                $headerCartTotal = array_sum(array_map(fn ($i) => (float) $i->price, $headerCartItems));
            @endphp
            @php
                // Resolve coach slug once for the header cart icon. Falls
                // back to the platform /cart when no coach is in context
                // (so this layout is safe if ever reused outside the
                // coach-site flow).
                $headerCartCoachSlug = $coachSlug
                    ?? optional(request()->route())->parameter('coachSlug')
                    ?? optional(request()->route())->parameter('site_slug')
                    ?? null;
                if (! $headerCartCoachSlug && isset($page) && !empty($page->coach_id)) {
                    $headerCartCoachSlug = \App\Models\CoachLandingPage::where('added_by', $page->coach_id)->value('slug');
                }
                // 2026-06-10 — clean root url (/cart) on a coach domain; the
                // /coach/{slug}/cart path only on the path surface.
                $headerCartFallbackHref = coachCommerceUrl('cart', $headerCartCoachSlug);
            @endphp
            <a href="{{ $headerCartFallbackHref }}"
               class="cs-nav__cart"
               aria-label="{{ __('Cart') }}"
               title="{{ __('View cart') }}"
               onclick="event.preventDefault(); document.body.classList.add('cs-cart-open');">
                <i class="fa-solid fa-cart-shopping"></i>
                <span class="mini-cart-count cs-nav__cart-count{{ $headerCartCount === 0 ? ' is-zero' : '' }}">{{ $headerCartCount }}</span>
            </a>

            {{-- 2026-06-11 — Login in the menu bar (New changes doc): coach AND
                 student log in from the custom website via the coach-branded
                 login page. Logged-in users get a role-aware "My account"
                 (coach/staff → instructor panel, student → student panel). --}}
            @auth('web')
                <a href="{{ auth('web')->user()->role === 'student' ? route('student.dashboard') : route('instructor.dashboard') }}"
                   class="cs-btn cs-btn--outline cs-btn--sm cs-nav__login">{{ __('My account') }}</a>
            @else
                <a href="{{ $headerCartCoachSlug ? url('/coach/' . $headerCartCoachSlug . '/login') : url('/login') }}"
                   class="cs-btn cs-btn--outline cs-btn--sm cs-nav__login">{{ __('Log in') }}</a>
            @endauth
            {{-- 2026-06-24 — the always-on default "Contact" button was removed.
                 A CTA now appears ONLY when the coach explicitly configures one
                 (Website settings → nav CTA). No hardcoded fallback button. --}}
            @if($settings?->nav_show_cta && !empty($settings->nav_cta_url))
                <a href="{{ $settings->nav_cta_url }}" class="cs-btn cs-btn--primary cs-btn--sm cs-nav__cta">{{ $settings->nav_cta_text ?: __('Contact') }}</a>
            @endif
            <button type="button" class="cs-nav__toggle" aria-label="Open menu" onclick="document.body.classList.toggle('cs-nav-open')">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </header>

    <main class="cs-main">
        @if(!empty($bodyHtml))
            {!! $bodyHtml !!}
        @else
            @yield('content')
        @endif
    </main>

    {{-- ───────────────────────────────────────────────────────────────────
         Coach-site cart drawer. Slides in from the right when the user
         clicks the header cart icon. Lists items with thumbnail/title/price,
         shows total, and provides ONE button to /checkout (the one place
         we hand off to the platform — required because payment, KYC,
         tax compute, and order ledger all live on the platform).
         The drawer keeps brand colors so until that final hop, the student
         never sees the MBSGuru chrome.
         ─────────────────────────────────────────────────────────────────── --}}
    <div class="cs-cart-overlay" onclick="document.body.classList.remove('cs-cart-open')"></div>
    <aside class="cs-cart-drawer" aria-label="{{ __('Your cart') }}" aria-modal="true" role="dialog">
        <header class="cs-cart-drawer__head">
            <h3>{{ __('Your cart') }} <span class="cs-cart-drawer__count">({{ $headerCartCount ?? 0 }})</span></h3>
            <button type="button" class="cs-cart-drawer__close" aria-label="{{ __('Close') }}"
                    onclick="document.body.classList.remove('cs-cart-open')">×</button>
        </header>
        <div class="cs-cart-drawer__body">
            @if(empty($headerCartItems))
                <div class="cs-cart-drawer__empty">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <p>{{ __('Your cart is empty.') }}</p>
                    <small>{{ __('Browse courses and click "Add to cart" to start.') }}</small>
                </div>
            @else
                @foreach($headerCartItems as $it)
                    @php
                        $itRemoveUrl = (!empty($it->cart_row_id))
                            ? route('remove-cart-item', [base64_encode($it->cart_row_id), $it->slug ?? '0'])
                            : null;
                    @endphp
                    <div class="cs-cart-drawer__item" data-course-id="{{ $it->course_id }}">
                        @if(!empty($it->thumbnail))
                            <img src="{{ str_starts_with($it->thumbnail, 'http') ? $it->thumbnail : asset($it->thumbnail) }}" alt="">
                        @else
                            <div class="cs-cart-drawer__placeholder"><i class="fa-solid fa-play"></i></div>
                        @endif
                        <div class="cs-cart-drawer__meta">
                            <h4>{{ \Illuminate\Support\Str::limit($it->title ?? 'Course', 60) }}</h4>
                            <span class="cs-cart-drawer__price">₹{{ number_format((float) $it->price, 0) }}</span>
                        </div>
                        @if($itRemoveUrl)
                            <a href="{{ $itRemoveUrl }}"
                               class="cs-cart-drawer__remove"
                               aria-label="{{ __('Remove') }}"
                               title="{{ __('Remove') }}">&times;</a>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
        @if(! empty($headerCartItems))
            @php
                // Resolve coach slug from the current request — the master
                // layout is rendered both for marketing pages (which have
                // a $page object with coach_id) and for commerce pages
                // (which carry $coachSlug directly). Either way we end up
                // with the right slug for coach-scoped URLs.
                $cartCoachSlug = $coachSlug
                    ?? optional(request()->route())->parameter('coachSlug')
                    ?? optional(request()->route())->parameter('site_slug')
                    ?? null;
                if (! $cartCoachSlug && isset($page) && !empty($page->coach_id)) {
                    $cartCoachSlug = \App\Models\CoachLandingPage::where('added_by', $page->coach_id)->value('slug');
                }
                $checkoutUrl = $cartCoachSlug
                    ? route('coach.checkout', ['coachSlug' => $cartCoachSlug])
                    : url('/checkout');
                $viewCartUrl = $cartCoachSlug
                    ? route('coach.cart', ['coachSlug' => $cartCoachSlug])
                    : url('/cart');
            @endphp
            <footer class="cs-cart-drawer__foot">
                <div class="cs-cart-drawer__total">
                    <span>{{ __('Total') }}</span>
                    <strong>₹{{ number_format((float) ($headerCartTotal ?? 0), 0) }}</strong>
                </div>
                <a href="{{ $checkoutUrl }}"
                   class="cs-btn cs-btn--primary cs-cart-drawer__checkout">
                    <i class="fa-solid fa-lock"></i>
                    {{ __('Proceed to secure checkout') }}
                </a>
                <a href="{{ $viewCartUrl }}" class="cs-cart-drawer__view-full">
                    {{ __('View full cart page') }}
                </a>
            </footer>
        @endif
    </aside>

    {{-- 2026-06-11 — Cart drawer item remove. Lives in the master layout so
         remove works on EVERY coach page (the add-to-cart JS only ships with
         the recorded-courses section). AJAX removal updates the drawer, the
         header badge and the total in place — no page refresh. If JS/AJAX
         fails for any reason it falls back to following the link (the
         controller still removes + redirects back), so remove can never be
         a dead button. --}}
    <style>
        .cs-cart-drawer__item { position: relative; }
        .cs-cart-drawer__remove {
            margin-left: auto;
            flex: 0 0 auto;
            width: 26px; height: 26px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
            color: #9CA3AF; font-size: 20px; line-height: 1; text-decoration: none;
            transition: background .15s, color .15s;
        }
        .cs-cart-drawer__remove:hover { background: #FEE2E2; color: #DC2626; }
    </style>
    <script nonce="{{ csp_nonce() }}">
    (function () {
        function setText(sel, value) {
            document.querySelectorAll(sel).forEach(function (el) { el.textContent = value; });
        }
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.cs-cart-drawer__remove');
            if (!btn) return;
            e.preventDefault();
            if (btn.dataset.busy) return;
            btn.dataset.busy = '1';

            var url = btn.getAttribute('href');
            fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (data) {
                if (!data || data.status !== 'success') {
                    // Couldn't process as AJAX — fall back to a normal navigation
                    // so the item still gets removed server-side.
                    window.location.href = url;
                    return;
                }
                // Drop this item from the drawer.
                var item = btn.closest('.cs-cart-drawer__item');
                if (item) item.remove();

                var count = (typeof data.cart_count !== 'undefined') ? data.cart_count : null;
                if (count !== null) {
                    // Header badge(s)
                    document.querySelectorAll('.mini-cart-count, [data-cart-count], .cs-cart-count').forEach(function (el) {
                        el.textContent = count;
                        if (count > 0) { el.classList.remove('is-zero'); } else { el.classList.add('is-zero'); }
                    });
                    // Drawer header "(n)"
                    setText('.cs-cart-drawer__count', '(' + count + ')');
                }
                if (typeof data.total !== 'undefined') {
                    var totalEl = document.querySelector('.cs-cart-drawer__total strong');
                    if (totalEl) totalEl.textContent = data.total;
                }
                // Cart emptied → show the empty state + drop the footer.
                if (count === 0) {
                    var body = document.querySelector('.cs-cart-drawer__body');
                    if (body && !body.querySelector('.cs-cart-drawer__empty')) {
                        body.innerHTML = '<div class="cs-cart-drawer__empty">' +
                            '<i class="fa-solid fa-cart-shopping"></i>' +
                            '<p>{{ __('Your cart is empty.') }}</p></div>';
                    }
                    var foot = document.querySelector('.cs-cart-drawer__foot');
                    if (foot) foot.remove();
                    // If we're on the full cart page, reload so it reflects empty.
                    if (/\/cart(\/|$|\?)/.test(window.location.pathname)) window.location.reload();
                }
            })
            .catch(function () { window.location.href = url; });
        });
    })();
    </script>

    {{-- Footer. Priority: page's own footer section (already in bodyHtml) →
         the coach's GLOBAL footer (injected by the coach-site composer on
         login/register/cart/checkout/etc.) → the generic config-driven footer. --}}
    @if(!($hasFooterSection ?? false) && !empty($globalFooterHtml))
        {!! $globalFooterHtml !!}
    @elseif(!($hasFooterSection ?? false))
        @php $footer = $settings?->footer_config ?? null; @endphp
        <footer class="cs-footer">
            <div class="cs-container cs-footer__grid">
                <div class="cs-footer__brand">
                    @if($brandLogo)
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="cs-footer__logo">
                    @else
                        <h3 class="cs-footer__name">{{ $brandName }}</h3>
                    @endif
                    @if(!empty($footer['tagline']))
                        <p class="cs-footer__tag">{{ $footer['tagline'] }}</p>
                    @endif
                    @if($settings && ($settings->social_facebook || $settings->social_instagram || $settings->social_youtube || $settings->social_twitter || $settings->social_linkedin || $settings->social_tiktok || $settings->social_pinterest))
                        <div class="cs-footer__social">
                            @if($settings->social_facebook)  <a href="{{ $settings->social_facebook }}"  target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a> @endif
                            @if($settings->social_instagram) <a href="{{ $settings->social_instagram }}" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a> @endif
                            @if($settings->social_youtube)   <a href="{{ $settings->social_youtube }}"   target="_blank" rel="noopener" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a> @endif
                            @if($settings->social_twitter)   <a href="{{ $settings->social_twitter }}"   target="_blank" rel="noopener" aria-label="Twitter / X"><i class="fa-brands fa-x-twitter"></i></a> @endif
                            @if($settings->social_linkedin)  <a href="{{ $settings->social_linkedin }}"  target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a> @endif
                            @if($settings->social_tiktok)    <a href="{{ $settings->social_tiktok }}"    target="_blank" rel="noopener" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a> @endif
                            @if($settings->social_pinterest) <a href="{{ $settings->social_pinterest }}" target="_blank" rel="noopener" aria-label="Pinterest"><i class="fa-brands fa-pinterest-p"></i></a> @endif
                        </div>
                    @endif
                </div>

                @foreach(($footer['link_groups'] ?? []) as $g)
                    <div class="cs-footer__col">
                        <h4 class="cs-footer__h">{{ $g['title'] ?? '' }}</h4>
                        <ul class="cs-footer__links">
                            @foreach(($g['links'] ?? []) as $l)
                                <li><a href="{{ $l['url'] ?? '#' }}">{{ $l['label'] ?? '' }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <div class="cs-footer__bar">
                <div class="cs-container cs-footer__bar-inner">
                    <span>&copy; {{ date('Y') }} {{ $brandName }}. {{ $footer['copyright'] ?? __('All rights reserved.') }}</span>
                </div>
            </div>
        </footer>
    @endif

    {{-- Floating WhatsApp button --}}
    @if($whatsappUrl)
        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="cs-float-btn cs-float-btn--whatsapp" aria-label="Chat on WhatsApp">
            <i class="fa-brands fa-whatsapp"></i>
        </a>
    @endif

    {{-- Floating sticky CTA (e.g. "Book a Call") --}}
    @if($settings?->sticky_cta_enabled && !empty($settings->sticky_cta_text) && !empty($settings->sticky_cta_url))
        <a href="{{ $settings->sticky_cta_url }}" class="cs-float-cta">
            <i class="fa-solid fa-calendar-check"></i>
            <span>{{ $settings->sticky_cta_text }}</span>
        </a>
    @endif

    {{-- Tiny client JS: lead form ajax + smooth scroll + nav scroll-shadow --}}
    <script nonce="{{ csp_nonce() }}">
    (function() {
        // Premium nav: add a subtle shadow + condense once the page scrolls.
        var nav = document.querySelector('.cs-nav');
        if (nav) {
            var onScroll = function() { nav.classList.toggle('is-scrolled', window.scrollY > 8); };
            onScroll();
            window.addEventListener('scroll', onScroll, { passive: true });
        }
        document.addEventListener('click', function(e) {
            const a = e.target.closest('a[href^="#"]');
            if (!a) return;
            const id = a.getAttribute('href').slice(1);
            const t = id ? document.getElementById(id) : null;
            if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
        document.querySelectorAll('form[data-form="lead"]').forEach(function(f) {
            f.addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(f);
                const btn = f.querySelector('button[type=submit]');
                const ok = f.querySelector('[data-success]');
                if (btn) { btn.disabled = true; btn.dataset._orig = btn.textContent; btn.textContent = 'Sending…'; }
                fetch(f.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json().catch(function(){ return {}; }); })
                    .then(function(data) {
                        if (data && data.success) {
                            f.reset();
                            if (ok) ok.classList.add('cs-form__success--show');
                        } else {
                            alert((data && data.message) || 'Something went wrong. Please try again.');
                        }
                    })
                    .catch(function() { alert('Network error. Please try again.'); })
                    .finally(function() {
                        if (btn) { btn.disabled = false; btn.textContent = btn.dataset._orig || 'Send'; }
                    });
            });
        });
    })();
    </script>

    {{-- Reusable Booking Enquiry modal — rendered ONCE here so any button on
         any coach page (Class Schedule "Book Now", a Trainer "Book Personal
         Classes" CTA, or any future button with class .cs-book-trigger) opens
         the same shared form. Leads are tenant-scoped to this coach. --}}
    @include('frontend.coach-site.partials.booking-enquiry-modal')

    {{-- "Book Your Trial Session" popup — renders only when this coach enabled it. --}}
    @include('frontend.coach-site.partials.trial-session-popup')

    {{-- Coach's custom <body> scripts (Hotjar, Crisp, etc.) --}}
    @if(!empty($settings?->custom_body_scripts))
        {!! $settings->custom_body_scripts !!}
    @endif

    @stack('scripts')
</body>
</html>
