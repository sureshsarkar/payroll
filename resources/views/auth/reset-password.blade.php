@extends('frontend.layouts.master')
@section('meta_title', __('Set a new password') . ' || ' . $brand->name) {{-- P4 — per-coach white-label --}}

@section('contents')
    {{--
        Per-coach white-label password reset (token-validated step).

        Design intent (2026-05): corporate single-column auth card,
        matching the rest of the auth set. This is the final step of
        the reset flow — user clicked the link in their email and
        the token + email pair is already known to be valid. So:
          * Email shown read-only with a "from your reset link"
            helper — editing it would just fail the token match.
          * Lock icon anchors the card and signals security context.
          * Password strength meter (reused from register) helps
            users pick something strong on the spot.
          * Show / hide toggles on both password fields.

        $brand auto-injected by the BrandResolver view composer.

        Functional contract preserved 1:1 from the legacy template:
          * POST route('reset-password-store', $token)
          * email field (now readonly) + password + password_confirmation
          * conditional reCAPTCHA
          * Switch link → route('login')
          * frontend.validation-error component on every input
    --}}

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    <style>
        /* Scoped to .auth-shell — same tokens as login + register
           + forgot-password. */
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
            max-width: 460px;
            margin: 0 auto;
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
            .auth-card { padding: 32px 22px 28px; border-radius: 14px; }
        }

        /* Lock anchor icon — security context */
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
            margin-bottom: 22px;
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

        /* ── Account-context strip (replaces the email field) ─── */
        .auth-account {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: color-mix(in srgb, var(--auth-brand) 5%, #ffffff);
            border: 1px solid var(--auth-line);
            border-radius: 10px;
            margin-bottom: 18px;
        }
        .auth-account__avatar {
            width: 36px; height: 36px;
            border-radius: 9px;
            background: var(--auth-brand);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -0.02em;
            flex-shrink: 0;
        }
        .auth-account__body {
            min-width: 0;
            flex: 1;
        }
        .auth-account__label {
            display: block;
            font-size: 11.5px;
            color: var(--auth-subtle);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .auth-account__email {
            display: block;
            font-size: 14px;
            color: var(--auth-text);
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ── Form ─────────────────────────────────────────────── */
        .auth-form { display: flex; flex-direction: column; gap: 14px; }

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
        .auth-input--pw { padding-right: 56px; }

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

        /* Password strength meter */
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

        /* Password match indicator */
        .auth-match {
            font-size: 11.5px;
            margin-top: 4px;
            display: block;
            min-height: 14px;
            font-weight: 500;
            color: var(--auth-muted);
        }
        .auth-match.is-ok      { color: #10b981; }
        .auth-match.is-mismatch { color: #ef4444; }

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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .auth-submit:hover {
            background: color-mix(in srgb, var(--auth-brand) 88%, #000);
            box-shadow: 0 4px 12px -2px color-mix(in srgb, var(--auth-brand) 35%, transparent);
        }
        .auth-submit:active { transform: translateY(1px); }
        .auth-submit i { font-size: 12px; }

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

        /* ── Trust row ───────────────────────────────────────── */
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
    </style>

    {{-- Pre-compute display avatar initial from the user's email or
         brand fallback. Pure presentational, no security impact. --}}
    @php
        $emailLocal = explode('@', $user->email ?? '')[0] ?? '';
        $resetInitial = strtoupper(substr($emailLocal, 0, 1)) ?: 'A';
    @endphp

    <section class="auth-shell">
        <div class="auth-stage">

            {{-- ── Card ───────────────────────────────────────── --}}
            <div class="auth-card">

                {{-- Lock anchor icon --}}
                <div class="auth-icon" aria-hidden="true">
                    <i class="fas fa-lock"></i>
                </div>

                <div class="auth-head">
                    <h1>{{ __('Set a new password') }}</h1>
                    <p>{{ __('Choose a strong password you haven\'t used here before.') }}</p>
                </div>

                {{-- Account context strip — replaces the editable email
                     field. The email is locked because it must match the
                     token; an editable field would just confuse users. --}}
                <div class="auth-account">
                    <div class="auth-account__avatar">{{ $resetInitial }}</div>
                    <div class="auth-account__body">
                        <span class="auth-account__label">{{ __('Resetting password for') }}</span>
                        <span class="auth-account__email" title="{{ $user->email }}">{{ $user->email }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('reset-password-store', $token) }}" class="auth-form" novalidate>
                    @csrf

                    {{-- Email is sent as a hidden input so the controller
                         still receives it on submit (token + email pair
                         validation). Display is the account strip above. --}}
                    <input type="hidden" name="email" value="{{ $user->email }}">
                    <x-frontend.validation-error name="email" />

                    {{-- New password --}}
                    <div class="auth-field">
                        <div class="auth-field__head">
                            <label for="password" class="auth-field__label">{{ __('New password') }}</label>
                            <span class="auth-field__hint">{{ __('8+ characters') }}</span>
                        </div>
                        <div class="auth-input-wrap">
                            <input id="password" name="password" type="password"
                                   class="auth-input auth-input--pw"
                                   placeholder="{{ __('Choose a strong password') }}"
                                   autocomplete="new-password" required autofocus
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

                    {{-- Confirm new password --}}
                    <div class="auth-field">
                        <div class="auth-field__head">
                            <label for="password_confirmation" class="auth-field__label">{{ __('Confirm new password') }}</label>
                        </div>
                        <div class="auth-input-wrap">
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                   class="auth-input auth-input--pw"
                                   placeholder="{{ __('Re-enter password') }}"
                                   autocomplete="new-password" required
                                   data-match-target>
                            <button type="button" class="auth-input-toggle" data-toggle-pw="password_confirmation">
                                {{ __('Show') }}
                            </button>
                        </div>
                        <small class="auth-match" data-match-label></small>
                        <x-frontend.validation-error name="password_confirmation" />
                    </div>

                    {{-- reCAPTCHA --}}
                    @if (Cache::get('setting')->recaptcha_status === 'active')
                        <div class="auth-field auth-recaptcha">
                            <div class="g-recaptcha"
                                 data-sitekey="{{ Cache::get('setting')->recaptcha_site_key }}"></div>
                            <x-frontend.validation-error name="g-recaptcha-response" />
                        </div>
                    @endif

                    <button type="submit" class="auth-submit">
                        <i class="fas fa-check"></i>
                        <span>{{ __('Reset password') }}</span>
                    </button>
                </form>

                <div class="auth-switch">
                    {{ __('Remembered it?') }}<a href="{{ route('login') }}">{{ __('Sign in instead') }}</a>
                </div>
            </div>

            {{-- ── Trust row ──────────────────────────────────── --}}
            <div class="auth-trust">
                <span class="auth-trust__item"><i class="fas fa-shield-alt"></i> {{ __('End-to-end encrypted') }}</span>
                <span class="auth-trust__item"><i class="fas fa-key"></i> {{ __('Single-use reset link') }}</span>
                <span class="auth-trust__item"><i class="fas fa-user-shield"></i> {{ __('Auto sign-out elsewhere') }}</span>
            </div>

        </div>
    </section>

    <script>
        (function () {
            // Password show / hide toggles
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

            // Password strength meter
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

            // Live "passwords match" feedback on the confirm field
            var confirm  = document.querySelector('[data-match-target]');
            var matchLbl = document.querySelector('[data-match-label]');
            var MATCH_OK    = @json(__('Passwords match'));
            var MATCH_BAD   = @json(__("Passwords don't match"));
            function updateMatch() {
                if (!pw || !confirm || !matchLbl) return;
                var a = pw.value || '';
                var b = confirm.value || '';
                matchLbl.classList.remove('is-ok', 'is-mismatch');
                if (b.length === 0) { matchLbl.textContent = ''; return; }
                if (a === b) {
                    matchLbl.textContent = MATCH_OK;
                    matchLbl.classList.add('is-ok');
                } else {
                    matchLbl.textContent = MATCH_BAD;
                    matchLbl.classList.add('is-mismatch');
                }
            }
            if (confirm) confirm.addEventListener('input', updateMatch);
            if (pw)      pw.addEventListener('input', updateMatch);
        })();
    </script>
@endsection
