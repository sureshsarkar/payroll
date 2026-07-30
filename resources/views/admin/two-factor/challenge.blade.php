@extends('admin.auth.app')

@section('title')
    <title>{{ __('Two-Factor Verification') }}</title>
@endsection

@push('css')
{{-- Audit fix M10 follow-on (2026-05-12) — pre-fix this view was 60 lines
     of inline `style=` attributes. Moved to named classes for parity with
     the setup view. --}}
<style>
    .tfac-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; background:#f3f4f6; }
    .tfac-card { max-width:440px; width:100%; background:#fff; border-radius:14px; box-shadow:0 10px 40px rgba(0,0,0,0.08); padding:40px; }
    .tfac-head { text-align:center; margin-bottom:24px; }
    .tfac-head .ic { width:60px; height:60px; margin:0 auto 12px; background:#ede9fe; border-radius:50%; display:flex; align-items:center; justify-content:center; }
    .tfac-head .ic i { color:#5751e1; font-size:24px; }
    .tfac-head h2 { margin:0; color:#1c1a4a; font-size:22px; }
    .tfac-head p { color:#6b7280; font-size:14px; margin:8px 0 0; }
    .tfac-err { padding:10px 14px; background:#fee2e2; color:#991b1b; border-radius:8px; margin-bottom:16px; font-size:13px; }
    .tfac-code { width:100%; padding:14px; font-size:24px; letter-spacing:12px; text-align:center; border:2px solid #e5e7eb; border-radius:10px; font-family:monospace; margin-bottom:14px; }
    .tfac-recovery { width:100%; padding:12px; font-size:14px; text-align:center; border:2px solid #e5e7eb; border-radius:10px; font-family:monospace; margin-bottom:10px; }
    .tfac-btn { width:100%; padding:12px; background:#5751e1; color:#fff; border:none; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; }
    .tfac-btn--outline { padding:10px; background:#fff; color:#5751e1; border:1px solid #5751e1; }
    .tfac-divider { margin-top:24px; padding-top:20px; border-top:1px solid #e5e7eb; }
    .tfac-toggle { cursor:pointer; font-size:13px; color:#5751e1; }
    .tfac-signout-wrap { text-align:center; margin-top:28px; padding-top:20px; border-top:1px solid #e5e7eb; }
    .tfac-signout { background:none; border:none; color:#9ca3af; font-size:12px; cursor:pointer; text-decoration:underline; }
    .tfac-note { font-size:11px; color:#9ca3af; margin:10px 0 0; }
    .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0; }
</style>
@endpush

@section('content')
<div class="tfac-wrap">
    <div class="tfac-card">
        <div class="tfac-head">
            <div class="ic"><i class="fas fa-shield-alt" aria-hidden="true"></i></div>
            <h2>{{ __('Verify it\'s you') }}</h2>
            <p>{{ __('Enter the 6-digit code from your authenticator app.') }}</p>
        </div>

        @if ($errors->any())
            <div class="tfac-err" role="alert">
                @foreach ($errors->all() as $err) {{ $err }} @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.2fa.challenge.verify') }}">
            @csrf
            {{-- Audit fix M7 (2026-05-12) — sr-only label so screen readers
                 announce the field; pre-fix it was an unlabeled box with
                 just a placeholder. --}}
            <label for="tfac-code" class="sr-only">{{ __('6-digit verification code') }}</label>
            <input id="tfac-code" type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                   autofocus autocomplete="one-time-code" required
                   placeholder="000000"
                   class="tfac-code">
            <button type="submit" class="tfac-btn">
                {{ __('Verify') }} <i class="fas fa-arrow-right" aria-hidden="true" style="margin-left:6px;"></i>
            </button>
        </form>

        <details class="tfac-divider">
            <summary class="tfac-toggle">
                <i class="fas fa-key" aria-hidden="true"></i> {{ __('Use a recovery code instead') }}
            </summary>
            <form method="POST" action="{{ route('admin.2fa.challenge.recovery') }}" style="margin-top:14px;">
                @csrf
                <label for="tfac-recovery" class="sr-only">{{ __('Recovery code') }}</label>
                <input id="tfac-recovery" type="text" name="recovery_code" autocomplete="off" required
                       placeholder="abc12-def34"
                       class="tfac-recovery">
                <button type="submit" class="tfac-btn tfac-btn--outline">
                    {{ __('Use recovery code') }}
                </button>
                <p class="tfac-note">{{ __('Each code can only be used once.') }}</p>
            </form>
        </details>

        <div class="tfac-signout-wrap">
            <form method="POST" action="{{ route('admin.logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="tfac-signout">
                    {{ __('Sign out') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
