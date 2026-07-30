@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

@php
    // $row is the raw CoachBrandSetting (what the coach has explicitly
    // set — many fields will be null). $brand is the COMPOSED Brand
    // (coach overrides + platform defaults + fallbacks) we use for
    // "this is what students currently see" previews so the coach can
    // see what's inherited vs what they've customised.
@endphp

<style>
    /* Scoped helpers for the brand form. corp-form-card / corp-field
       primitives cover most surfaces; we only need a couple of extras. */
    .brand-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }
    @media (max-width: 768px) {
        .brand-form-grid { grid-template-columns: 1fr; }
    }
    .brand-asset-preview {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px;
        background: var(--corp-card-grad);
        border: 1px dashed var(--corp-line);
        border-radius: 10px;
    }
    .brand-asset-preview__img {
        width: 64px; height: 64px;
        border-radius: 10px;
        background: #fff;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
        border: 1px solid var(--corp-line);
        flex-shrink: 0;
    }
    .brand-asset-preview__img img { max-width: 100%; max-height: 100%; }
    .brand-asset-preview__meta { flex: 1; min-width: 0; }
    .brand-asset-preview__meta__name { font-weight: 600; font-size: 13px; color: var(--corp-text); }
    .brand-asset-preview__meta__hint { font-size: 11.5px; color: var(--corp-muted); margin-top: 2px; }
    .brand-color-input {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .brand-color-input input[type=color] {
        width: 44px; height: 40px;
        border: 1px solid var(--corp-line);
        border-radius: 9px;
        padding: 2px;
        background: #fff;
        cursor: pointer;
    }
    .brand-color-input input[type=text] {
        flex: 1;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }
    .brand-source-pill {
        font-size: 10.5px;
        font-weight: 600;
        padding: 2px 9px;
        border-radius: 999px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .brand-source-pill--coach    { background: #ecfdf5; color: #047857; }
    .brand-source-pill--platform { background: #f3f4f6; color: #64748b; }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
/* Most of this page rides on var(--corp-*) tokens which already re-theme; only the few
   hardcoded neutral surfaces below need a dark counterpart. */
html[data-theme="dark"] .brand-asset-preview__img{ background:#1e293b; }
html[data-theme="dark"] .brand-color-input input[type=color]{ background:#1e293b; }
html[data-theme="dark"] .brand-source-pill--platform{ background:#22304a; color:#94a3b8; }
</style>

<div class="corp-page" id="brandSettings">

    {{-- Header ────────────────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Brand Settings') }}</h4>
            <p>
                {{ __('Your brand identity for the students you serve. Anything you leave blank inherits the platform default — fill in only what you want to override.') }}
            </p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.setting.index') }}" class="btn-corp-secondary">
                <i class="fas fa-arrow-left"></i> {{ __('Back to Settings') }}
            </a>
        </div>
    </div>

    @if (session('messege'))
        <div class="corp-form-card" style="border-color:#a7f3d0; background:#ecfdf5;">
            <div class="corp-form-card__body" style="padding:12px 16px; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-check-circle" style="color:#047857;"></i>
                <strong style="color:#065f46; font-size:13px;">{{ session('messege') }}</strong>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="corp-form-card" style="border-color:#fecaca; background:#fef2f2;">
            <div class="corp-form-card__body" style="padding:14px 18px;">
                <div style="display:flex; gap:10px; align-items:flex-start; color:#b91c1c; font-size:13px;">
                    <i class="fas fa-exclamation-circle" style="font-size:16px; margin-top:2px;"></i>
                    <div>
                        <strong>{{ __('Please correct the following:') }}</strong>
                        <ul style="margin:6px 0 0; padding-left:18px;">
                            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Live preview banner ───────────────────────────────────── --}}
    <div class="corp-form-card">
        <div class="corp-form-card__head">
            <h6 class="corp-form-card__title">
                <i class="fas fa-eye" style="color:var(--corp-brand);"></i>
                {{ __('Live Preview') }}
                <span class="brand-source-pill {{ $brand->isPlatformDefault ? 'brand-source-pill--platform' : 'brand-source-pill--coach' }}"
                      style="margin-left:auto;">
                    {{ $brand->isPlatformDefault ? __('Platform default') : __('Your brand') }}
                </span>
            </h6>
        </div>
        <div class="corp-form-card__body" style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
            <div style="width:56px; height:56px; border-radius:12px; background:linear-gradient(135deg, {{ $brand->primaryColor }}, {{ $brand->accentColor }}); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px; box-shadow:0 4px 12px -2px {{ $brand->primaryColor }}40;">
                @if ($brand->logoUrl())
                    <img src="{{ $brand->logoUrl() }}" alt="" style="max-width:48px; max-height:48px; border-radius:8px;">
                @else
                    {{ $brand->initials() }}
                @endif
            </div>
            <div style="flex:1; min-width:200px;">
                <div style="font-size:18px; font-weight:800; color:var(--corp-text); letter-spacing:-0.02em;">{{ $brand->name }}</div>
                <div style="font-size:12px; color:var(--corp-muted); margin-top:2px;">
                    <i class="fas fa-envelope" style="font-size:10px;"></i> {{ $brand->supportEmail }}
                    @if ($brand->supportPhone)
                        · <i class="fas fa-phone" style="font-size:10px;"></i> {{ $brand->supportPhone }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('instructor.brand-settings.update') }}" enctype="multipart/form-data" id="brandForm">
        @csrf

        {{-- Identity card ─────────────────────────────────────── --}}
        <div class="corp-form-card">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title">
                    <i class="fas fa-tag" style="color:var(--corp-brand);"></i>
                    {{ __('Identity') }}
                </h6>
                <p class="corp-form-card__sub">{{ __('Brand name and logo that appears on student-facing pages and emails.') }}</p>
            </div>
            <div class="corp-form-card__body">
                <div class="brand-form-grid">
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('Brand Name') }}</label>
                        <input type="text" name="brand_name" class="corp-input"
                               maxlength="80"
                               value="{{ old('brand_name', $row->brand_name) }}"
                               placeholder="{{ __('e.g.') }} {{ $brand->name }}">
                        <div class="corp-field__hint">{{ __('Leave blank to use the platform name.') }}</div>
                    </div>
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('Support Email') }}</label>
                        <input type="email" name="support_email" class="corp-input"
                               maxlength="120"
                               value="{{ old('support_email', $row->support_email) }}"
                               placeholder="support@yourbrand.com">
                        <div class="corp-field__hint">{{ __('Where students should email for help. Defaults to platform contact.') }}</div>
                    </div>
                </div>

                {{-- Logo upload ───────────────────────────────── --}}
                <div class="corp-field" style="margin-top:14px;">
                    <label class="corp-field__label">{{ __('Logo') }}</label>
                    <div class="brand-asset-preview">
                        <div class="brand-asset-preview__img">
                            @if ($row->logo_path)
                                <img src="{{ asset($row->logo_path) }}" alt="">
                            @else
                                <i class="fas fa-image" style="color:var(--corp-subtle); font-size:22px;"></i>
                            @endif
                        </div>
                        <div class="brand-asset-preview__meta">
                            <div class="brand-asset-preview__meta__name">
                                {{ $row->logo_path ? basename($row->logo_path) : __('No custom logo — platform default is shown') }}
                            </div>
                            <div class="brand-asset-preview__meta__hint">{{ __('PNG / JPG / SVG / WebP — up to 2 MB.') }}</div>
                        </div>
                    </div>
                    <input type="file" name="logo" class="corp-input" style="margin-top:8px; padding:6px;"
                           accept="image/png,image/jpeg,image/svg+xml,image/webp">
                    @if ($row->logo_path)
                        <label style="display:inline-flex; align-items:center; gap:6px; margin-top:8px; font-size:12px; color:var(--corp-muted); cursor:pointer;">
                            <input type="checkbox" name="clear_logo" value="1"> {{ __('Remove current logo') }}
                        </label>
                    @endif
                </div>

                {{-- Favicon upload ────────────────────────────── --}}
                <div class="corp-field" style="margin-top:14px;">
                    <label class="corp-field__label">{{ __('Favicon') }}</label>
                    <div class="brand-asset-preview">
                        <div class="brand-asset-preview__img" style="width:32px; height:32px;">
                            @if ($row->favicon_path)
                                <img src="{{ asset($row->favicon_path) }}" alt="">
                            @else
                                <i class="fas fa-bookmark" style="color:var(--corp-subtle); font-size:14px;"></i>
                            @endif
                        </div>
                        <div class="brand-asset-preview__meta">
                            <div class="brand-asset-preview__meta__name">
                                {{ $row->favicon_path ? basename($row->favicon_path) : __('No custom favicon') }}
                            </div>
                            <div class="brand-asset-preview__meta__hint">{{ __('Square, 32 × 32 ideal. PNG / ICO / SVG — up to 512 KB.') }}</div>
                        </div>
                    </div>
                    <input type="file" name="favicon" class="corp-input" style="margin-top:8px; padding:6px;"
                           accept="image/png,image/x-icon,image/svg+xml">
                    @if ($row->favicon_path)
                        <label style="display:inline-flex; align-items:center; gap:6px; margin-top:8px; font-size:12px; color:var(--corp-muted); cursor:pointer;">
                            <input type="checkbox" name="clear_favicon" value="1"> {{ __('Remove current favicon') }}
                        </label>
                    @endif
                </div>
            </div>
        </div>

        {{-- Colors card ───────────────────────────────────────── --}}
        <div class="corp-form-card">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title">
                    <i class="fas fa-palette" style="color:var(--corp-brand);"></i>
                    {{ __('Colors') }}
                </h6>
                <p class="corp-form-card__sub">{{ __('Primary is your accent / CTA color. Accent is the second swatch for gradients.') }}</p>
            </div>
            <div class="corp-form-card__body">
                <div class="brand-form-grid">
                    <div class="corp-field">
                        <label class="corp-field__label" for="brand_primary_color_text">{{ __('Primary Color') }}</label>
                        {{-- 2026-05-29 UI/UX audit P1-2: upgraded color picker with
                             swatch presets + WCAG-44 native picker + monospace text. --}}
                        <div class="cs-color-input">
                            <input type="color"
                                   aria-label="{{ __('Primary color picker') }}"
                                   value="{{ old('primary_color', $row->primary_color ?? '#10b981') }}"
                                   oninput="this.nextElementSibling.value=this.value.toUpperCase()">
                            <input type="text" name="primary_color" id="brand_primary_color_text" class="corp-input"
                                   pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$"
                                   value="{{ old('primary_color', $row->primary_color) }}"
                                   placeholder="{{ __('e.g.') }} {{ $brand->primaryColor }}"
                                   oninput="this.previousElementSibling.value=this.value">
                        </div>
                        <div class="cs-color-presets" role="group" aria-label="{{ __('Preset primary colors') }}">
                            @foreach (['#10b981','#059669','#EC4899','#EF4444','#F59E0B','#10B981','#0EA5E9','#0F172A'] as $preset)
                                <button type="button"
                                        title="{{ $preset }}"
                                        aria-label="{{ __('Use preset') }} {{ $preset }}"
                                        style="background:{{ $preset }};"
                                        onclick="(function(b){const w=b.closest('.corp-field');const c=w.querySelector('input[type=color]');const t=w.querySelector('input[type=text]');c.value='{{ $preset }}';t.value='{{ $preset }}';})(this)"></button>
                            @endforeach
                        </div>
                    </div>
                    <div class="corp-field">
                        <label class="corp-field__label" for="brand_accent_color_text">{{ __('Accent Color') }}</label>
                        <div class="cs-color-input">
                            <input type="color"
                                   aria-label="{{ __('Accent color picker') }}"
                                   value="{{ old('accent_color', $row->accent_color ?? '#059669') }}"
                                   oninput="this.nextElementSibling.value=this.value.toUpperCase()">
                            <input type="text" name="accent_color" id="brand_accent_color_text" class="corp-input"
                                   pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$"
                                   value="{{ old('accent_color', $row->accent_color) }}"
                                   placeholder="{{ __('e.g.') }} {{ $brand->accentColor }}"
                                   oninput="this.previousElementSibling.value=this.value">
                        </div>
                        <div class="cs-color-presets" role="group" aria-label="{{ __('Preset accent colors') }}">
                            @foreach (['#059669','#A855F7','#D946EF','#F472B6','#FB923C','#FBBF24','#84CC16','#22D3EE'] as $preset)
                                <button type="button"
                                        title="{{ $preset }}"
                                        aria-label="{{ __('Use preset') }} {{ $preset }}"
                                        style="background:{{ $preset }};"
                                        onclick="(function(b){const w=b.closest('.corp-field');const c=w.querySelector('input[type=color]');const t=w.querySelector('input[type=text]');c.value='{{ $preset }}';t.value='{{ $preset }}';})(this)"></button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Contact + legal card ──────────────────────────────── --}}
        <div class="corp-form-card">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title">
                    <i class="fas fa-address-card" style="color:var(--corp-brand);"></i>
                    {{ __('Contact & Legal') }}
                </h6>
                <p class="corp-form-card__sub">{{ __('Phone and legal links shown in your footer + emails.') }}</p>
            </div>
            <div class="corp-form-card__body">
                <div class="brand-form-grid">
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('Support Phone') }}</label>
                        <input type="text" name="support_phone" class="corp-input"
                               maxlength="40"
                               value="{{ old('support_phone', $row->support_phone) }}"
                               placeholder="+91 98765 43210">
                    </div>
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('Footer Text') }}</label>
                        <input type="text" name="footer_text" class="corp-input"
                               maxlength="255"
                               value="{{ old('footer_text', $row->footer_text) }}"
                               placeholder="© {{ date('Y') }} {{ $brand->name }}">
                    </div>
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('Terms URL') }}</label>
                        <input type="url" name="terms_url" class="corp-input"
                               maxlength="500"
                               value="{{ old('terms_url', $row->terms_url) }}"
                               placeholder="https://yourbrand.com/terms">
                    </div>
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('Privacy URL') }}</label>
                        <input type="url" name="privacy_url" class="corp-input"
                               maxlength="500"
                               value="{{ old('privacy_url', $row->privacy_url) }}"
                               placeholder="https://yourbrand.com/privacy">
                    </div>
                </div>
            </div>
        </div>

        {{-- Email signature card ──────────────────────────────── --}}
        <div class="corp-form-card">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title">
                    <i class="fas fa-signature" style="color:var(--corp-brand);"></i>
                    {{ __('Email Signature') }}
                </h6>
                <p class="corp-form-card__sub">{{ __('Appended to outgoing emails (welcome, receipts, announcements).') }}</p>
            </div>
            <div class="corp-form-card__body">
                <div class="corp-field">
                    <label class="corp-field__label">{{ __('Signature') }}</label>
                    <textarea name="email_signature" class="corp-input"
                              rows="4" maxlength="2000"
                              style="height:auto; padding:10px 12px; font-family:inherit;"
                              placeholder="—&#10;{{ $brand->name }} Team&#10;{{ $brand->supportEmail }}">{{ old('email_signature', $row->email_signature) }}</textarea>
                </div>
            </div>
        </div>

        {{-- P3 — Email From + SMTP card ────────────────────────── --}}
        <div class="corp-form-card">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title">
                    <i class="fas fa-envelope-open" style="color:var(--corp-brand);"></i>
                    {{ __('Email Sender') }}
                    @if ($row->smtp_verified_at)
                        <span class="corp-pill corp-pill--success" style="margin-left:auto;">
                            <i class="fas fa-check-circle"></i> {{ __('SMTP verified') }}
                            <span style="font-weight:500; margin-left:4px;">{{ $row->smtp_verified_at->diffForHumans() }}</span>
                        </span>
                    @elseif ($row->smtp_host)
                        <span class="corp-pill corp-pill--warning" style="margin-left:auto;">
                            <i class="fas fa-triangle-exclamation"></i> {{ __('SMTP unverified') }}
                        </span>
                    @endif
                </h6>
                <p class="corp-form-card__sub">
                    {{ __('Two tiers: set just the From address (uses platform SMTP, your address) — OR override SMTP entirely (your mail server, your delivery reputation).') }}
                </p>
            </div>
            <div class="corp-form-card__body">

                {{-- Tier 1: header overrides --}}
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--corp-muted); margin-bottom:10px;">
                    {{ __('Tier 1 — From / Reply-To override') }}
                </div>
                <div class="brand-form-grid">
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('From Address') }}</label>
                        <input type="email" name="mail_from_address" class="corp-input"
                               maxlength="120"
                               value="{{ old('mail_from_address', $row->mail_from_address) }}"
                               placeholder="hello@yourbrand.com">
                        <div class="corp-field__hint">{{ __('Outgoing email From header. Leave blank to use platform default.') }}</div>
                    </div>
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('From Name') }}</label>
                        <input type="text" name="mail_from_name" class="corp-input"
                               maxlength="120"
                               value="{{ old('mail_from_name', $row->mail_from_name) }}"
                               placeholder="{{ $brand->name }} Team">
                    </div>
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('Reply-To') }}</label>
                        <input type="email" name="mail_reply_to" class="corp-input"
                               maxlength="120"
                               value="{{ old('mail_reply_to', $row->mail_reply_to) }}"
                               placeholder="support@yourbrand.com">
                    </div>
                </div>

                {{-- Tier 2: full SMTP --}}
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--corp-muted); margin:20px 0 10px; padding-top:14px; border-top:1px dashed var(--corp-line-soft);">
                    {{ __('Tier 2 — Full SMTP override (optional)') }}
                </div>
                <div class="brand-form-grid">
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('SMTP Host') }}</label>
                        <input type="text" name="smtp_host" id="smtp_host" class="corp-input"
                               maxlength="200"
                               value="{{ old('smtp_host', $row->smtp_host) }}"
                               placeholder="smtp.sendgrid.net">
                    </div>
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('Port') }}</label>
                        <input type="number" name="smtp_port" id="smtp_port" class="corp-input"
                               min="1" max="65535"
                               value="{{ old('smtp_port', $row->smtp_port) }}"
                               placeholder="587">
                    </div>
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('Username') }}</label>
                        <input type="text" name="smtp_username" id="smtp_username" class="corp-input"
                               maxlength="200"
                               autocomplete="off"
                               value="{{ old('smtp_username', $row->smtp_username) }}">
                    </div>
                    <div class="corp-field">
                        <label class="corp-field__label">
                            {{ __('Password') }}
                            @if ($row->smtp_password_encrypted)
                                <span style="font-size:10px; font-weight:500; color:var(--corp-muted); margin-left:4px;">
                                    ({{ __('leave blank to keep existing') }})
                                </span>
                            @endif
                        </label>
                        <input type="password" name="smtp_password" id="smtp_password" class="corp-input"
                               maxlength="200"
                               autocomplete="new-password"
                               placeholder="{{ $row->smtp_password_encrypted ? '••••••••' : '' }}">
                    </div>
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('Encryption') }}</label>
                        <select name="smtp_encryption" id="smtp_encryption" class="corp-select">
                            <option value="tls"  @selected(old('smtp_encryption', $row->smtp_encryption) === 'tls')>TLS</option>
                            <option value="ssl"  @selected(old('smtp_encryption', $row->smtp_encryption) === 'ssl')>SSL</option>
                            <option value="none" @selected(old('smtp_encryption', $row->smtp_encryption) === 'none')>{{ __('None') }}</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px; align-items:center; margin-top:14px; flex-wrap:wrap;">
                    <button type="button" class="btn-corp-secondary" id="testSmtpBtn">
                        <i class="fas fa-plug"></i> {{ __('Test SMTP') }}
                    </button>
                    @if ($row->smtp_host)
                        <label style="display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--corp-muted); cursor:pointer;">
                            <input type="checkbox" name="clear_smtp" value="1">
                            {{ __('Clear all SMTP fields on save') }}
                        </label>
                    @endif
                    <span id="testSmtpResult" style="font-size:12.5px; margin-left:auto;"></span>
                </div>

                <p style="font-size:11.5px; color:var(--corp-muted); margin-top:14px;">
                    <i class="fas fa-shield-alt" style="color:var(--corp-brand);"></i>
                    {{ __('Your SMTP password is encrypted at rest. If SMTP delivery fails, we automatically fall back to the platform mailer so your students still get their email — and log the failure for you to fix.') }}
                </p>
            </div>
        </div>

    </form>

    {{-- P5 — Custom domains card. SEPARATE form from the brand
         settings save because the verify / delete actions are AJAX
         + per-row; wrapping them in the main form would either
         submit the form when a coach clicks Verify, or require
         nested forms (illegal HTML). --}}
    <div class="corp-form-card" id="domainsCard">
        <div class="corp-form-card__head">
            <h6 class="corp-form-card__title">
                <i class="fas fa-globe" style="color:var(--corp-brand);"></i>
                {{ __('Domains') }}
            </h6>
            <p class="corp-form-card__sub">
                {{ __('Get an instant branded link below — no setup needed. Or connect your own domain with a single DNS record.') }}
            </p>
        </div>
        <div class="corp-form-card__body">

            {{-- 2026-06-06 — Instant branded link (zero DNS). The coach gets
                 <label>.<platform> live immediately and can rename the label
                 here. Requires wildcard DNS + TLS for *.<platform> on the
                 server (one-time operator setup). --}}
            @if (!empty($platformHost))
                @php
                    $subHost = $subdomainRow->hostname ?? $platformHost;
                    $subUrl  = 'https://' . $subHost;
                @endphp
                <div style="border:1px solid var(--corp-line); border-radius:12px; padding:18px 20px; margin-bottom:22px;
                            background:linear-gradient(135deg, rgba(41,166,92,.06), rgba(79,190,128,.04));">
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                        <i class="fas fa-bolt" style="color:var(--corp-brand);"></i>
                        <strong style="font-size:14px;">{{ __('Your instant branded link') }}</strong>
                        <span class="corp-pill corp-pill--success" style="font-size:10.5px;">
                            <i class="fas fa-check-circle"></i> {{ __('Live — no DNS needed') }}
                        </span>
                    </div>

                    @if ($subdomainRow)
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:10px 0 14px;">
                            <code id="subdomainHost" style="font-size:14px; padding:7px 12px; background:#fff;
                                   border:1px solid var(--corp-line); border-radius:8px; user-select:all;">{{ $subHost }}</code>
                            <button type="button" class="btn-corp-secondary" id="copySubdomainBtn"
                                    data-copy="{{ $subUrl }}" style="padding:6px 12px; font-size:12px;">
                                <i class="far fa-copy"></i> {{ __('Copy') }}
                            </button>
                            <a href="{{ $subUrl }}" target="_blank" rel="noopener"
                               class="btn-corp-secondary" style="padding:6px 12px; font-size:12px;">
                                <i class="fas fa-external-link-alt"></i> {{ __('Open') }}
                            </a>
                        </div>
                    @else
                        <p style="font-size:12.5px; color:var(--corp-muted); margin:8px 0 14px;">
                            {{ __('Pick a name to claim your free branded link — it goes live instantly.') }}
                        </p>
                    @endif

                    <form action="{{ route('instructor.domains.subdomain') }}" method="POST">
                        @csrf
                        <label class="corp-field__label">
                            {{ $subdomainRow ? __('Rename your subdomain') : __('Choose your subdomain') }}
                        </label>
                        <div style="display:flex; align-items:stretch; gap:0; flex-wrap:wrap; max-width:560px;">
                            <input type="text" name="slug"
                                   value="{{ old('slug', $subdomainSlug) }}"
                                   class="corp-input"
                                   placeholder="yourbrand"
                                   pattern="[a-z0-9]([a-z0-9-]*[a-z0-9])?"
                                   minlength="3" maxlength="40" required
                                   style="border-top-right-radius:0; border-bottom-right-radius:0; flex:1; min-width:160px;">
                            <span style="display:inline-flex; align-items:center; padding:0 12px; font-size:13px;
                                         color:var(--corp-muted); background:#f3f4f6; border:1px solid var(--corp-line);
                                         border-left:none; border-top-right-radius:8px; border-bottom-right-radius:8px;
                                         white-space:nowrap;">.{{ $platformHost }}</span>
                            <button type="submit" class="btn-corp-primary" style="margin-left:8px;">
                                <i class="fas fa-check"></i> {{ $subdomainRow ? __('Rename') : __('Claim') }}
                            </button>
                        </div>
                        @error('slug')
                            <small style="color:var(--corp-danger,#dc2626); display:block; margin-top:6px;">{{ $message }}</small>
                        @enderror
                        <small style="display:block; margin-top:6px; color:var(--corp-muted); font-size:11.5px;">
                            {{ __('3–40 letters, numbers or hyphens (e.g. yoga-with-virendra). Changes apply instantly.') }}
                        </small>
                    </form>
                </div>
            @endif

            {{-- Custom-domain header --}}
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:var(--corp-muted);
                        font-weight:700; margin:6px 0 10px;">
                {{ __('Your own domain (optional)') }}
            </div>

            {{-- Existing CUSTOM domains (the subdomain is shown above) --}}
            @php $customDomains = $domains->where('kind', 'custom'); @endphp
            @if ($customDomains->isEmpty())
                <div class="corp-empty" style="padding:30px 16px;">
                    <div class="corp-empty__icon" style="width:48px;height:48px;font-size:18px;">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="corp-empty__title">{{ __('No custom domain yet') }}</div>
                    <div class="corp-empty__hint">
                        {{ __('Your instant link above already works. Add your own domain below only if you want to use yourbrand.com.') }}
                    </div>
                </div>
            @else
                <div class="corp-table-wrap" style="border:none;">
                    <table class="corp-table">
                        <thead>
                            <tr>
                                <th>{{ __('Hostname') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th style="width:200px; text-align:right;">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customDomains as $d)
                                <tr data-row-id="{{ $d->id }}">
                                    <td>
                                        <code style="font-size:12px;">{{ $d->hostname }}</code>
                                    </td>
                                    <td>
                                        @php
                                            $st = [
                                                'pending'   => ['#fffbeb', '#92400e', 'fa-clock', __('Pending')],
                                                'verified'  => ['#eff6ff', '#1d4ed8', 'fa-hourglass-half', __('Awaiting approval')],
                                                'active'    => ['#ecfdf5', '#047857', 'fa-check-circle', __('Active')],
                                                'failed'    => ['#fef2f2', '#991b1b', 'fa-exclamation-circle', __('Failed')],
                                                'suspended' => ['#f3f4f6', '#374151', 'fa-ban', __('Suspended')],
                                            ][$d->status] ?? ['#fffbeb', '#92400e', 'fa-clock', $d->status];
                                        @endphp
                                        <span class="corp-pill" style="background:{{ $st[0] }}; color:{{ $st[1] }};">
                                            <i class="fas {{ $st[2] }}"></i> {{ $st[3] }}
                                        </span>
                                        @if ($d->status === 'failed' && $d->last_error)
                                            <div style="font-size:11px; color:#b91c1c; margin-top:4px;">{{ $d->last_error }}</div>
                                        @elseif ($d->status === 'suspended')
                                            <div style="font-size:11px; color:var(--corp-muted); margin-top:4px;">{{ __('Suspended by the administrator. Please contact support.') }}</div>
                                        @elseif ($d->status === 'verified')
                                            <div style="font-size:11px; color:var(--corp-muted); margin-top:4px;">{{ __('DNS verified — an administrator will review it shortly.') }}</div>
                                        @endif
                                        @if ($d->last_verified_at)
                                            <div style="font-size:10.5px; color:var(--corp-muted); margin-top:3px;">{{ __('Last checked') }}: {{ $d->last_verified_at->diffForHumans() }}</div>
                                        @endif
                                    </td>
                                    <td style="text-align:right;">
                                        @if (in_array($d->status, ['pending', 'failed']))
                                            <button type="button"
                                                    class="btn-corp-secondary verify-domain-btn"
                                                    data-id="{{ $d->id }}"
                                                    style="padding:5px 12px; font-size:11px;">
                                                <i class="fas fa-plug"></i> {{ __('Verify') }}
                                            </button>
                                        @endif
                                        <form action="{{ route('instructor.domains.destroy', $d->id) }}"
                                              method="POST" style="display:inline;"
                                              onsubmit="return confirm('{{ __('Remove this domain? Your students using it will fall back to your subdomain.') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="corp-actions__btn corp-actions__btn--danger"
                                                    title="{{ __('Remove') }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                {{-- 2026-06-06 — ONE-record verification. Point the domain
                                     at us with a single A (or CNAME) record, then Verify.
                                     No TXT step. (Legacy TXT is still accepted server-side
                                     and shown as an advanced fallback.) --}}
                                @if (in_array($d->status, ['pending', 'failed']))
                                    <tr data-instructions-for="{{ $d->id }}">
                                        <td colspan="3" style="background:#fafbfc; padding:14px 18px;">
                                            <div style="font-size:12px; color:var(--corp-text); line-height:1.6;">
                                                <strong>{{ __('Point your domain to us — just one record:') }}</strong>
                                                <ol style="margin:6px 0 0; padding-left:20px;">
                                                    <li>{{ __('In your DNS provider for') }} <code>{{ $d->hostname }}</code>, {{ __('add ONE of these:') }}
                                                        <div style="margin-top:6px; display:flex; flex-direction:column; gap:6px;">
                                                            @if (!empty($platformIp))
                                                                <div style="padding:8px 12px; background:#fff; border:1px solid var(--corp-line); border-radius:6px; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:11.5px;">
                                                                    <span style="color:var(--corp-muted);">{{ __('A record') }}</span>&nbsp;&nbsp;<strong>@</strong> &rarr; <span style="user-select:all;">{{ $platformIp }}</span>
                                                                </div>
                                                            @endif
                                                            @if (!empty($platformCnameTarget))
                                                                <div style="padding:8px 12px; background:#fff; border:1px solid var(--corp-line); border-radius:6px; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:11.5px;">
                                                                    <span style="color:var(--corp-muted);">{{ __('or CNAME') }}</span>&nbsp;&nbsp;<strong>@/www</strong> &rarr; <span style="user-select:all;">{{ $platformCnameTarget }}</span>
                                                                </div>
                                                            @endif
                                                            @if (empty($platformIp) && empty($platformCnameTarget))
                                                                <div style="padding:8px 12px; background:#fff; border:1px solid var(--corp-line); border-radius:6px; font-size:11.5px; color:var(--corp-muted);">
                                                                    {{ __('Ask your platform operator for the IP / hostname to point at.') }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </li>
                                                    <li>{{ __('Click Verify above. DNS changes can take a few minutes to propagate.') }}</li>
                                                </ol>
                                                <details style="margin-top:8px;">
                                                    <summary style="cursor:pointer; color:var(--corp-muted); font-size:11px;">{{ __('Prefer to verify by TXT record instead?') }}</summary>
                                                    <div style="margin-top:6px; font-size:11.5px;">
                                                        {{ __('Add this TXT record on the apex, then Verify:') }}
                                                        <div style="margin-top:6px; padding:8px 12px; background:#fff; border:1px solid var(--corp-line); border-radius:6px; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; user-select:all;">
                                                            {{ $domainTokens[$d->id] }}
                                                        </div>
                                                    </div>
                                                </details>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Add a custom domain (2026-06: gated by the superadmin feature
                 flag + per-coach quota). --}}
            @if (! $customDomainEnabled)
                <div class="corp-empty" style="padding:18px; margin-top:16px;">
                    <div class="corp-empty__hint">
                        {{ __('Custom domains are not enabled on your plan. Your instant branded link above is fully active.') }}
                    </div>
                </div>
            @elseif ($customDomainCount >= $customDomainMax)
                <div class="corp-empty" style="padding:18px; margin-top:16px;">
                    <div class="corp-empty__hint">
                        {{ __('You have reached your custom-domain limit (:max).', ['max' => $customDomainMax]) }}
                    </div>
                </div>
            @else
                <form action="{{ route('instructor.domains.store') }}" method="POST"
                      style="margin-top:18px; display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
                    @csrf
                    <div class="corp-field" style="flex:1; min-width:240px; margin:0;">
                        <label class="corp-field__label">{{ __('Add your own domain') }}</label>
                        <input type="text" name="hostname" class="corp-input"
                               placeholder="yourbrand.com"
                               maxlength="255" required>
                        <small style="display:block; margin-top:6px; color:var(--corp-muted); font-size:11.5px;">
                            {{ __('After adding, point it at us with one DNS record and click Verify.') }}
                        </small>
                    </div>
                    <button type="submit" class="btn-corp-primary">
                        <i class="fas fa-plus"></i> {{ __('Add') }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- P5 layout note: the Domains card sits as a sibling of the
         main brand form (NOT inside it) because verify/delete are
         AJAX/per-row operations with their own lifecycle. The
         sticky bar below uses the HTML5 form="brandForm" attribute
         to associate its submit button with the brand form even
         though they're no longer parent/child in the DOM. --}}

    {{-- Sticky save bar ─────────────────────────────────────────── --}}
    <div class="corp-sticky-bar">
        <div class="corp-sticky-bar__summary">
            <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
            <span>
                <strong>{{ __('Saving updates immediately.') }}</strong>
                {{ __('Changes apply to your students on their next page load.') }}
            </span>
        </div>
        <div class="corp-sticky-bar__actions">
            <button type="submit" form="brandForm" class="btn-corp-primary">
                <i class="fas fa-check"></i> {{ __('Save Brand Settings') }}
            </button>
        </div>
    </div>
</div>

<script>
// P5 — Verify Domain button. POSTs to the verify endpoint and
// updates the row's status pill inline based on the JSON response.
(function () {
    const verifyUrlTpl = "{{ url('instructor/domains/__ID__/verify') }}";
    const token = document.querySelector('input[name=_token]')?.value;

    document.querySelectorAll('.verify-domain-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const url = verifyUrlTpl.replace('__ID__', id);
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ __('Checking…') }}';

            try {
                const body = new FormData();
                body.append('_token', token);
                const r = await fetch(url, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                const j = await r.json();
                if (j.ok) {
                    // Found the TXT record — reload so the table re-
                    // renders with the verified pill.
                    location.reload();
                } else {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    // Replace any prior message with the new one.
                    let msg = btn.parentElement.querySelector('.verify-err');
                    if (! msg) {
                        msg = document.createElement('div');
                        msg.className = 'verify-err';
                        msg.style.cssText = 'font-size:11px; color:#b91c1c; margin-top:6px;';
                        btn.parentElement.appendChild(msg);
                    }
                    msg.textContent = j.message || 'Verification failed.';
                }
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        });
    });
})();

