@extends('admin.auth.app')
@section('title')
    <title>{{ __('Set new admin password') }} | {{ $setting?->app_name ?? config('app.name') }}</title>
@endsection
@section('content')
    {{--
        Admin password reset (token-validated) — corporate redesign (2026-05).

        Matches /admin/login + /admin/forgot-password dark theme. The
        email is locked (token-bound — editing it can only fail) and
        shown as an account-context strip. Password fields get show/hide
        toggles, strength meter, and live match feedback.

        Functional contract preserved 1:1:
          * POST route('admin.password.reset-store', $token)
          * email (hidden + display strip), password, password_confirmation
          * Back link → route('admin.login')
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

            position: relative; min-height: 100vh;
            background:
                radial-gradient(at 20% 0%, color-mix(in srgb, var(--adm-brand) 18%, transparent), transparent 50%),
                radial-gradient(at 80% 100%, color-mix(in srgb, var(--adm-brand-2) 14%, transparent), transparent 50%),
                linear-gradient(180deg, var(--adm-bg) 0%, var(--adm-bg-2) 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            padding: 56px 16px 60px;
            display: flex; align-items: center; justify-content: center;
            color: var(--adm-text); overflow: hidden;
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

        .adm-stage { position: relative; z-index: 1; max-width: 460px; width: 100%; }

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
            padding: 36px 38px 32px;
        }
        @media (max-width: 520px) { .adm-card { padding: 28px 22px 26px; border-radius: 14px; } }

        .adm-icon {
            width: 56px; height: 56px; border-radius: 14px; margin: 0 auto 16px;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, rgba(99,102,241,0.18) 0%, rgba(139,92,246,0.18) 100%);
            border: 1px solid rgba(99,102,241,0.3);
            color: color-mix(in srgb, var(--adm-brand) 50%, #fff);
            font-size: 22px;
        }

        .adm-head { text-align: center; margin-bottom: 20px; }
        .adm-head h1 {
            font-size: 22px; font-weight: 700; color: var(--adm-text);
            letter-spacing: -0.022em; margin: 0 0 8px; line-height: 1.25;
        }
        .adm-head p { font-size: 13.5px; color: var(--adm-muted); margin: 0; line-height: 1.55; }

        /* Account context strip — locked email display */
        .adm-account {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 14px; border-radius: 9px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--adm-line);
            margin-bottom: 18px;
        }
        .adm-account__avatar {
            width: 36px; height: 36px; border-radius: 9px;
            background: linear-gradient(135deg, var(--adm-brand) 0%, var(--adm-brand-2) 100%);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700; flex-shrink: 0;
        }
        .adm-account__body { flex: 1; min-width: 0; }
        .adm-account__label {
            display: block; font-size: 11px; color: var(--adm-subtle);
            text-transform: uppercase; letter-spacing: 0.06em;
            font-weight: 600; margin-bottom: 2px;
        }
        .adm-account__email {
            display: block; font-size: 13.5px; color: var(--adm-text); font-weight: 600;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .adm-form { display: flex; flex-direction: column; gap: 14px; }
        .adm-field { display: flex; flex-direction: column; gap: 5px; }
        .adm-field__head {
            display: flex; align-items: baseline; justify-content: space-between; gap: 8px;
        }
        .adm-field__label {
            font-size: 12.5px; font-weight: 600; color: var(--adm-text-2); line-height: 1.4;
        }
        .adm-field__hint {
            font-size: 11.5px; color: var(--adm-subtle); font-weight: 500;
        }

        .adm-input-wrap { position: relative; display: flex; align-items: center; }
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
        .adm-input--pw { padding-right: 56px; }

        .adm-input-toggle {
            position: absolute; right: 6px;
            background: transparent; border: 0;
            padding: 7px 10px; font-size: 12px; font-weight: 600;
            color: var(--adm-muted); cursor: pointer; border-radius: 6px;
            transition: background-color .15s var(--adm-ease), color .15s var(--adm-ease);
        }
        .adm-input-toggle:hover { background: rgba(255,255,255,0.06); color: var(--adm-text); }

        .adm-strength { display: flex; gap: 4px; margin-top: 4px; height: 3px; }
        .adm-strength__seg {
            flex: 1; background: var(--adm-line); border-radius: 2px;
            transition: background-color .2s var(--adm-ease);
        }
        .adm-strength__seg.is-1 { background: #ef4444; }
        .adm-strength__seg.is-2 { background: #f59e0b; }
        .adm-strength__seg.is-3 { background: #10b981; }
        .adm-strength__label {
            font-size: 11.5px; color: var(--adm-muted);
            margin-top: 4px; display: block; min-height: 14px; font-weight: 500;
        }
        .adm-match {
            font-size: 11.5px; margin-top: 4px; display: block; min-height: 14px;
            font-weight: 500; color: var(--adm-muted);
        }
        .adm-match.is-ok      { color: #10b981; }
        .adm-match.is-mismatch { color: #ef4444; }

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

    @php
        $emailLocal = explode('@', $admin->email ?? '')[0] ?? '';
        $resetInitial = strtoupper(substr($emailLocal, 0, 1)) ?: 'A';
    @endphp

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

                <div class="adm-icon" aria-hidden="true"><i class="fas fa-lock"></i></div>

                <div class="adm-head">
                    <h1>{{ __('Set a new admin password') }}</h1>
                    <p>{{ __("Choose a strong password you haven't used here before.") }}</p>
                </div>

                <div class="adm-account">
                    <div class="adm-account__avatar">{{ $resetInitial }}</div>
                    <div class="adm-account__body">
                        <span class="adm-account__label">{{ __('Resetting password for') }}</span>
                        <span class="adm-account__email" title="{{ $admin->email }}">{{ $admin->email }}</span>
                    </div>
                </div>

                <form action="{{ route('admin.password.reset-store', $token) }}" method="POST" class="adm-form" novalidate>
                    @csrf
                    <input type="hidden" name="email" value="{{ $admin->email }}">

                    <div class="adm-field">
                        <div class="adm-field__head">
                            <label for="admin-reset-password" class="adm-field__label">{{ __('New password') }}</label>
                            <span class="adm-field__hint">{{ __('8+ characters') }}</span>
                        </div>
                        <div class="adm-input-wrap">
                            <input id="admin-reset-password" type="password" name="password"
                                   class="adm-input adm-input--pw"
                                   placeholder="{{ __('Choose a strong password') }}"
                                   autocomplete="new-password" required minlength="8"
                                   autofocus data-strength-target>
                            <button type="button" class="adm-input-toggle" data-toggle-pw="admin-reset-password">
                                {{ __('Show') }}
                            </button>
                        </div>
                        <div class="adm-strength" aria-hidden="true">
                            <span class="adm-strength__seg" data-strength-seg="1"></span>
                            <span class="adm-strength__seg" data-strength-seg="2"></span>
                            <span class="adm-strength__seg" data-strength-seg="3"></span>
                        </div>
                        <small class="adm-strength__label" data-strength-label></small>
                    </div>

                    <div class="adm-field">
                        <div class="adm-field__head">
                            <label for="admin-reset-password-confirmation" class="adm-field__label">{{ __('Confirm new password') }}</label>
                        </div>
                        <div class="adm-input-wrap">
                            <input id="admin-reset-password-confirmation" type="password" name="password_confirmation"
                                   class="adm-input adm-input--pw"
                                   placeholder="{{ __('Re-enter password') }}"
                                   autocomplete="new-password" required minlength="8"
                                   data-match-target>
                            <button type="button" class="adm-input-toggle" data-toggle-pw="admin-reset-password-confirmation">
                                {{ __('Show') }}
                            </button>
                        </div>
                        <small class="adm-match" data-match-label></small>
                    </div>

                    <button id="adminLoginBtn" type="submit" class="adm-submit" tabindex="3">
                        <i class="fas fa-check"></i>
                        <span>{{ __('Reset password') }}</span>
                    </button>
                </form>

                <div class="adm-switch">
                    {{ __('Remembered it?') }}<a href="{{ route('admin.login') }}">{{ __('Sign in instead') }}</a>
                </div>
            </div>

        </div>
    </section>

    <script>
        (function () {
            document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var input = document.getElementById(btn.getAttribute('data-toggle-pw'));
                    if (!input) return;
                    var isPw = input.type === 'password';
                    input.type = isPw ? 'text' : 'password';
                    btn.textContent = isPw ? @json(__('Hide')) : @json(__('Show'));
                });
            });

            var pw    = document.querySelector('[data-strength-target]');
            var segs  = document.querySelectorAll('[data-strength-seg]');
            var label = document.querySelector('[data-strength-label]');
            var LABELS = { 0:'', 1:@json(__('Weak')), 2:@json(__('Okay')), 3:@json(__('Strong')) };
            if (pw && segs.length && label) {
                pw.addEventListener('input', function () {
                    var v = pw.value || '', score = 0;
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

            var confirm = document.querySelector('[data-match-target]');
            var matchLbl = document.querySelector('[data-match-label]');
            var MATCH_OK = @json(__('Passwords match')), MATCH_BAD = @json(__("Passwords don't match"));
            function updateMatch() {
                if (!pw || !confirm || !matchLbl) return;
                var a = pw.value || '', b = confirm.value || '';
                matchLbl.classList.remove('is-ok', 'is-mismatch');
                if (b.length === 0) { matchLbl.textContent = ''; return; }
                if (a === b) { matchLbl.textContent = MATCH_OK; matchLbl.classList.add('is-ok'); }
                else         { matchLbl.textContent = MATCH_BAD; matchLbl.classList.add('is-mismatch'); }
            }
            if (confirm) confirm.addEventListener('input', updateMatch);
            if (pw)      pw.addEventListener('input', updateMatch);
        })();
    </script>
@endsection
