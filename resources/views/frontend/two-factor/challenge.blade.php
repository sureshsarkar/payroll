@extends('frontend.layouts.master')

@section('contents')
<div style="min-height:80vh; display:flex; align-items:center; justify-content:center; background:#f3f4f6; padding:40px 20px;">
    <div style="max-width:440px; width:100%; background:#fff; border-radius:14px; box-shadow:0 10px 40px rgba(0,0,0,0.08); padding:40px;">
        <div style="text-align:center; margin-bottom:24px;">
            <div style="width:60px; height:60px; margin:0 auto 12px; background:#ede9fe; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-shield-alt" style="color:#5751e1; font-size:24px;"></i>
            </div>
            <h2 style="margin:0; color:#1c1a4a; font-size:22px;">{{ __('Verify it\'s you') }}</h2>
            <p style="color:#6b7280; font-size:14px; margin:8px 0 0;">{{ __('Enter the 6-digit code from your authenticator app.') }}</p>
        </div>

        @if ($errors->any())
            <div style="padding:10px 14px; background:#fee2e2; color:#991b1b; border-radius:8px; margin-bottom:16px; font-size:13px;">
                @foreach ($errors->all() as $err) {{ $err }} @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('web.2fa.challenge.verify') }}">
            @csrf
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                   autofocus autocomplete="one-time-code" placeholder="000000"
                   style="width:100%; padding:14px; font-size:24px; letter-spacing:12px; text-align:center; border:2px solid #e5e7eb; border-radius:10px; font-family:monospace; margin-bottom:14px;">
            <button type="submit" style="width:100%; padding:12px; background:#5751e1; color:#fff; border:none; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer;">
                {{ __('Verify') }} <i class="fas fa-arrow-right" style="margin-left:6px;"></i>
            </button>
        </form>

        <details style="margin-top:24px; padding-top:20px; border-top:1px solid #e5e7eb;">
            <summary style="cursor:pointer; font-size:13px; color:#5751e1;">
                <i class="fas fa-key"></i> {{ __('Use a recovery code instead') }}
            </summary>
            <form method="POST" action="{{ route('web.2fa.challenge.recovery') }}" style="margin-top:14px;">
                @csrf
                <input type="text" name="recovery_code" autocomplete="off" placeholder="abc12-def34"
                       style="width:100%; padding:12px; font-size:14px; text-align:center; border:2px solid #e5e7eb; border-radius:10px; font-family:monospace; margin-bottom:10px;">
                <button type="submit" style="width:100%; padding:10px; background:#fff; color:#5751e1; border:1px solid #5751e1; border-radius:10px; font-weight:500; font-size:13px; cursor:pointer;">
                    {{ __('Use recovery code') }}
                </button>
                <p style="font-size:11px; color:#9ca3af; margin:10px 0 0;">{{ __('Each code can only be used once.') }}</p>
            </form>
        </details>

        <div style="text-align:center; margin-top:28px; padding-top:20px; border-top:1px solid #e5e7eb;">
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button type="submit" style="background:none; border:none; color:#9ca3af; font-size:12px; cursor:pointer; text-decoration:underline;">
                    {{ __('Sign out') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
