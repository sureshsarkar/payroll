@extends('frontend.layouts.master')

@section('contents')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap" style="gap:12px;">
                <div>
                    <h2 style="margin:0; color:#1c1a4a;">
                        <i class="fas fa-shield-alt" style="color:#5751e1; margin-right:8px;"></i>
                        {{ __('Two-Factor Authentication') }}
                    </h2>
                    <p style="color:#6b7280; margin:6px 0 0; font-size:14px;">
                        {{ __('Add a second verification step at sign-in.') }}
                    </p>
                </div>
                @if ($isEnabled)
                    <span style="padding:6px 14px; background:#dcfce7; color:#166534; border-radius:6px; font-size:13px; font-weight:600;">
                        <i class="fas fa-check-circle"></i> {{ __('Active') }}
                    </span>
                @else
                    <span style="padding:6px 14px; background:#fee2e2; color:#991b1b; border-radius:6px; font-size:13px; font-weight:600;">
                        <i class="fas fa-times-circle"></i> {{ __('Not Active') }}
                    </span>
                @endif
            </div>

            @if (session('messege'))
                <div style="padding:12px 16px; background:#dcfce7; color:#166534; border-radius:8px; margin-bottom:16px; font-size:13px;">
                    {{ session('messege') }}
                </div>
            @endif
            @if ($errors->any())
                <div style="padding:12px 16px; background:#fee2e2; color:#991b1b; border-radius:8px; margin-bottom:16px; font-size:13px;">
                    @foreach ($errors->all() as $err) {{ $err }}<br> @endforeach
                </div>
            @endif

            @if (!$isEnabled && !$hasPending)
                <div style="background:#fff; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.04); border:1px solid #e5e7eb;">
                    <h4 style="margin:0 0 12px; color:#1c1a4a;">{{ __('How it works') }}</h4>
                    <ol style="color:#374151; font-size:14px; line-height:1.8; padding-left:20px;">
                        <li>{{ __('Click "Begin enrollment" below.') }}</li>
                        <li>{{ __('Scan the QR code with Google Authenticator, Authy, 1Password, or any TOTP app.') }}</li>
                        <li>{{ __('Enter a 6-digit code from your app to confirm enrollment.') }}</li>
                        <li>{{ __('Save the recovery codes — they\'re your backup if you lose your phone.') }}</li>
                    </ol>
                    <form method="POST" action="{{ route('web.2fa.enable') }}" style="margin-top:16px;">
                        @csrf
                        <button type="submit" style="padding:10px 24px; background:#5751e1; color:#fff; border:none; border-radius:8px; font-weight:600; font-size:14px; cursor:pointer;">
                            <i class="fas fa-key"></i> {{ __('Begin enrollment') }}
                        </button>
                    </form>
                </div>

            @elseif ($hasPending)
                <div style="background:#fff; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.04); border:1px solid #e5e7eb;">
                    <h4 style="margin:0 0 16px; color:#1c1a4a;">{{ __('Step 1 — Scan this QR with your authenticator app') }}</h4>
                    <div style="display:flex; gap:24px; flex-wrap:wrap; align-items:flex-start;">
                        <div style="background:#fafbfc; padding:16px; border-radius:12px; border:1px solid #e5e7eb;">
                            {!! $qrSvg !!}
                        </div>
                        <div style="flex:1; min-width:240px;">
                            <p style="font-size:13px; color:#6b7280; margin:0 0 8px;">{{ __('Or enter this secret manually:') }}</p>
                            <code style="display:block; padding:10px 14px; background:#f3f4f6; border-radius:6px; font-family:'SF Mono', Consolas, monospace; font-size:13px; color:#1f2937; word-break:break-all; margin-bottom:14px;">{{ $secret }}</code>

                            <h5 style="margin:24px 0 8px; color:#1c1a4a; font-size:15px;">{{ __('Step 2 — Enter the 6-digit code') }}</h5>
                            <form method="POST" action="{{ route('web.2fa.confirm') }}">
                                @csrf
                                <div style="display:flex; gap:8px;">
                                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                                           autofocus autocomplete="off" placeholder="123456"
                                           style="flex:1; padding:12px 14px; font-size:18px; letter-spacing:6px; text-align:center; border:2px solid #e5e7eb; border-radius:8px; font-family:monospace;">
                                    <button type="submit" style="padding:12px 24px; background:#5751e1; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
                                        {{ __('Confirm') }}
                                    </button>
                                </div>
                            </form>

                            <details style="margin-top:24px; font-size:12px; color:#6b7280;">
                                <summary style="cursor:pointer;">{{ __('Cancel enrollment') }}</summary>
                                <form method="POST" action="{{ route('web.2fa.disable') }}" style="margin-top:8px;">
                                    @csrf
                                    <button type="submit" style="padding:6px 14px; background:#fff; color:#dc2626; border:1px solid #dc2626; border-radius:6px; font-size:12px; cursor:pointer;">
                                        {{ __('Cancel and remove pending enrollment') }}
                                    </button>
                                </form>
                            </details>
                        </div>
                    </div>
                </div>

                @if (!empty($recoveryCodes))
                    <div style="background:#fffbeb; border:1px solid #fbbf24; border-radius:12px; padding:20px; margin-top:20px;">
                        <h4 style="margin:0 0 8px; color:#92400e;">
                            <i class="fas fa-exclamation-triangle"></i> {{ __('Save these recovery codes') }}
                        </h4>
                        <p style="margin:0 0 14px; color:#92400e; font-size:13px;">
                            {{ __('Each code can be used once if you lose access to your authenticator app.') }}
                        </p>
                        <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:8px;">
                            @foreach ($recoveryCodes as $rc)
                                <code style="background:#fff; padding:10px; border-radius:6px; font-family:monospace; font-size:13px; color:#1c1a4a; text-align:center; letter-spacing:1px;">{{ $rc }}</code>
                            @endforeach
                        </div>
                    </div>
                @endif

            @else
                <div style="background:#fff; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.04); border:1px solid #e5e7eb;">
                    <h4 style="margin:0 0 12px; color:#166534;">
                        <i class="fas fa-check-circle"></i> {{ __('2FA is enabled on your account') }}
                    </h4>
                    <p style="color:#6b7280; font-size:14px; margin-bottom:18px;">
                        {{ __('You\'ll be asked for a code at every login. Confirmed:') }}
                        <strong>{{ $user->two_factor_confirmed_at?->format('M j, Y') }}</strong>
                    </p>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <form method="POST" action="{{ route('web.2fa.regenerate') }}"
                              onsubmit="return confirm('{{ __('Generate fresh recovery codes? Old ones will stop working.') }}');">
                            @csrf
                            <button type="submit" style="padding:10px 18px; background:#fff; color:#5751e1; border:1px solid #5751e1; border-radius:8px; font-weight:500; font-size:13px; cursor:pointer;">
                                <i class="fas fa-sync-alt"></i> {{ __('Regenerate recovery codes') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('web.2fa.disable') }}"
                              onsubmit="return confirm('{{ __('Are you sure?') }}');">
                            @csrf
                            <button type="submit" style="padding:10px 18px; background:#fff; color:#dc2626; border:1px solid #dc2626; border-radius:8px; font-weight:500; font-size:13px; cursor:pointer;">
                                <i class="fas fa-minus-circle"></i> {{ __('Disable 2FA') }}
                            </button>
                        </form>
                    </div>
                </div>

                <div style="background:#fafbfc; border-radius:12px; padding:20px; margin-top:20px; border:1px solid #e5e7eb;">
                    <h5 style="margin:0 0 8px; color:#1c1a4a; font-size:14px;">{{ __('Your unused recovery codes') }} ({{ count($recoveryCodes) }})</h5>
                    @if (count($recoveryCodes))
                        <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:6px;">
                            @foreach ($recoveryCodes as $rc)
                                <code style="background:#fff; padding:8px; border-radius:6px; font-family:monospace; font-size:12px; color:#6b7280; text-align:center; border:1px solid #e5e7eb;">{{ $rc }}</code>
                            @endforeach
                        </div>
                    @else
                        <p style="color:#dc2626; font-size:13px; margin:0;">
                            {{ __('All recovery codes have been used. Regenerate immediately!') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
