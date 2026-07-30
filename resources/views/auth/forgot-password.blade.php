@extends('frontend.layouts.master')
@section('meta_title', __('Reset your password') . ' || ' . $brand->name) {{-- P4 — per-coach white-label --}}

@section('contents')
    {{--
        Per-coach white-label password reset request.

        Design intent (2026-05): corporate single-column auth card,
        matching the login + register design language. Since this
        page has only one field, the card is anchored by a small
        envelope icon at the top so it doesn't feel empty. A
        reassurance note + a "Back to sign in" link sit below the
        form because users almost always return after submitting.

        $brand auto-injected by the BrandResolver view composer.

        Functional contract preserved 1:1 from the legacy template:
          * POST route('forget-password') with @csrf
          * email field
          * conditional reCAPTCHA
          * Switch link → route('login')
          * frontend.validation-error component
    --}}

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    <style>
        /* Scoped to .auth-shell — same tokens as login + register. */
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
            background: var(--auth-bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding: 88px 16px 80px;
            min-height: 100vh;
            overflow: hidden;
        }
        @media (max-width: 768px) {
            .auth-shell { padding: 64px 16px 60px; }
        }
        .auth-shell *,
        .auth-shell *::before,
        .auth-shell *::after { box-sizing: border-box; }

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
            max-width: 440px;
            margin: 0 auto;
        }

        /* ── Back link above the card ─────────────────────────── */
        .auth-back {
            text-align: center;
            margin-bottom: 14px;
        }
        .auth-back a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--auth-muted);
            text-decoration: none;
            font-weight: 500;
            padding: 6px 10px;
            border-radius: 8px;
            transition: color .15s var(--auth-ease),
                        background-color .15s var(--auth-ease);
        }
        .auth-back a:hover {
            color: var(--auth-text);
            background: color-mix(in srgb, var(--auth-brand) 6%, transparent);
            text-decoration: none;
        }
        .auth-back a i { font-size: 10px; }

        /* ── Card ─────────────────────────────────────────────── */
        .auth-card {
            background: var(--auth-card);
            border: 1px solid var(--auth-line);
            border-radius: 16px;
            box-shadow: var(--auth-shadow);
            padding: 40px 44px 36px;
        }
        @media (max-width: 520px) {
            .auth-card { padding: 32px 22px 28px; border-radius: 14px; }
        }

        /* Anchor icon at the top — gives the otherwise sparse card
           a visual centre of gravity. */
        .auth-icon {
            width: 56px; height: 56px;
            border-radius: 14px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: color-mix(in srgb, var(--auth-brand) 12%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--auth-brand) 20%, transparent);
            color: var(--auth-brand);
            font-size: 22px;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.6),
                0 4px 12px -2px color-mix(in srgb, var(--auth-brand) 22%, transparent);
        }

        .auth-head {
            margin-bottom: 24px;
            text-align: center;
        }
        .auth-head h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--auth-text);
            letter-spacing: -0.022em;
            margin: 0 0 8px;
            line-height: 1.25;
        }
        .auth-head p {
            font-size: 14px;
            color: var(--auth-muted);
            margin: 0;
            line-height: 1.55;
        }

        /* ── Form ─────────────────────────────────────────────── */
        .auth-form { display: flex; flex-direction: column; gap: 16px; }

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

        .auth-input {
            width: 100%;
            padding: 12px 14px;
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

        /* ── Reassurance note ─────────────────────────────────── */
        .auth-note {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 10px;
            background: color-mix(in srgb, var(--auth-brand) 5%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--auth-brand) 14%, transparent);
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12.5px;
            color: var(--auth-text-2);
            line-height: 1.5;
        }
        .auth-note i {
            color: var(--auth-brand);
            font-size: 13px;
            margin-top: 2px;
            flex-shrink: 0;
        }

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

        /* reCAPTCHA */
        .auth-recaptcha .g-recaptcha > div { margin: 0 !important; }

        /* Session status (e.g. "We sent you a reset link") — Laravel's
           default flash channel. Renders only when present. */
        .auth-flash {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 10px;
            background: color-mix(in srgb, #10b981 10%, #ffffff);
            border: 1px solid color-mix(in srgb, #10b981 28%, transparent);
            color: #065f46;
            font-size: 13px;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .auth-flash i {
            color: #10b981;
            font-size: 14px;
            margin-top: 2px;
            flex-shrink: 0;
        }
    </style>

    <section class="auth-shell">
        <div class="auth-stage">

            {{-- Back link to sign-in --}}
            <div class="auth-back">
                <a href="{{ route('login') }}">
                    <i class="fas fa-arrow-left"></i>
                    {{ __('Back to sign in') }}
                </a>
            </div>

            {{-- ── Card ───────────────────────────────────────── --}}
            <div class="auth-card">

                {{-- Flash message (Laravel session 'status' from password reset request) --}}
                @if(session('status') || session('success'))
                    <div class="auth-flash">
                        <i class="fas fa-check-circle"></i>
                        <span>{{ session('status') ?? session('success') }}</span>
                    </div>
                @endif

                {{-- Anchor icon --}}
                <div class="auth-icon" aria-hidden="true">
                    <i class="fas fa-envelope-open-text"></i>
                </div>

                <div class="auth-head">
                    <h1>{{ __('Reset your password') }}</h1>
                    <p>{{ __('Enter the email tied to your account and we\'ll send you a secure link to set a new password.') }}</p>
                </div>

                <form method="POST" action="{{ route('forget-password') }}" class="auth-form" novalidate>
                    @csrf

                    {{-- Email --}}
                    <div class="auth-field">
                        <div class="auth-field__head">
                            <label for="email" class="auth-field__label">{{ __('Email address') }}</label>
                        </div>
                        <input id="email" name="email" type="email" class="auth-input"
                               value="{{ old('email') }}"
                               placeholder="you@example.com"
                               autocomplete="email" required autofocus>
                        <x-frontend.validation-error name="email" />
                    </div>

                    {{-- reCAPTCHA --}}
                    @if (Cache::get('setting')->recaptcha_status === 'active')
                        <div class="auth-field auth-recaptcha">
                            <div class="g-recaptcha"
                                 data-sitekey="{{ Cache::get('setting')->recaptcha_site_key }}"></div>
                            <x-frontend.validation-error name="g-recaptcha-response" />
                        </div>
                    @endif

                    <button type="submit" class="auth-submit">{{ __('Send reset link') }}</button>
                </form>

                {{-- Reassurance note — sets expectations so the user doesn't
                     bounce back to login thinking the form is broken. --}}
                <div class="auth-note">
                    <i class="fas fa-info-circle"></i>
                    <span>{{ __("The link arrives within a few minutes. Check your spam folder if you don't see it.") }}</span>
                </div>

                <div class="auth-switch">
                    {{ __('Remembered it?') }}<a href="{{ route('login') }}">{{ __('Sign in') }}</a>
                </div>
            </div>

            {{-- ── Trust row ──────────────────────────────────── --}}
            <div class="auth-trust">
                <span class="auth-trust__item"><i class="fas fa-shield-alt"></i> {{ __('Bank-grade security') }}</span>
                <span class="auth-trust__item"><i class="fas fa-lock"></i> {{ __('SSL encrypted') }}</span>
                <span class="auth-trust__item"><i class="fas fa-clock"></i> {{ __('Link expires in 60 min') }}</span>
            </div>

        </div>
    </section>
@endsection