(function () {
    // 2026-06-06 — Copy the instant subdomain URL to the clipboard.
    const btn = document.getElementById('copySubdomainBtn');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        const text = btn.dataset.copy || '';
        const original = btn.innerHTML;
        try {
            await navigator.clipboard.writeText(text);
        } catch (e) {
            // Fallback for non-secure contexts.
            const ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch (_) {}
            document.body.removeChild(ta);
        }
        btn.innerHTML = '<i class="fas fa-check"></i> {{ __('Copied') }}';
        setTimeout(() => { btn.innerHTML = original; }, 1600);
    });
})();

(function () {
    // P3 — Test SMTP button. POSTs the current SMTP form values
    // (NOT the persisted ones) to the verify endpoint and shows the
    // outcome inline. Coach only saves after a green result.
    const btn = document.getElementById('testSmtpBtn');
    const out = document.getElementById('testSmtpResult');
    if (!btn) return;

    const url   = "{{ route('instructor.brand-settings.test-smtp') }}";
    const token = document.querySelector('input[name=_token]')?.value;

    btn.addEventListener('click', async () => {
        const pw = document.getElementById('smtp_password').value;
        if (!pw) {
            out.innerHTML = '<span style="color:#b91c1c;"><i class="fas fa-exclamation-circle"></i> {{ __('Enter the SMTP password before testing.') }}</span>';
            return;
        }

        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ __('Testing…') }}';
        out.innerHTML = '';

        try {
            const body = new FormData();
            body.append('_token', token);
            body.append('smtp_host',         document.getElementById('smtp_host').value);
            body.append('smtp_port',         document.getElementById('smtp_port').value || '587');
            body.append('smtp_username',     document.getElementById('smtp_username').value);
            body.append('smtp_password',     pw);
            body.append('smtp_encryption',   document.getElementById('smtp_encryption').value);
            body.append('mail_from_address', document.querySelector('input[name=mail_from_address]').value || '');
            body.append('mail_from_name',    document.querySelector('input[name=mail_from_name]').value || '');

            const r = await fetch(url, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            const j = await r.json();
            if (j.ok) {
                out.innerHTML = '<span style="color:#047857;"><i class="fas fa-check-circle"></i> {{ __('Test email sent — check your inbox.') }}</span>';
            } else {
                const msg = (j.error || '').replace(/</g, '&lt;');
                out.innerHTML = '<span style="color:#b91c1c;"><i class="fas fa-exclamation-circle"></i> '
                    + '{{ __('SMTP failed') }}: <code>' + msg + '</code></span>';
            }
        } catch (e) {
            out.innerHTML = '<span style="color:#b91c1c;"><i class="fas fa-exclamation-circle"></i> {{ __('Test request failed.') }}</span>';
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });
})();
</script>
@endsection
