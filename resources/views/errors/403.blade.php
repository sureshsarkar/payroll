@php
    // 2026-07-04 — Context-aware 403. A coach / coach-staff member who hits a
    // restricted module inside the coach panel gets the access-denied message
    // rendered IN-PLACE within the dashboard shell (sidebar + chrome stay put)
    // — it reads as "this page is restricted", not a jarring jump to the public
    // error page. Everyone else (students, guests, public routes) still gets the
    // normal frontend error page. One view covers controller `view('errors.403')`
    // AND middleware `abort(403)`.
    $__u = auth('web')->user();
    $__isCoachPanel = request()->is('instructor*') && $__u
        && (($__u->role === 'instructor' && empty($__u->coach_id))
            || (! empty($__u->coach_id) && ! in_array($__u->role, ['student', 'admin'], true)));
@endphp

@if ($__isCoachPanel)
    @extends('frontend.instructor-dashboard.layouts.master')

    @section('dashboard-contents')
        <div class="ad-stage">
            <div class="ad-card" role="alert" aria-live="polite">
                <div class="ad-badge"><i class="bi bi-shield-lock"></i></div>
                <span class="ad-code">403 · {{ __('Restricted') }}</span>
                <h1 class="ad-title">{{ __('Access Denied') }}</h1>
                <p class="ad-text">
                    {{ __('You don’t have permission to open this page. Ask your HR or administrator to grant it from') }}
                    <strong>{{ __('Staff → Roles') }}</strong>.
                </p>
                @if (! empty($deniedPermission))
                    <div class="ad-perm"><span>{{ __('Permission needed') }}</span><code>{{ $deniedPermission }}</code></div>
                @endif
                <div class="ad-actions">
                    <a href="{{ route('hr.overview') }}" class="ad-btn ad-btn--primary">
                        <i class="bi bi-grid-1x2"></i> {{ __('Back to Dashboard') }}
                    </a>
                    <button type="button" class="ad-btn"
                            onclick="if(history.length>1){history.back()}else{window.location.href='{{ route('hr.overview') }}'}">
                        <i class="bi bi-arrow-left"></i> {{ __('Go Back') }}
                    </button>
                </div>
            </div>
        </div>

        <style>
            .ad-stage { min-height:60vh; display:flex; align-items:center; justify-content:center;
                padding:32px 18px; font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
            .ad-card { width:100%; max-width:480px; text-align:center; background:#fff; border:1px solid #e8ecf2;
                border-radius:20px; padding:44px 38px 40px;
                box-shadow:inset 0 1px 0 rgba(255,255,255,.7),0 1px 2px rgba(15,23,42,.04),0 24px 60px -24px rgba(15,23,42,.20); }
            .ad-badge { width:82px; height:82px; margin:0 auto 22px; border-radius:24px; display:grid; place-items:center;
                font-size:34px; color:#059669; background:linear-gradient(160deg,#ecfdf5 0%,#d1fae5 100%);
                border:1px solid #a7f3d0; box-shadow:0 10px 26px -10px rgba(16,185,129,.45); }
            .ad-code { display:inline-block; font-size:11px; font-weight:700; letter-spacing:.14em; text-transform:uppercase;
                color:#059669; background:#ecfdf5; border:1px solid #a7f3d0; padding:4px 12px; border-radius:999px; margin-bottom:16px; }
            .ad-title { font-size:26px; font-weight:800; letter-spacing:-.02em; color:#0f172a; margin:0 0 10px; }
            .ad-text { font-size:14.5px; line-height:1.6; color:#64748b; margin:0 auto 22px; max-width:42ch; }
            .ad-text strong { color:#334155; font-weight:700; }
            .ad-perm { display:inline-flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #e8ecf2;
                border-radius:10px; padding:8px 14px; margin-bottom:24px; font-size:12px; color:#94a3b8; font-weight:600; }
            .ad-perm code { font-family:ui-monospace,'SFMono-Regular',Menlo,Consolas,monospace; font-size:12px; color:#059669;
                background:#ecfdf5; border:1px solid #a7f3d0; border-radius:6px; padding:2px 8px; }
            .ad-actions { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
            .ad-btn { display:inline-flex; align-items:center; gap:8px; font-size:13.5px; font-weight:700; cursor:pointer;
                padding:11px 20px; border-radius:11px; border:1px solid #e8ecf2; background:#fff; color:#334155;
                text-decoration:none; transition:all .16s cubic-bezier(.22,1,.36,1); }
            .ad-btn:hover { background:#f8fafc; color:#0f172a; text-decoration:none; }
            .ad-btn--primary { background:linear-gradient(135deg,#10b981,#059669); color:#fff; border-color:transparent;
                box-shadow:0 8px 20px -6px rgba(16,185,129,.5); }
            .ad-btn--primary:hover { color:#fff; filter:brightness(1.04); }
            .ad-btn:focus-visible { outline:2px solid #10b981; outline-offset:2px; }
            @media (max-width:520px){ .ad-card{padding:34px 22px 30px;} .ad-title{font-size:22px;} }
        </style>
    @endsection
@else
    @extends('frontend.layouts.master')
    @section('meta_title', __('Access Denied') . ' || ' . ($setting->app_name ?? config('app.name', 'MBSGuru')))

    @section('contents')
        {{-- LMS removal phase 2 (2026-08-27) — the second crumb pointed at
             checkout.index, which is gone (and was a nonsensical target for a
             403 page anyway). --}}
        <x-frontend.breadcrumb :title="__('Access Denied')" :links="[['url' => route('home'), 'text' => __('Home')]]" />

        <section class="error-area pt-0">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="error-wrap text-center">
                            <div class="error-img">
                                <h1 style="font-size: 300px;">403</h1>
                            </div>
                            <div class="error-content">
                                <h2 class="title">{{ __('Access Denied') }} <span>{{ __('You don’t have permission to access this page. Please ask your HR or administrator to grant access.') }}</span></h2>
                                <div class="tg-button-wrap">
                                    <a href="{{ route('home') }}" class="btn arrow-btn">{{ __('Go Home') }} <img
                                            src="{{ asset('frontend/img/icons/right_arrow.svg') }}" alt="img" class="injectable"></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endsection
@endif
