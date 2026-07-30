{{-- Coach-scoped registration. Posts to /coach/{slug}/register so the
     new student is auto-linked to this coach via CoachStudentLink and
     redirected back to /coach/{slug}/{checkout or dashboard}. --}}
@extends('frontend.coach-site.layouts.master')

@section('content')
<section class="cs-pad">
    <div class="cs-container" style="max-width:460px;">
        <div style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:36px 32px;box-shadow:0 4px 20px rgba(15,23,42,0.04);">
            <h1 style="margin:0 0 6px;font-size:24px;font-weight:700;color:#111827;">{{ __('Create your account') }}</h1>
            <p style="color:#6B7280;font-size:14px;margin:0 0 24px;">
                {{ __('Join') }} <strong>{{ $brand?->name ?? config('app.name') }}</strong> {{ __('to access courses + live sessions.') }}
            </p>

            @if($errors->any())
                <div style="background:#FEE2E2;border-left:4px solid #DC2626;padding:10px 14px;border-radius:6px;margin-bottom:18px;font-size:13px;color:#991B1B;">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('coach.register.submit', ['coachSlug' => $coachSlug]) }}">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">{{ __('Name') }}</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">{{ __('Email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">{{ __('Password') }} <span style="color:#6B7280;font-weight:400;font-size:12px;">({{ __('min 8 chars') }})</span></label>
                    <input type="password" name="password" required minlength="8"
                           style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">{{ __('Confirm password') }}</label>
                    <input type="password" name="password_confirmation" required minlength="8"
                           style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;">
                </div>
                <button type="submit" class="cs-btn cs-btn--primary" style="width:100%;justify-content:center;">
                    {{ __('Create account') }}
                </button>
            </form>

            <div style="text-align:center;margin-top:20px;padding-top:20px;border-top:1px solid #F3F4F6;font-size:13px;color:#6B7280;">
                {{ __('Already have an account?') }}
                <a href="{{ route('coach.login', ['coachSlug' => $coachSlug]) }}" style="color:var(--brand-primary);text-decoration:none;font-weight:600;">
                    {{ __('Log in here') }}
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
