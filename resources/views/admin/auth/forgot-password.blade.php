@extends('admin.auth.app')
@section('title')
    <title>{{ __('Reset admin password') }} | {{ $setting?->app_name ?? config('app.name') }}</title>
@endsection
@section('content')
    {{--
        Admin password reset request — corporate redesign (2026-05).

        Matches /admin/login (dark slate theme) so admins recognise
        the operator surface even on the recovery flow. Same scoped
        token system, same phishing-mitigation cues.

        Functional contract preserved 1:1:
          * POST route('admin.forget-password')
          * email field + old() repopulation + autofocus
          * Back link → route('admin.login')
          * Logo link → route('home')
    --}}

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    <style>
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
            padding: 56px 16px 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--adm-text);
            overflow: hidden;
        }
        .adm-auth::before {
            content: ''; position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
            background-size: 56px 56px;
            pointer-events: none;
            mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
        }

        .adm-stage { position: relative; z-index: 1; max-width: 440px; width: 100%; }

        .adm-back-top { text-align: center; margin-bottom: 14px; }
        .adm-back-top a {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; color: var(--adm-text-2);
            padding: 6px 12px; border-radius: 8px;
            transition: background-color .15s var(--adm-ease);
        }
        .adm-back-top a:hover { background: rgba(255,255,255,0.06); color: var(--adm-text); }
        .adm-back-top a i { font-size: 10px; }

        .adm-mast { text-align: center; margin-bottom: 22px; }
        .adm-mast__logo {
            display: inline-flex; align-items: center; gap: 10px;
            color: var(--adm-text); font-weight: 700; font-size: 16px;
            letter-spacing: -0.015em;
        }
        .adm-mast__logo:hover { color: var(--adm-text); }
        .adm-mast__mark {
            width: 40px; height: 40px; border-radius: 10px;
            background: linear-gradient(135deg, var(--adm-brand) 0%, var(--adm-brand-2) 100%);
            color: #fff; display: inline-flex; align-items: center; justify-content: center;
            font-size: 16px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.2), 0 4px 12px -2px rgba(99,102,241,0.5);
        }
        .adm-mast__mark img { width: 100%; height: 100%; object-fit: contain; border-radius: 10px; }

        .adm-card {
            background: var(--adm-card); border: 1px solid var(--adm-line);
            border-radius: 16px; box-shadow: var(--adm-shadow);
            padding: 36px 38px 32px; backdrop-filter: blur(6px);
        }
        @media (max-width: 520px) { .adm-card { padding: 28px 22px 26px; border-radius: 14px; } }

        /* Anchor icon for the empty card */
        .adm-icon {
            width: 56px; height: 56px; border-radius: 14px; margin: 0 auto 18px;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, rgba(99,102,241,0.18) 0%, rgba(139,92,246,0.18) 100%);
            border: 1px solid rgba(99,102,241,0.3);
            color: color-mix(in srgb, var(--adm-brand) 50%, #fff);
            font-size: 22px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
        }

        .adm-head { text-align: center; margin-bottom: 22px; }
        .adm-head h1 {
            font-size: 22px; font-weight: 700; color: var(--adm-text);
            letter-spacing: -0.022em; margin: 0 0 8px; line-height: 1.25;
        }
        .adm-head p { font-size: 13.5px; color: var(--adm-muted); margin: 0; line-height: 1.55; }

        .adm-form { display: flex; flex-direction: column; gap: 16px; }
        .adm-field { display: flex; flex-direction: column; gap: 5px; }
        .adm-field__label {
            font-size: 12.5px; font-weight: 600; color: var(--adm-text-2); line-height: 1.4;
        }
        .adm-input {
            width: 100%; padding: 12px 14px; font-size: 14px; color: var(--adm-text);
            background: rgba(255,255,255,0.04); border: 1px solid var(--adm-line);
            border-radius: 9px; font-family: inherit; line-height: 1.45; outline: none;
            transition: border-color .15s var(--adm-ease),
                        background-color .15s var(--adm-ease),
                        box-shadow .15s var(--adm-ease);
        }
        .adm-input::placeholder { color: var(--adm-subtle); }
        .adm-input:hover { border-color: rgba(255,255,255,0.16); background: rgba(255,255,255,0.06); }
        .adm-input:focus {
            border-color: var(--adm-brand); background: rgba(255,255,255,0.07);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.22);
        }
        .adm-input:-webkit-autofill, .adm-input:-webkit-autofill:hover, .adm-input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--adm-text);
            -webkit-box-shadow: 0 0 0px 1000px var(--adm-card-2) inset;
            transition: background-color 5000s ease-in-out 0s;
        }

        .adm-submit {
            width: 100%; padding: 13px 18px; font-size: 14px; font-weight: 600;
            color: #fff; background: linear-gradient(135deg, var(--adm-brand) 0%, var(--adm-brand-2) 100%);
            border: 0; border-radius: 9px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.18), 0 6px 16px -4px rgba(99,102,241,0.5);
            transition: filter .15s var(--adm-ease), box-shadow .15s var(--adm-ease), transform .1s;
            margin-top: 6px;
        }
        .adm-submit:hover {
            filter: brightness(1.08);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.22), 0 10px 22px -4px rgba(99,102,241,0.55);
        }
        .adm-submit:active { transform: translateY(1px); }
        .adm-submit i { font-size: 12px; }

        .adm-note {
            margin-top: 16px; padding: 11px 14px; border-radius: 9px;
            background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.18);
            display: flex; align-items: flex-start; gap: 10px;
            font-size: 12px; color: var(--adm-text-2); line-height: 1.5;
        }
        .adm-note i { color: color-mix(in srgb, var(--adm-brand) 50%, #fff); font-size: 12px; margin-top: 2px; flex-shrink: 0; }

        .adm-flash {
            margin-bottom: 18px; padding: 12px 14px; border-radius: 9px;
            background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.25);
            color: #6ee7b7; font-size: 13px; line-height: 1.5;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .adm-flash i { color: #10b981; font-size: 14px; margin-top: 2px; flex-shrink: 0; }

        .adm-switch {
            margin-top: 22px; padding-top: 20px;
            border-top: 1px solid var(--adm-line-soft);
            text-align: center; font-size: 13px; color: var(--adm-muted);
        }
        .adm-switch a {
            color: color-mix(in srgb, var(--adm-brand) 60%, #fff);
            font-weight: 600; margin-left: 4px;
        }
        .adm-switch a:hover { text-decoration: underline; color: #fff; }
    </style>

    <section class="adm-auth">
        <div class="adm-stage">

            <div class="adm-back-top">
                <a href="{{ route('admin.login') }}">
                    <i class="fas fa-arrow-left"></i>
                    {{ __('Back to admin sign in') }}
                </a>
            </div>

            <div class="adm-mast">
                <a href="{{ route('home') }}" class="adm-mast__logo">
                    <span class="adm-mast__mark">
                        @if(!empty($setting?->logo))
                            <img src="{{ asset($setting->logo) }}" alt="{{ $setting?->app_name }}">
                        @else
                            <i class="fas fa-shield-alt"></i>
                        @endif
                    </span>
                    <span>{{ $setting?->app_name ?? config('app.name', 'Admin') }}</span>
                </a>
            </div>

            <div class="adm-card">

                @if(session('status') || session('success'))
                    <div class="adm-flash">
                        <i class="fas fa-check-circle"></i>
                        <span>{{ session('status') ?? session('success') }}</span>
                    </div>
                @endif

                <div class="adm-icon" aria-hidden="true">
                    <i class="fas fa-envelope-open-text"></i>
                </div>

                <div class="adm-head">
                    <h1>{{ __('Reset admin password') }}</h1>
                    <p>{{ __('Enter your operator email and we will send you a secure link to set a new password.') }}</p>
                </div>

                <form action="{{ route('admin.forget-password') }}" method="POST" class="adm-form">
                    @csrf
                    <div class="adm-field">
                        <label for="admin-forgot-email" class="adm-field__label">{{ __('Admin email') }}</label>
                        <input id="admin-forgot-email" type="email" name="email"
                               class="adm-input"
                               value="{{ old('email') }}"
                               placeholder="admin@example.com"
                               autocomplete="email" tabindex="1" autofocus required>
                    </div>

                    <button id="adminLoginBtn" type="submit" class="adm-submit" tabindex="2">
                        <i class="fas fa-paper-plane"></i>
                        <span>{{ __('Send reset link') }}</span>
                    </button>
                </form>

                <div class="adm-note">
                    <i class="fas fa-info-circle"></i>
                    <span>{{ __("The link expires in 60 minutes and arrives within a few minutes. Check your spam folder if you don't see it.") }}</span>
                </div>

                <div class="adm-switch">
                    {{ __('Remembered it?') }}<a href="{{ route('admin.login') }}">{{ __('Sign in') }}</a>
                </div>
            </div>

        </div>
    </section>
@endsection
