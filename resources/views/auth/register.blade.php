@extends('frontend.layouts.master')
@section('meta_title', __('Create HR account') . ' || ' . $brand->name) {{-- P4 — per-coach white-label --}}

@section('contents')
    {{--
        Per-coach white-label coach signup.

        Design intent (2026-05): corporate single-column auth card,
        Linear / Stripe / Vercel-style. No in-card masthead — the
        site nav already brands the page; doubling the logo creates
        visual noise and exposes the legal entity name. A small
        "For Coaches" category pill identifies which signup surface
        this is (vs. student-side).

        $brand is auto-injected by the BrandResolver view composer:
        on a coach's subdomain or custom domain the colors / accent
        track that coach; on platform root they track the platform.

        Functional contract preserved 1:1 from the legacy template:
          * POST route('register') with @csrf
          * hidden role=instructor (COACH signup; student enrollment
            is order-driven, not self-service)
          * optional referral_code with ?ref= pre-fill
          * conditional Google OAuth (setting->google_login_status)
          * conditional reCAPTCHA (Cache::get('setting')->recaptcha_status)
          * frontend.validation-error component on every input
    --}}

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    <style>
        /* All styles scoped to .auth-shell so they don't leak into
           the rest of the master layout. */
        .auth-shell {
            --auth-brand:      {{ $brand->primaryColor ?: '#6366f1' }};
            --auth-brand-2:    {{ $brand->accentColor  ?: '#8b5cf6' }};
            --auth-text:       #0b1220;
            --auth-text-2:     #1f2937;
            --auth-muted:      #6b7280;
            --auth-subtle:     #9ca3af;
            --auth-line:       #e5e7eb;
            --auth-line-soft:  #f1f3f5;
            --auth-bg:         #fafbfc;
            --auth-card:       #ffffff;
            --auth-shadow:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 8px 24px -6px rgba(15, 23, 42, 0.08),
                0 24px 64px -16px rgba(15, 23, 42, 0.10);
            --auth-ease: cubic-bezier(.22, 1, .36, 1);

            position: relative;
            /*background: var(--auth-bg);*/
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding: 88px 16px 80px;   /* room below the marketing nav */
            min-height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #08132a, #163878, #3070c8);
        }
        @media (max-width: 768px) {
            .auth-shell { padding: 64px 16px 60px; }
        }
        .auth-shell *,
        .auth-shell *::before,
        .auth-shell *::after { box-sizing: border-box; }

        /* Subtle brand-tinted radial glow at the top — adds depth
           without competing with the form. */
        .auth-shell::before {
            content: '';
            position: absolute;
            top: -240px; left: 50%;
            transform: translateX(-50%);
            width: 1000px; height: 540px;
            background: radial-gradient(closest-side,
                color-mix(in srgb, var(--auth-brand) 14%, transparent),
                transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .auth-stage {
            position: relative;
            z-index: 1;
            max-width: 520px;
            margin: 30px auto;
        }

        /* ── Category pill (replaces the duplicate masthead) ── */
        .auth-pill-wrap {
            text-align: center;
            margin-bottom: 18px;
        }
        .auth-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px 5px 10px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--auth-brand) 10%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--auth-brand) 22%, transparent);
            color: color-mix(in srgb, var(--auth-brand) 78%, #000);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .auth-pill__dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--auth-brand);
        }

        /* ── Card ─────────────────────────────────────────────── */
        .auth-card {
            background: var(--auth-card);
            border: 1px solid var(--auth-line);
            border-radius: 16px;
            box-shadow: var(--auth-shadow);
            padding: 40px 44px 36px;
        }
        @media (max-width: 520px) {
            .auth-card { padding: 30px 22px 26px; border-radius: 14px; }
        }

        .auth-head {
            margin-bottom: 26px;
            text-align: center;
        }
        .auth-head h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--auth-text);
            letter-spacing: -0.022em;
            margin: 0 0 8px;
            line-height: 1.2;
        }
        .auth-head p {
            font-size: 14px;
            color: var(--auth-muted);
            margin: 0;
            line-height: 1.5;
        }

        /* ── Social ───────────────────────────────────────────── */
        .auth-social {
            display: flex; flex-direction: column; gap: 8px;
            margin-bottom: 16px;
        }
        .auth-social__btn {
            display: flex; align-items: center; justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 11px 16px;
            border-radius: 10px;
            background: #fff;
            border: 1px solid var(--auth-line);
            color: var(--auth-text);
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            transition: border-color .15s var(--auth-ease),
                        background-color .15s var(--auth-ease);
        }
        .auth-social__btn:hover {
            border-color: #cbd5e1;
            background: #fafbfc;
            color: var(--auth-text);
            text-decoration: none;
        }
        .auth-social__btn img { width: 18px; height: 18px; }

        .auth-divider {
            position: relative;
            text-align: center;
            margin: 18px 0;
        }
        .auth-divider::before {
            content: '';
            position: absolute; left: 0; right: 0; top: 50%;
            height: 1px; background: var(--auth-line);
        }
        .auth-divider span {
            position: relative;
            background: var(--auth-card);
            padding: 0 10px;
            font-size: 11.5px;
            color: var(--auth-subtle);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 600;
        }

        /* ── Form ─────────────────────────────────────────────── */
        .auth-form { display: flex; flex-direction: column; gap: 14px; }

        /* Two-column row for full-name + email at desktop width */
        .auth-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        @media (max-width: 540px) {
            .auth-row-2 { grid-template-columns: 1fr; }
        }

        .auth-field { display: flex; flex-direction: column; gap: 5px; }
        .auth-field__head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
        }
        .auth-field__label {
            font-size: 13px;
            font-weight: 600;
            color: var(--auth-text-2);
            line-height: 1.4;
        }
        .auth-field__hint {
            font-size: 12px;
            color: var(--auth-subtle);
            font-weight: 500;
        }

        .auth-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .auth-input {
            width: 100%;
            padding: 11px 14px;
            font-size: 14px;
            color: var(--auth-text);
            background: #fff;
            border: 1px solid var(--auth-line);
            border-radius: 10px;
            transition: border-color .15s var(--auth-ease),
                        box-shadow    .15s var(--auth-ease);
            font-family: inherit;
            line-height: 1.45;
        }
        .auth-input::placeholder { color: var(--auth-subtle); }
        .auth-input:hover  { border-color: #cbd5e1; }
        .auth-input:focus {
            outline: none;
            border-color: var(--auth-brand);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--auth-brand) 18%, transparent);
        }
        .auth-input--pw    { padding-right: 56px; }
        .auth-input--code  { text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }

        .auth-input-toggle {
            position: absolute;
            right: 6px;
            background: transparent;
            border: 0;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 600;
            color: var(--auth-muted);
            cursor: pointer;
            border-radius: 6px;
            transition: background-color .15s var(--auth-ease), color .15s var(--auth-ease);
        }
        .auth-input-toggle:hover { background: #f3f4f6; color: var(--auth-text); }

        /* Password strength — subtle single line */
        .auth-strength {
            display: flex; gap: 4px; margin-top: 4px;
            height: 3px;
        }
        .auth-strength__seg {
            flex: 1;
            background: var(--auth-line);
            border-radius: 2px;
            transition: background-color .2s var(--auth-ease);
        }
        .auth-strength__seg.is-1 { background: #ef4444; }
        .auth-strength__seg.is-2 { background: #f59e0b; }
        .auth-strength__seg.is-3 { background: #10b981; }
        .auth-strength__label {
            font-size: 11.5px;
            color: var(--auth-muted);
            margin-top: 4px;
            display: block;
            min-height: 14px;
            font-weight: 500;
        }

        /* ── Submit ───────────────────────────────────────────── */
        .auth-submit {
            width: 100%;
            padding: 13px 18px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: var(--auth-brand);
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 0.005em;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            transition: background-color .15s var(--auth-ease),
                        box-shadow .15s var(--auth-ease),
                        transform .1s var(--auth-ease);
            margin-top: 6px;
        }
        .auth-submit:hover {
            background: color-mix(in srgb, var(--auth-brand) 88%, #000);
            box-shadow: 0 4px 12px -2px color-mix(in srgb, var(--auth-brand) 35%, transparent);
        }
        .auth-submit:active { transform: translateY(1px); }

        /* ── Switch ───────────────────────────────────────────── */
        .auth-switch {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid var(--auth-line-soft);
            text-align: center;
            font-size: 13.5px;
            color: var(--auth-muted);
        }
        .auth-switch a {
            color: var(--auth-brand);
            font-weight: 600;
            text-decoration: none;
            margin-left: 4px;
        }
        .auth-switch a:hover { text-decoration: underline; }

        /* ── Validation errors ───────────────────────────────── */
        .auth-field .invalid-feedback,
        .auth-field .text-danger,
        .auth-field [role="alert"] {
            color: #dc2626 !important;
            font-size: 12.5px;
            margin-top: 2px;
            display: block;
            line-height: 1.4;
        }

        /* ── Trust row below card ────────────────────────────── */
        .auth-trust {
            margin-top: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            font-size: 12px;
            color: var(--auth-subtle);
            flex-wrap: wrap;
        }
        .auth-trust__item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        .auth-trust__item i {
            color: color-mix(in srgb, var(--auth-brand) 70%, #000);
            font-size: 11px;
        }

        /* ── Legal footer ────────────────────────────────────── */
        .auth-legal {
            margin-top: 14px;
            text-align: center;
            font-size: 11.5px;
            color: var(--auth-subtle);
            line-height: 1.6;
            max-width: 420px;
            margin-left: auto;
            margin-right: auto;
        }
        .auth-legal a {
            color: var(--auth-muted);
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .auth-legal a:hover { color: var(--auth-text-2); }

        /* reCAPTCHA */
        .auth-recaptcha .g-recaptcha > div { margin: 0 !important; }
    </style>

    <section class="auth-shell">
        <div class="auth-stage">

            {{-- Category pill — identifies the signup surface,
                 replaces the duplicate masthead. --}}
            <div class="auth-pill-wrap">
                <span class="auth-pill">
                    <span class="auth-pill__dot"></span>
                    {{ __('For HR') }}
                </span>
            </div>

            {{-- ── Card ───────────────────────────────────────── --}}
            <div class="auth-card">
                <div class="auth-head">
                    <h1>{{ __('Create your HR account') }}</h1>
                    <p>{{ __('Set up your HR & payroll workspace in a few minutes. No card required.') }}</p>
                </div>

                @if($setting->google_login_status == 'active')
                    <div class="auth-social">
                        <a href="{{ route('auth.social', 'google') }}" class="auth-social__btn">
                            <img src="{{ asset('frontend/img/icons/google.svg') }}" alt="">
                            <span>{{ __('Continue with Google') }}</span>
                        </a>
                    </div>
                    <div class="auth-divider"><span>{{ __('or') }}</span></div>
                @endif

                <form method="POST" action="{{ route('register') }}" class="auth-form" novalidate>
                    @csrf
                    <input type="hidden" name="role" value="instructor">

                    {{-- Full name + Email on one row (desktop) --}}
                    <div class="auth-row-2">
                        <div class="auth-field">
                            <div class="auth-field__head">
                                <label for="name" class="auth-field__label">{{ __('Full name') }}</label>
                            </div>
                            <div class="auth-input-wrap">
                                <input id="name" name="name" type="text" class="auth-input"
                                       value="{{ old('name') }}"
                                       placeholder="{{ __('Your full name') }}"
                                       autocomplete="name" required>
                            </div>
                            <x-frontend.validation-error name="name" />
                        </div>

                        <div class="auth-field">
                            <div class="auth-field__head">
                                <label for="email" class="auth-field__label">{{ __('Work email') }}</label>
                            </div>
                            <div class="auth-input-wrap">
                                <input id="email" name="email" type="email" class="auth-input"
                                       value="{{ old('email') }}"
                                       placeholder="you@example.com"
                                       autocomplete="email" required>
                            </div>
                            <x-frontend.validation-error name="email" />
                        </div>
                    </div>

                    {{-- Password --}}
                    <div class="auth-field">
                        <div class="auth-field__head">
                            <label for="password" class="auth-field__label">{{ __('Password') }}</label>
                            <span class="auth-field__hint">{{ __('8+ characters') }}</span>
                        </div>
                        <div class="auth-input-wrap">
                            <input id="password" name="password" type="password"
                                   class="auth-input auth-input--pw"
                                   placeholder="{{ __('Create a password') }}"
                                   autocomplete="new-password" required
                                   data-strength-target>
                            <button type="button" class="auth-input-toggle" data-toggle-pw="password">
                                {{ __('Show') }}
                            </button>
                        </div>
                        <div class="auth-strength" aria-hidden="true">
                            <span class="auth-strength__seg" data-strength-seg="1"></span>
                            <span class="auth-strength__seg" data-strength-seg="2"></span>
                            <span class="auth-strength__seg" data-strength-seg="3"></span>
                        </div>
                        <small class="auth-strength__label" data-strength-label></small>
                        <x-frontend.validation-error name="password" />
                    </div>

                    {{-- Confirm password --}}
                    <div class="auth-field">
                        <div class="auth-field__head">
                            <label for="password_confirmation" class="auth-field__label">{{ __('Confirm password') }}</label>
                        </div>
                        <div class="auth-input-wrap">
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                   class="auth-input auth-input--pw"
                                   placeholder="{{ __('Re-enter password') }}"
                                   autocomplete="new-password" required>
                            <button type="button" class="auth-input-toggle" data-toggle-pw="password_confirmation">
                                {{ __('Show') }}
                            </button>
                        </div>
                        <x-frontend.validation-error name="password_confirmation" />
                    </div>

                    {{-- Referral code (optional). Pre-fills from ?ref=CODE.
                         Server treats explicit field as authoritative over the mbs_ref cookie. --}}
                    <div class="auth-field">
                        <div class="auth-field__head">
                            <label for="referral_code" class="auth-field__label">{{ __('Referral code') }}</label>
                            <span class="auth-field__hint">{{ __('Optional') }}</span>
                        </div>
                        <div class="auth-input-wrap">
                            <input id="referral_code" name="referral_code" type="text"
                                   class="auth-input auth-input--code"
                                   value="{{ old('referral_code', request()->query('ref', '')) }}"
                                   placeholder="{{ __('Enter code') }}"
                                   maxlength="20" autocomplete="off">
                        </div>
                        <x-frontend.validation-error name="referral_code" />
                    </div>

                    {{-- reCAPTCHA --}}
                    @if (Cache::get('setting')->recaptcha_status === 'active')
                        <div class="auth-field auth-recaptcha">
                            <div class="g-recaptcha"
                                 data-sitekey="{{ Cache::get('setting')->recaptcha_site_key }}"></div>
                            <x-frontend.validation-error name="g-recaptcha-response" />
                        </div>
                    @endif

                    <button type="submit" class="auth-submit">{{ __('Create account') }}</button>
                </form>

                <div class="auth-switch">
                    {{ __('Already have an account?') }}<a href="{{ route('login') }}">{{ __('Sign in') }}</a>
                </div>
            </div>

            {{-- ── Trust row ──────────────────────────────────── --}}
            <div class="auth-trust">
                <span class="auth-trust__item"><i class="fas fa-shield-alt"></i> {{ __('Bank-grade security') }}</span>
                <span class="auth-trust__item"><i class="fas fa-bolt"></i> {{ __('Setup in minutes') }}</span>
                <span class="auth-trust__item"><i class="fas fa-headset"></i> {{ __('Dedicated support') }}</span>
            </div>

            {{-- ── Legal footer ───────────────────────────────── --}}
            @if($brand->termsUrl || $brand->privacyUrl)
                <div class="auth-legal">
                    {{ __('By creating an account you agree to our') }}
                    @if($brand->termsUrl)
                        <a href="{{ $brand->termsUrl }}" target="_blank" rel="noopener">{{ __('Terms') }}</a>@if($brand->privacyUrl) {{ __('and') }} @endif
                    @endif
                    @if($brand->privacyUrl)
                        <a href="{{ $brand->privacyUrl }}" target="_blank" rel="noopener">{{ __('Privacy Policy') }}</a>
                    @endif.
                </div>
            @endif

        </div>
    </section>

    <script>
        (function () {
            // Password show/hide toggles
            document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-toggle-pw');
                    var input = document.getElementById(id);
                    if (!input) return;
                    var isPw = input.type === 'password';
                    input.type = isPw ? 'text' : 'password';
                    btn.textContent = isPw ? @json(__('Hide')) : @json(__('Show'));
                });
            });

            // Password strength meter — 3-segment bar.
            // Scoring: length >= 8 = +1, letters + digits = +1, has symbol = +1.
            var pw    = document.querySelector('[data-strength-target]');
            var segs  = document.querySelectorAll('[data-strength-seg]');
            var label = document.querySelector('[data-strength-label]');
            var LABELS = {
                0: '',
                1: @json(__('Weak')),
                2: @json(__('Okay')),
                3: @json(__('Strong'))
            };
            if (pw && segs.length && label) {
                pw.addEventListener('input', function () {
                    var v = pw.value || '';
                    var score = 0;
                    if (v.length >= 8) score++;
                    if (/[A-Za-z]/.test(v) && /\d/.test(v)) score++;
                    if (/[^A-Za-z0-9]/.test(v)) score++;
                    segs.forEach(function (seg, i) {
                        seg.classList.remove('is-1', 'is-2', 'is-3');
                        if (i < score) seg.classList.add('is-' + score);
                    });
                    label.textContent = LABELS[score] || '';
                });
            }
        })();
    </script>
@endsection
