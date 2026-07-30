@extends('admin.master_layout')

@section('title')
    <title>{{ __('Two-Factor Authentication') }} - {{ $setting->app_name ?? 'Admin' }}</title>
@endsection
@section('page-title', __('Two-Factor Authentication'))

@push('css')
{{-- M10 fix (2026-05-12) — the 2FA setup view had ~40 inline `style="…"`
     attributes inlined across 160 lines. Refactored to a single per-page
     <style> block with named classes. Same render, ~80 fewer inline-style
     bytes per element, far easier to theme. --}}
<style>
    .tfa-wrap { padding-top: 1.5rem; }
    .tfa-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:1.5rem; }
    .tfa-head h2 { margin:0; color:#1c1a4a; }
    .tfa-head h2 .ic { color:#5751e1; margin-right:8px; }
    .tfa-head p { color:#6b7280; margin:6px 0 0; font-size:14px; }

    .tfa-pill { padding:6px 14px; border-radius:6px; font-size:13px; font-weight:600; }
    .tfa-pill--on  { background:#dcfce7; color:#166534; }
    .tfa-pill--off { background:#fee2e2; color:#991b1b; }

    .tfa-alert { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; }
    .tfa-alert--ok  { background:#dcfce7; color:#166534; }
    .tfa-alert--err { background:#fee2e2; color:#991b1b; }

    .tfa-card { background:#fff; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.04); border:1px solid #e5e7eb; }
    .tfa-card h4 { margin:0 0 12px; color:#1c1a4a; }
    .tfa-card h4.success { color:#166534; }
    .tfa-card p { color:#6b7280; font-size:14px; margin-bottom:18px; }
    .tfa-card ol { color:#374151; font-size:14px; line-height:1.8; padding-left:20px; }

    .tfa-warning { background:#fffbeb; border:1px solid #fbbf24; border-radius:12px; padding:20px; margin-top:20px; }
    .tfa-warning h4 { margin:0 0 8px; color:#92400e; }
    .tfa-warning p  { margin:0 0 14px; color:#92400e; font-size:13px; }

    .tfa-soft-card { background:#fafbfc; border-radius:12px; padding:20px; margin-top:20px; border:1px solid #e5e7eb; }
    .tfa-soft-card h5 { margin:0 0 8px; color:#1c1a4a; font-size:14px; }

    .tfa-qr-frame { background:#fafbfc; padding:16px; border-radius:12px; border:1px solid #e5e7eb; }
    .tfa-qr-row { display:flex; gap:24px; flex-wrap:wrap; align-items:flex-start; }
    .tfa-qr-col { flex:1; min-width:240px; }
    .tfa-qr-col h5 { margin:24px 0 8px; color:#1c1a4a; font-size:15px; }
    .tfa-secret-label { font-size:13px; color:#6b7280; margin:0 0 8px; }

    .tfa-code-input { flex:1; padding:12px 14px; font-size:18px; letter-spacing:6px; text-align:center; border:2px solid #e5e7eb; border-radius:8px; font-family:monospace; }
    .tfa-code-form { display:flex; gap:8px; }

    .tfa-code { display:block; padding:10px 14px; background:#f3f4f6; border-radius:6px; font-family:'SF Mono', Consolas, monospace; font-size:13px; color:#1f2937; word-break:break-all; margin-bottom:14px; }
    .tfa-recovery-grid { display:grid; grid-template-columns: repeat(2, 1fr); gap:8px; }
    .tfa-recovery-grid--compact { gap:6px; }
    .tfa-recovery-code { background:#fff; padding:10px; border-radius:6px; font-family:'SF Mono', Consolas, monospace; font-size:13px; color:#1c1a4a; text-align:center; letter-spacing:1px; }
    .tfa-recovery-code--used-up { padding:8px; font-size:12px; color:#6b7280; border:1px solid #e5e7eb; }

    .tfa-btn { border:none; cursor:pointer; font-weight:600; }
    .tfa-btn--primary  { padding:10px 24px; background:#5751e1; color:#fff; border-radius:8px; font-size:14px; }
    .tfa-btn--confirm  { padding:12px 24px; background:#5751e1; color:#fff; border-radius:8px; font-weight:600; }
    .tfa-btn--outline  { padding:10px 18px; background:#fff; color:#5751e1; border:1px solid #5751e1; border-radius:8px; font-weight:500; font-size:13px; }
    .tfa-btn--danger   { padding:10px 18px; background:#fff; color:#dc2626; border:1px solid #dc2626; border-radius:8px; font-weight:500; font-size:13px; }
    .tfa-btn--ghost-danger { padding:6px 14px; background:#fff; color:#dc2626; border:1px solid #dc2626; border-radius:6px; font-size:12px; }
    .tfa-btn-row { display:flex; gap:10px; flex-wrap:wrap; }

    .tfa-cancel-details { margin-top:24px; font-size:12px; color:#6b7280; }
    .tfa-cancel-details summary { cursor:pointer; }

    .tfa-empty-recovery { color:#dc2626; font-size:13px; margin:0; }
</style>
@endpush

@section('admin-content')
<div class="container-fluid tfa-wrap">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="tfa-head">
                <div>
                    <h2>
                        <i class="fas fa-shield-alt ic"></i>
                        {{ __('Two-Factor Authentication') }}
                    </h2>
                    <p>{{ __('Add a second verification step at sign-in.') }}</p>
                </div>
                @if ($isEnabled)
                    <span class="tfa-pill tfa-pill--on">
                        <i class="fas fa-check-circle"></i> {{ __('Active') }}
                    </span>
                @else
                    <span class="tfa-pill tfa-pill--off">
                        <i class="fas fa-times-circle"></i> {{ __('Not Active') }}
                    </span>
                @endif
            </div>

            @if (session('messege') || session('message'))
                <div class="tfa-alert tfa-alert--ok">
                    {{ session('messege') ?? session('message') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="tfa-alert tfa-alert--err">
                    @foreach ($errors->all() as $err) {{ $err }}<br> @endforeach
                </div>
            @endif

            {{-- ============================================================ NOT ENROLLED ===== --}}
            @if (!$isEnabled && !$hasPending)
                <div class="tfa-card">
                    <h4>{{ __('How it works') }}</h4>
                    <ol>
                        <li>{{ __('Click "Begin enrollment" below.') }}</li>
                        <li>{{ __('Scan the QR code with Google Authenticator, Authy, 1Password, or any TOTP app.') }}</li>
                        <li>{{ __('Enter a 6-digit code from your app to confirm enrollment.') }}</li>
                        <li>{{ __('Save the recovery codes — they\'re your backup if you lose your phone.') }}</li>
                    </ol>
                    <form method="POST" action="{{ route('admin.2fa.enable') }}" style="margin-top:16px;">
                        @csrf
                        <button type="submit" class="tfa-btn tfa-btn--primary">
                            <i class="fas fa-key"></i> {{ __('Begin enrollment') }}
                        </button>
                    </form>
                </div>

            {{-- ============================================================ PENDING (UNCONFIRMED) ===== --}}
            @elseif ($hasPending)
                <div class="tfa-card">
                    <h4>{{ __('Step 1 — Scan this QR with your authenticator app') }}</h4>
                    <div class="tfa-qr-row">
                        <div class="tfa-qr-frame">
                            {!! $qrSvg !!}
                        </div>
                        <div class="tfa-qr-col">
                            <p class="tfa-secret-label">{{ __('Or enter this secret manually:') }}</p>
                            <code class="tfa-code">{{ $secret }}</code>

                            <h5>{{ __('Step 2 — Enter the 6-digit code from your app') }}</h5>
                            <form method="POST" action="{{ route('admin.2fa.confirm') }}">
                                @csrf
                                <div class="tfa-code-form">
                                    <label for="tfa-code-input" class="sr-only">{{ __('6-digit code') }}</label>
                                    <input type="text" id="tfa-code-input" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                                           autofocus autocomplete="off" placeholder="123456"
                                           class="tfa-code-input">
                                    <button type="submit" class="tfa-btn tfa-btn--confirm">
                                        {{ __('Confirm') }}
                                    </button>
                                </div>
                            </form>

                            <details class="tfa-cancel-details">
                                <summary>{{ __('Cancel enrollment') }}</summary>
                                <form method="POST" action="{{ route('admin.2fa.disable') }}" style="margin-top:8px;">
                                    @csrf
                                    <button type="submit" class="tfa-btn tfa-btn--ghost-danger">
                                        {{ __('Cancel and remove pending enrollment') }}
                                    </button>
                                </form>
                            </details>
                        </div>
                    </div>
                </div>

                @if (!empty($recoveryCodes))
                    <div class="tfa-warning">
                        <h4>
                            <i class="fas fa-triangle-exclamation"></i> {{ __('Save these recovery codes') }}
                        </h4>
                        <p>{{ __('Each code can be used once if you lose access to your authenticator app. Store them somewhere safe — they will not be shown in full again.') }}</p>
                        <div class="tfa-recovery-grid">
                            @foreach ($recoveryCodes as $rc)
                                <code class="tfa-recovery-code">{{ $rc }}</code>
                            @endforeach
                        </div>
                    </div>
                @endif

            {{-- ============================================================ ENROLLED ===== --}}
            @else
                <div class="tfa-card">
                    <h4 class="success">
                        <i class="fas fa-check-circle"></i> {{ __('2FA is enabled on your account') }}
                    </h4>
                    <p>
                        {{ __('You\'ll be asked for a code at every login. Confirmed:') }}
                        <strong>{{ $admin->two_factor_confirmed_at?->format('M j, Y') }}</strong>
                    </p>
                    <div class="tfa-btn-row">
                        <form method="POST" action="{{ route('admin.2fa.regenerate') }}"
                              onsubmit="return confirm('{{ __('Generate fresh recovery codes? Old ones will stop working.') }}');">
                            @csrf
                            <button type="submit" class="tfa-btn tfa-btn--outline">
                                <i class="fas fa-rotate"></i> {{ __('Regenerate recovery codes') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.2fa.disable') }}"
                              onsubmit="return confirm('{{ __('Are you sure? This removes the second-factor protection from your account.') }}');">
                            @csrf
                            <button type="submit" class="tfa-btn tfa-btn--danger">
                                <i class="fas fa-minus-circle"></i> {{ __('Disable 2FA') }}
                            </button>
                        </form>
                    </div>
                </div>

                <div class="tfa-soft-card">
                    <h5>{{ __('Your unused recovery codes') }} ({{ count($recoveryCodes) }})</h5>
                    @if (count($recoveryCodes))
                        <div class="tfa-recovery-grid tfa-recovery-grid--compact">
                            @foreach ($recoveryCodes as $rc)
                                <code class="tfa-recovery-code tfa-recovery-code--used-up">{{ $rc }}</code>
                            @endforeach
                        </div>
                    @else
                        <p class="tfa-empty-recovery">
                            {{ __('All recovery codes have been used. Regenerate immediately!') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
