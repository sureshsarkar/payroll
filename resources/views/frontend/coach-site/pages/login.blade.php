{{-- Coach-scoped login. Posts to /coach/{slug}/login so the redirect
     after login is coach.student.dashboard (never the platform's
     /student/dashboard). Form fields match what the platform login
     accepts so password reset / forgot links keep working. --}}
@extends('frontend.coach-site.layouts.master')

@section('content')
<section class="cs-pad">
    <div class="cs-container" style="max-width:460px;">
        <div style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:36px 32px;box-shadow:0 4px 20px rgba(15,23,42,0.04);">
            <h1 style="margin:0 0 6px;font-size:24px;font-weight:700;color:#111827;">{{ __('Log in') }}</h1>
            <p style="color:#6B7280;font-size:14px;margin:0 0 24px;">
                {{ __('Continue with') }} <strong>{{ $brand?->name ?? config('app.name') }}</strong>
            </p>

            @if(session()->has('messege'))
                <div style="background:#FEF3C7;border-left:4px solid #F59E0B;padding:10px 14px;border-radius:6px;margin-bottom:18px;font-size:13px;">
                    {{ session('messege') }}
                </div>
            @endif

            @if($errors->any())
                <div style="background:#FEE2E2;border-left:4px solid #DC2626;padding:10px 14px;border-radius:6px;margin-bottom:18px;font-size:13px;color:#991B1B;">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('coach.login.submit', ['coachSlug' => $coachSlug]) }}">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">{{ __('Email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">{{ __('Password') }}</label>
                    <input type="password" name="password" required
                           style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;">
                </div>
                <div style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;">
                    <label style="font-size:13px;color:#6B7280;display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" name="remember" value="1"> {{ __('Remember me') }}
                    </label>
                    <a href="{{ route('password.request') }}" style="font-size:13px;color:var(--brand-primary);text-decoration:none;">
                        {{ __('Forgot password?') }}
                    </a>
                </div>
                <button type="submit" class="cs-btn cs-btn--primary" style="width:100%;justify-content:center;">
                    {{ __('Log in') }}
                </button>
            </form>

            <div style="text-align:center;margin-top:20px;padding-top:20px;border-top:1px solid #F3F4F6;font-size:13px;color:#6B7280;">
                {{ __("Don't have an account?") }}
                <a href="{{ route('coach.register', ['coachSlug' => $coachSlug]) }}" style="color:var(--brand-primary);text-decoration:none;font-weight:600;">
                    {{ __('Register here') }}
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
