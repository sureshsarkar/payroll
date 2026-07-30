@extends('frontend.layouts.master')
@section('meta_title', __('Become a coach') . ' || ' . $brand->name) {{-- P4 — per-coach white-label --}}

@section('contents')
    {{--
        Become Instructor (coach upgrade) — corporate redesign (2026-05).

        Design intent: same auth-shell pattern as register/login (Inter
        font, centered card, brand-tinted radial glow, "For Coaches"
        category pill) but adapted for an application/upload form
        rather than a credentials form.

        Per-coach white-label: $brand drives every accent (pill, focus
        rings, submit button, links). On a coach's domain the page
        retints to that coach.

        Functional contract preserved 1:1 from the legacy template:
          * POST route('become-instructor.create') with @csrf + multipart
          * Conditional certificate file (when need_certificate == 1)
          * Conditional identity_scan file (when need_identity_scan == 1)
          * payout_account <select> + dynamic payment_information block
            shown via the .payment_info_wrap + .payment-{name} pattern
          * payout_information textarea
          * extra_information textarea
          * Conditional reCAPTCHA
          * Submit button text branches on userAuth()->role
          * $instructorRequestSetting->instructions HTML rendered via clean()
    --}}

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    <style>
        .bi-shell {
            --bi-brand:       {{ $brand->primaryColor ?: '#6366f1' }};
            --bi-brand-2:     {{ $brand->accentColor  ?: '#8b5cf6' }};
            --bi-text:        #0b1220;
            --bi-text-2:      #1f2937;
            --bi-muted:       #6b7280;
            --bi-subtle:      #9ca3af;
            --bi-line:        #e5e7eb;
            --bi-line-soft:   #f1f3f5;
            --bi-bg:          #fafbfc;
            --bi-card:        #ffffff;
            --bi-shadow:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 8px 24px -6px rgba(15, 23, 42, 0.08),
                0 24px 64px -16px rgba(15, 23, 42, 0.10);
            --bi-ease: cubic-bezier(.22, 1, .36, 1);

            position: relative;
            background: var(--bi-bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            padding: 88px 16px 80px;
            min-height: 100vh;
            overflow: hidden;
        }
        @media (max-width: 768px) { .bi-shell { padding: 64px 16px 60px; } }
        .bi-shell *,
        .bi-shell *::before,
        .bi-shell *::after { box-sizing: border-box; }

        .bi-shell::before {
            content: '';
            position: absolute; top: -240px; left: 50%;
            transform: translateX(-50%);
            width: 1000px; height: 540px;
            background: radial-gradient(closest-side,
                color-mix(in srgb, var(--bi-brand) 14%, transparent), transparent 70%);
            pointer-events: none; z-index: 0;
        }

        .bi-stage { position: relative; z-index: 1; max-width: 640px; margin: 0 auto; }

        /* Category pill */
        .bi-pill-wrap { text-align: center; margin-bottom: 18px; }
        .bi-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px 5px 10px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--bi-brand) 10%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--bi-brand) 22%, transparent);
            color: color-mix(in srgb, var(--bi-brand) 78%, #000);
            font-size: 12px; font-weight: 600; letter-spacing: 0.02em;
        }
        .bi-pill__dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--bi-brand);
        }

        /* Card */
        .bi-card {
            background: var(--bi-card);
            border: 1px solid var(--bi-line);
            border-radius: 16px;
            box-shadow: var(--bi-shadow);
            padding: 40px 44px 36px;
        }
        @media (max-width: 520px) {
            .bi-card { padding: 28px 22px 26px; border-radius: 14px; }
        }

        .bi-head { margin-bottom: 24px; text-align: center; }
        .bi-head h1 {
            font-size: 24px; font-weight: 700; color: var(--bi-text);
            letter-spacing: -0.022em; margin: 0 0 8px; line-height: 1.2;
        }
        .bi-head p {
            font-size: 14px; color: var(--bi-muted); margin: 0; line-height: 1.5;
        }

        /* Instructions panel — admin-configured HTML rendered via clean() */
        .bi-instructions {
            padding: 16px 18px;
            background: color-mix(in srgb, var(--bi-brand) 5%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--bi-brand) 14%, transparent);
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 13px;
            line-height: 1.55;
            color: var(--bi-text-2);
        }
        .bi-instructions h1, .bi-instructions h2, .bi-instructions h3,
        .bi-instructions h4, .bi-instructions h5, .bi-instructions h6 {
            font-size: 13.5px; font-weight: 700; color: var(--bi-text);
            margin: 0 0 8px;
        }
        .bi-instructions p { margin: 0 0 10px; }
        .bi-instructions p:last-child { margin-bottom: 0; }
        .bi-instructions ul, .bi-instructions ol { margin: 0 0 10px 18px; padding: 0; }
        .bi-instructions li { margin-bottom: 4px; }
        .bi-instructions a { color: var(--bi-brand); font-weight: 600; }
        .bi-instructions a:hover { text-decoration: underline; }

        /* Form */
        .bi-form { display: flex; flex-direction: column; gap: 16px; }

        .bi-field { display: flex; flex-direction: column; gap: 6px; }
        .bi-field__head {
            display: flex; align-items: baseline; justify-content: space-between; gap: 8px;
        }
        .bi-field__label {
            font-size: 13px; font-weight: 600; color: var(--bi-text-2); line-height: 1.4;
        }
        .bi-field__hint {
            font-size: 12px; color: var(--bi-subtle); font-weight: 500;
        }
        .bi-required {
            display: inline-block;
            color: #ef4444;
            font-weight: 700;
            margin-left: 2px;
        }

        .bi-input,
        .bi-select,
        .bi-textarea {
            width: 100%; padding: 11px 14px; font-size: 14px; color: var(--bi-text);
            background: #fff; border: 1px solid var(--bi-line);
            border-radius: 10px; font-family: inherit; line-height: 1.45; outline: none;
            transition: border-color .15s var(--bi-ease), box-shadow .15s var(--bi-ease);
        }
        .bi-input::placeholder,
        .bi-textarea::placeholder { color: var(--bi-subtle); }
        .bi-input:hover, .bi-select:hover, .bi-textarea:hover { border-color: #cbd5e1; }
        .bi-input:focus, .bi-select:focus, .bi-textarea:focus {
            border-color: var(--bi-brand);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--bi-brand) 18%, transparent);
        }

        .bi-textarea { min-height: 110px; resize: vertical; }

        .bi-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%236b7280' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 38px;
            cursor: pointer;
        }

        /* File input — custom styled drop target */
        .bi-file {
            position: relative; display: flex; align-items: center; gap: 10px;
            padding: 14px 16px; background: #fff;
            border: 1.5px dashed var(--bi-line); border-radius: 10px;
            cursor: pointer; transition: all .15s var(--bi-ease);
        }
        .bi-file:hover {
            border-color: color-mix(in srgb, var(--bi-brand) 50%, var(--bi-line));
            background: color-mix(in srgb, var(--bi-brand) 4%, #ffffff);
        }
        .bi-file input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer;
        }
        .bi-file__icon {
            width: 36px; height: 36px; border-radius: 9px;
            background: color-mix(in srgb, var(--bi-brand) 10%, #ffffff);
            color: var(--bi-brand);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
            border: 1px solid color-mix(in srgb, var(--bi-brand) 16%, transparent);
        }
        .bi-file__body { flex: 1; min-width: 0; }
        .bi-file__title {
            font-size: 13px; font-weight: 600; color: var(--bi-text); line-height: 1.35;
        }
        .bi-file__hint {
            font-size: 11.5px; color: var(--bi-muted); margin-top: 2px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* Payment information panel — slides in when a method is selected */
        .bi-payment-panel {
            padding: 16px 18px; border-radius: 10px;
            background: color-mix(in srgb, var(--bi-brand) 4%, #fafbfc);
            border: 1px solid var(--bi-line-soft);
            display: flex; flex-direction: column; gap: 10px;
        }
        .bi-payment-info {
            font-size: 12.5px; color: var(--bi-text-2); line-height: 1.55;
            padding-bottom: 10px; border-bottom: 1px solid var(--bi-line-soft);
        }
        .bi-payment-info:empty { display: none; }
        .bi-payment-info p { margin: 0 0 6px; }
        .bi-payment-info p:last-child { margin-bottom: 0; }
        .bi-payment-info a { color: var(--bi-brand); font-weight: 600; }

        /* Submit */
        .bi-submit {
            width: 100%; padding: 13px 18px; font-size: 14.5px; font-weight: 600;
            color: #fff; background: var(--bi-brand);
            border: 0; border-radius: 10px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            transition: background-color .15s var(--bi-ease),
                        box-shadow .15s var(--bi-ease), transform .1s;
            margin-top: 6px;
        }
        .bi-submit:hover {
            background: color-mix(in srgb, var(--bi-brand) 88%, #000);
            box-shadow: 0 4px 12px -2px color-mix(in srgb, var(--bi-brand) 35%, transparent);
        }
        .bi-submit:active { transform: translateY(1px); }
        .bi-submit i { font-size: 12px; }

        /* Trust strip */
        .bi-trust {
            margin-top: 24px; display: flex; align-items: center; justify-content: center;
            gap: 18px; font-size: 12px; color: var(--bi-subtle); flex-wrap: wrap;
        }
        .bi-trust__item { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; }
        .bi-trust__item i {
            color: color-mix(in srgb, var(--bi-brand) 70%, #000); font-size: 11px;
        }

        /* Field error rendering — preserves x-frontend.validation-error look */
        .bi-field .invalid-feedback,
        .bi-field .text-danger,
        .bi-field [role="alert"] {
            color: #dc2626 !important;
            font-size: 12.5px; margin-top: 2px; display: block; line-height: 1.4;
        }

        .bi-recaptcha .g-recaptcha > div { margin: 0 !important; }
    </style>

    <section class="bi-shell">
        <div class="bi-stage">

            <div class="bi-pill-wrap">
                <span class="bi-pill">
                    <span class="bi-pill__dot"></span>
                    {{ __('For Coaches') }}
                </span>
            </div>

            <div class="bi-card">
                <div class="bi-head">
                    <h1>
                        @if(userAuth()->role == 'instructor')
                            {{ __('Update your coach profile') }}
                        @else
                            {{ __('Become a coach') }}
                        @endif
                    </h1>
                    <p>
                        @if(userAuth()->role == 'instructor')
                            {{ __('Resubmit your documents and payout details for review.') }}
                        @else
                            {{ __('Upload your details and start teaching on this platform. Approval usually takes 1-2 business days.') }}
                        @endif
                    </p>
                </div>

                @if(!empty($instructorRequestSetting?->instructions))
                    <div class="bi-instructions">
                        {!! clean($instructorRequestSetting->instructions) !!}
                    </div>
                @endif

                <form method="POST" action="{{ route('become-instructor.create') }}" class="bi-form" enctype="multipart/form-data">
                    @csrf

                    @if ($instructorRequestSetting?->need_certificate == 1)
                        <div class="bi-field">
                            <div class="bi-field__head">
                                <label class="bi-field__label">
                                    {{ __('Certificates & documents') }} <span class="bi-required">*</span>
                                </label>
                                <span class="bi-field__hint">{{ __('PDF, JPG, PNG') }}</span>
                            </div>
                            <label class="bi-file" data-file-input>
                                <span class="bi-file__icon"><i class="fas fa-file-upload"></i></span>
                                <span class="bi-file__body">
                                    <span class="bi-file__title" data-file-title>{{ __('Click to upload certificate') }}</span>
                                    <span class="bi-file__hint" data-file-hint>{{ __('Drop or browse from your computer') }}</span>
                                </span>
                                <input type="file" name="certificate" data-file-target>
                            </label>
                            <x-frontend.validation-error name="certificate" />
                        </div>
                    @endif

                    @if ($instructorRequestSetting?->need_identity_scan == 1)
                        <div class="bi-field">
                            <div class="bi-field__head">
                                <label class="bi-field__label">
                                    {{ __('Identity scan') }} <span class="bi-required">*</span>
                                </label>
                                <span class="bi-field__hint">{{ __('Passport or government ID') }}</span>
                            </div>
                            <label class="bi-file" data-file-input>
                                <span class="bi-file__icon"><i class="fas fa-id-card"></i></span>
                                <span class="bi-file__body">
                                    <span class="bi-file__title" data-file-title>{{ __('Click to upload ID') }}</span>
                                    <span class="bi-file__hint" data-file-hint>{{ __('Both sides if applicable') }}</span>
                                </span>
                                <input type="file" name="identity_scan" data-file-target>
                            </label>
                            <x-frontend.validation-error name="identity_scan" />
                        </div>
                    @endif

                    <div class="bi-field">
                        <div class="bi-field__head">
                            <label for="payout_account" class="bi-field__label">
                                {{ __('Payout account') }} <span class="bi-required">*</span>
                            </label>
                        </div>
                        <select name="payout_account" id="payout_account" class="bi-select">
                            <option value="">{{ __('Select payment method') }}</option>
                            @foreach ($withdrawMethods as $method)
                                <option value="{{ $method->name }}">{{ $method->name }}</option>
                            @endforeach
                        </select>
                        <x-frontend.validation-error name="payout_account" />
                    </div>

                    <div class="bi-field payment_info_wrap d-none">
                        <div class="bi-field__head">
                            <label for="payout_information" class="bi-field__label">
                                {{ __('Payment information') }} <span class="bi-required">*</span>
                            </label>
                        </div>
                        <div class="bi-payment-panel">
                            @foreach ($withdrawMethods as $method)
                                <div class="bi-payment-info payment-{{ $method->name }} payment-info">
                                    {!! clean($method->description) !!}
                                </div>
                            @endforeach
                            <textarea name="payout_information" id="payout_information"
                                      placeholder="{{ __('Account number, IBAN, UPI, wallet ID — whatever your method needs') }}"
                                      class="bi-textarea"></textarea>
                        </div>
                        <x-frontend.validation-error name="payout_information" />
                    </div>

                    <div class="bi-field">
                        <div class="bi-field__head">
                            <label for="extra_information" class="bi-field__label">{{ __('Extra information') }}</label>
                            <span class="bi-field__hint">{{ __('Optional') }}</span>
                        </div>
                        <textarea name="extra_information" id="extra_information" class="bi-textarea"
                                  placeholder="{{ __('Anything else the reviewer should know — teaching experience, subject areas, languages') }}"></textarea>
                        <x-frontend.validation-error name="extra_information" />
                    </div>

                    @if (Cache::get('setting')->recaptcha_status === 'active')
                        <div class="bi-field bi-recaptcha">
                            <div class="g-recaptcha"
                                 data-sitekey="{{ Cache::get('setting')->recaptcha_site_key }}"></div>
                            <x-frontend.validation-error name="g-recaptcha-response" />
                        </div>
                    @endif

                    <button type="submit" class="bi-submit">
                        <i class="fas fa-paper-plane"></i>
                        <span>
                            @if(userAuth()->role == 'instructor')
                                {{ __('Submit update') }}
                            @else
                                {{ __('Submit for review') }}
                            @endif
                        </span>
                    </button>
                </form>
            </div>

            <div class="bi-trust">
                <span class="bi-trust__item"><i class="fas fa-shield-alt"></i> {{ __('Documents encrypted at rest') }}</span>
                <span class="bi-trust__item"><i class="fas fa-clock"></i> {{ __('Reviewed in 1-2 business days') }}</span>
                <span class="bi-trust__item"><i class="fas fa-envelope"></i> {{ __('Email confirmation on decision') }}</span>
            </div>

        </div>
    </section>

    <script>
        (function () {
            // File-input live label — shows the chosen filename so the user
            // knows the upload actually picked up.
            document.querySelectorAll('[data-file-input]').forEach(function (wrap) {
                var input = wrap.querySelector('[data-file-target]');
                var title = wrap.querySelector('[data-file-title]');
                var hint  = wrap.querySelector('[data-file-hint]');
                if (!input || !title) return;
                var DEFAULT_TITLE = title.textContent;
                var DEFAULT_HINT  = hint ? hint.textContent : '';
                input.addEventListener('change', function () {
                    var f = input.files && input.files[0];
                    if (f) {
                        title.textContent = f.name;
                        if (hint) {
                            var kb = (f.size / 1024).toFixed(0);
                            hint.textContent = kb + ' KB · ' + (f.type || 'unknown type');
                        }
                    } else {
                        title.textContent = DEFAULT_TITLE;
                        if (hint) hint.textContent = DEFAULT_HINT;
                    }
                });
            });

            // Payment-method picker — legacy behaviour preserved (jQuery on
            // the public site loads jQuery globally). The legacy template
            // toggled .payment_info_wrap and the per-method .payment-{name}
            // descriptions via inline scripts elsewhere; this re-implements
            // the toggle inline + framework-free so the form works even
            // if no global handler is registered.
            var sel  = document.getElementById('payout_account');
            var wrap = document.querySelector('.payment_info_wrap');
            if (sel && wrap) {
                var infos = wrap.querySelectorAll('.payment-info');
                function applyChoice() {
                    var v = sel.value;
                    if (!v) {
                        wrap.classList.add('d-none');
                        infos.forEach(function (i) { i.style.display = 'none'; });
                        return;
                    }
                    wrap.classList.remove('d-none');
                    infos.forEach(function (i) { i.style.display = 'none'; });
                    var match = wrap.querySelector('.payment-' + CSS.escape(v));
                    if (match) match.style.display = '';
                }
                sel.addEventListener('change', applyChoice);
                applyChoice();
            }
        })();
    </script>
@endsection
