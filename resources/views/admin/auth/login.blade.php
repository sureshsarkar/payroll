@extends('admin.auth.app')
@section('title')
    <title>{{ __('Admin sign in') }} | {{ $setting?->app_name ?? config('app.name') }}</title>
@endsection
@section('content')
    {{--
        Admin console sign in — corporate redesign (2026-05).

        Visually distinct from the user-side /login so admins can tell
        at a glance they're on the operator entrance (which is also a
        small phishing-mitigation cue: a fake page would have to copy
        a different surface).

        Uses $setting (legacy admin pattern) for the platform brand,
        NOT $brand. Admin = platform operator, always sees the
        platform's own logo + name, never a coach's white-label.

        Functional contract preserved 1:1 from the legacy template:
          * POST route('admin.store-login') with @csrf
          * email + password fields (kept the admin-login-email /
            admin-login-password ids in case external JS hooks them)
          * Forgot Password → route('admin.password.request')
          * Logo links back to route('home')
          * autofocus on email, old('email') repopulation
    --}}

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    <style>
        /* Reset — admin auth.app loads stisla CSS which can intrude. */
        .adm-auth, .adm-auth * { box-sizing: border-box; }
        .adm-auth a { text-decoration: none; }

        .adm-auth {
            --adm-brand:       #6366f1;
            --adm-brand-2:     #8b5cf6;
            --adm-text:        #f1f5f9;
            --adm-text-2:      #cbd5e1;
            --adm-muted:       #94a3b8;
            --adm-subtle:      #64748b;
            --adm-line:        rgba(255, 255, 255, 0.08);
            --adm-line-soft:   rgba(255, 255, 255, 0.04);
            --adm-bg:          #0a0f1e;
            --adm-bg-2:        #0f1729;
            --adm-card:        #111a2e;
            --adm-card-2:      #1a2540;
            --adm-shadow:
                0 1px 2px rgba(0, 0, 0, 0.4),
                0 16px 36px -8px rgba(0, 0, 0, 0.5),
                0 32px 80px -16px rgba(0, 0, 0, 0.6);
            --adm-ease: cubic-bezier(.22, 1, .36, 1);

            position: relative;
            min-height: 100vh;
            background:
                radial-gradient(at 20% 0%, color-mix(in srgb, var(--adm-brand) 18%, transparent), transparent 50%),
                radial-gradient(at 80% 100%, color-mix(in srgb, var(--adm-brand-2) 14%, transparent), transparent 50%),
                linear-gradient(180deg, var(--adm-bg) 0%, var(--adm-bg-2) 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding: 56px 16px 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--adm-text);
            overflow: hidden;
        }

        /* Subtle grid pattern overlay — signals "admin / serious"
           without overpowering. */
        .adm-auth::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
            background-size: 56px 56px;
            pointer-events: none;
            mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
        }

        .adm-stage {
            position: relative;
            z-index: 1;
            max-width: 440px;
            width: 100%;
            margin: 0 auto;
        }

        /* ── Admin pill ──────────────────────────────────── */
        .adm-pill-wrap {
            text-align: center;
            margin-bottom: 18px;
        }
        .adm-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px 5px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: var(--adm-text-2);
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .adm-pill__dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }

        /* ── Brand mast ─────────────────────────────────── */
        .adm-mast {
            text-align: center;
            margin-bottom: 22px;
        }
        .adm-mast__logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: var(--adm-text);
            font-weight: 700;
            font-size: 16px;
            letter-spacing: -0.015em;
        }
        .adm-mast__logo:hover { color: var(--adm-text); text-decoration: none; }
        .adm-mast__mark {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--adm-brand) 0%, var(--adm-brand-2) 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.2),
                0 4px 12px -2px rgba(99, 102, 241, 0.5);
        }
        .adm-mast__mark img {
            width: 100%; height: 100%; object-fit: contain;
            border-radius: 10px;
        }

        /* ── Card ───────────────────────────────────────── */
        .adm-card {
            background: var(--adm-card);
            border: 1px solid var(--adm-line);
            border-radius: 16px;
            box-shadow: var(--adm-shadow);
            padding: 36px 38px 32px;
            backdrop-filter: blur(6px);
        }
        @media (max-width: 520px) {
            .adm-card { padding: 28px 22px 26px; border-radius: 14px; }
        }

        .adm-head { text-align: center; margin-bottom: 24px; }
        .adm-head h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--adm-text);
            letter-spacing: -0.022em;
            margin: 0 0 8px;
            line-height: 1.25;
        }
        .adm-head p {
            font-size: 13.5px;
            color: var(--adm-muted);
            margin: 0;
            line-height: 1.5;
        }

        /* ── Form ────────────────────────────────────────── */
        .adm-form { display: flex; flex-direction: column; gap: 16px; }
        .adm-field { display: flex; flex-direction: column; gap: 5px; }
        .adm-field__head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
        }
        .adm-field__label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--adm-text-2);
            line-height: 1.4;
        }
        .adm-field__hint-link {
            font-size: 12px;
            color: color-mix(in srgb, var(--adm-brand) 60%, #fff);
            font-weight: 600;
            text-decoration: none;
        }
        .adm-field__hint-link:hover { text-decoration: underline; color: #fff; }

        .adm-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .adm-input {
            width: 100%;
            padding: 12px 14px;
            font-size: 14px;
            color: var(--adm-text);
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--adm-line);
            border-radius: 9px;
            transition: border-color .15s var(--adm-ease),
                        background-color .15s var(--adm-ease),
                        box-shadow .15s var(--adm-ease);
            font-family: inherit;
            line-height: 1.45;
            outline: none;
        }
        .adm-input::placeholder { color: var(--adm-subtle); }
        .adm-input:hover  {
            border-color: rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.06);
        }
        .adm-input:focus {
            border-color: var(--adm-brand);
            background: rgba(255, 255, 255, 0.07);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.22);
        }
        /* Browser autofill — kill the yellow */
        .adm-input:-webkit-autofill,
        .adm-input:-webkit-autofill:hover,
        .adm-input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--adm-text);
            -webkit-box-shadow: 0 0 0px 1000px var(--adm-card-2) inset;
            transition: background-color 5000s ease-in-out 0s;
        }
        .adm-input--pw { padding-right: 56px; }

        .adm-input-toggle {
            position: absolute;
            right: 6px;
            background: transparent;
            border: 0;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 600;
            color: var(--adm-muted);
            cursor: pointer;
            border-radius: 6px;
            transition: background-color .15s var(--adm-ease), color .15s var(--adm-ease);
        }
        .adm-input-toggle:hover { background: rgba(255, 255, 255, 0.06); color: var(--adm-text); }

        /* ── Submit ──────────────────────────────────────── */
        .adm-submit {
            width: 100%;
            padding: 13px 18px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, var(--adm-brand) 0%, var(--adm-brand-2) 100%);
            border: 0;
            border-radius: 9px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: 0.005em;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.18),
                0 6px 16px -4px rgba(99, 102, 241, 0.5);
            transition: transform .12s var(--adm-ease),
                        box-shadow .15s var(--adm-ease),
                        filter .15s var(--adm-ease);
            margin-top: 6px;
        }
        .adm-submit:hover {
            filter: brightness(1.08);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.22),
                0 10px 22px -4px rgba(99, 102, 241, 0.55);
        }
        .adm-submit:active { transform: translateY(1px); }
        .adm-submit i { font-size: 12px; }

        /* ── Switch ──────────────────────────────────────── */
        .adm-switch {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid var(--adm-line-soft);
            text-align: center;
            font-size: 13px;
            color: var(--adm-muted);
        }
        .adm-switch a {
            color: color-mix(in srgb, var(--adm-brand) 60%, #fff);
            font-weight: 600;
            text-decoration: none;
            margin-left: 4px;
        }
        .adm-switch a:hover { text-decoration: underline; color: #fff; }

        /* ── Trust row ───────────────────────────────────── */
        .adm-trust {
            margin-top: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            font-size: 11.5px;
            color: var(--adm-muted);
            flex-wrap: wrap;
        }
        .adm-trust__item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        .adm-trust__item i {
            color: color-mix(in srgb, var(--adm-brand) 50%, #fff);
            font-size: 11px;
        }

        /* ── Back to site ────────────────────────────────── */
        .adm-back {
            margin-top: 18px;
            text-align: center;
            font-size: 12px;
        }
        .adm-back a {
            color: var(--adm-subtle);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            transition: color .15s var(--adm-ease), background-color .15s var(--adm-ease);
        }
        .adm-back a:hover {
            color: var(--adm-text-2);
            background: rgba(255, 255, 255, 0.04);
            text-decoration: none;
        }
        .adm-back a i { font-size: 10px; }
    </style>

    <section class="adm-auth">
        <div class="adm-stage">

            {{-- Admin entrance pill --}}
            <div class="adm-pill-wrap">
                <span class="adm-pill">
                    <span class="adm-pill__dot"></span>
                    {{ __('Admin Console') }}
                </span>
            </div>

            {{-- Platform brand mast — 2026-05-29 Doc-A4: app_name text
                 removed per user feedback ("Remove Test Name"). The
                 ADMIN CONSOLE pill above + the logo image are enough
                 identification on a platform-internal sign-in surface.
                 alt= retained for screen readers + image-blocked
                 browsers. --}}
            <div class="adm-mast">
                <a href="{{ route('home') }}" class="adm-mast__logo" aria-label="{{ $setting?->app_name ?? config('app.name', 'Admin') }}">
                    <span class="adm-mast__mark">
                        @if(!empty($setting?->logo))
                            <img src="{{ asset($setting->logo) }}" alt="{{ $setting?->app_name }}">
                        @else
                            <i class="fas fa-shield-alt"></i>
                        @endif
                    </span>
                </a>
            </div>

            {{-- ── Card ─────────────────────────────────────── --}}
            <div class="adm-card">
                <div class="adm-head">
                    <h1>{{ __('Sign in to admin') }}</h1>
                    <p>{{ __('Use your operator credentials. This area is monitored.') }}</p>
                </div>

                <form novalidate id="adminLoginForm" action="{{ route('admin.store-login') }}" method="POST" class="adm-form">
                    @csrf

                    {{-- Email --}}
                    <div class="adm-field">
                        <div class="adm-field__head">
                            <label for="admin-login-email" class="adm-field__label">{{ __('Email address') }}</label>
                        </div>
                        <div class="adm-input-wrap">
                            <input id="admin-login-email" type="email" name="email"
                                   class="adm-input"
                                   value="{{ old('email') }}"
                                   placeholder="admin@example.com"
                                   autocomplete="email" tabindex="1" autofocus required>
                        </div>
                    </div>

                    {{-- Password — Forgot link on the label row --}}
                    <div class="adm-field">
                        <div class="adm-field__head">
                            <label for="admin-login-password" class="adm-field__label">{{ __('Password') }}</label>
                            <a href="{{ route('admin.password.request') }}" class="adm-field__hint-link">
                                {{ __('Forgot password?') }}
                            </a>
                        </div>
                        <div class="adm-input-wrap">
                            <input id="admin-login-password" type="password" name="password"
                                   class="adm-input adm-input--pw"
                                   placeholder="{{ __('Enter your password') }}"
                                   autocomplete="current-password" tabindex="2" required>
                            <button type="button" class="adm-input-toggle" data-toggle-pw="admin-login-password">
                                {{ __('Show') }}
                            </button>
                        </div>
                    </div>

                    <button id="adminLoginBtn" type="submit" class="adm-submit" tabindex="3">
                        <i class="fas fa-lock"></i>
                        <span>{{ __('Sign in to admin') }}</span>
                    </button>
                </form>

                <div class="adm-switch">
                    {{ __('Not an admin?') }}<a href="{{ route('login') }}">{{ __('User sign in') }}</a>
                </div>
            </div>

            {{-- Trust strip — admin-specific concerns --}}
            <div class="adm-trust">
                <span class="adm-trust__item"><i class="fas fa-lock"></i> {{ __('Encrypted session') }}</span>
                <span class="adm-trust__item"><i class="fas fa-file-alt"></i> {{ __('Activity logged') }}</span>
                <span class="adm-trust__item"><i class="fas fa-shield-alt"></i> {{ __('2FA-ready') }}</span>
            </div>

            {{-- Back to public site --}}
            <div class="adm-back">
                <a href="{{ route('home') }}">
                    <i class="fas fa-arrow-left"></i>
                    {{ __('Back to public site') }}
                </a>
            </div>

        </div>
    </section>

    <script>
        (function () {
            // Password show / hide toggle
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
        })();
    </script>
@endsection
