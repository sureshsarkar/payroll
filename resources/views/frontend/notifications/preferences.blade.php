@php
    $u = Auth::guard('web')->user();
    $isCoach = $u && $u->role === 'instructor';
    $dashLayout = $isCoach
        ? 'frontend.instructor-dashboard.layouts.master'
        : 'frontend.student-dashboard.layouts.master';
@endphp
@extends($dashLayout)

@section('dashboard-contents')
<div class="py-3">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap" style="gap:12px;">
        <div>
            <h2 style="margin:0; color:#1c1a4a;">{{ __('Notification Preferences') }}</h2>
            <p style="color:#6b7280; margin:6px 0 0; font-size:14px;">
                {{ __('Choose how you want to be notified for each type of event.') }}
            </p>
        </div>
        <a href="{{ route('notifications.index') }}" style="color:#10b981; text-decoration:none; font-size:13px;">
            <i class="fas fa-arrow-left"></i> {{ __('Back to notifications') }}
        </a>
    </div>

    @if (session('messege'))
        <div style="padding:12px 16px; background:#dcfce7; color:#166534; border-radius:8px; margin-bottom:16px; font-size:13px;">
            <i class="fas fa-check-circle"></i> {{ session('messege') }}
        </div>
    @endif

    <form method="POST" action="{{ route('notifications.preferences.update') }}">
        @csrf

        <div style="background:#fff; border-radius:12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden;">
            <div style="display:grid; grid-template-columns: 1fr 90px 90px 90px; gap:0; padding: 14px 20px; border-bottom: 2px solid #f3f4f6; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#9ca3af; font-weight:600;">
                <div>{{ __('Event') }}</div>
                <div style="text-align:center;" title="{{ __('In-app bell') }}">
                    <i class="fas fa-bell" style="margin-right:4px;"></i> {{ __('In-app') }}
                </div>
                <div style="text-align:center;" title="{{ __('Email') }}">
                    <i class="fas fa-envelope" style="margin-right:4px;"></i> {{ __('Email') }}
                </div>
                <div style="text-align:center;" title="{{ __('Push (Pusher)') }}">
                    <i class="fas fa-bolt" style="margin-right:4px;"></i> {{ __('Push') }}
                </div>
            </div>

            @foreach ($events as $key => $cfg)
                <div style="display:grid; grid-template-columns: 1fr 90px 90px 90px; gap:0; padding:18px 20px; border-bottom: 1px solid #f3f4f6; align-items:center;">
                    <div>
                        <div style="font-size:14px; font-weight:600; color:#1c1a4a; margin-bottom:2px;">
                            {{ __($cfg['label']) }}
                        </div>
                        <div style="font-size:12px; color:#6b7280; line-height:1.5;">
                            {{ __($cfg['description']) }}
                        </div>
                    </div>
                    @foreach ($channels as $ch)
                        @php
                            $checked = !isset($prefs[$key][$ch]) ? true : (bool) $prefs[$key][$ch];
                        @endphp
                        <div style="text-align:center;">
                            <label class="mbs-toggle">
                                <input type="checkbox" name="prefs[{{ $key }}][{{ $ch }}]"
                                       value="1" {{ $checked ? 'checked' : '' }}>
                                <span class="mbs-toggle__slider"></span>
                            </label>
                        </div>
                    @endforeach
                </div>
            @endforeach

            @if (empty($events))
                <div style="padding:32px; text-align:center; color:#9ca3af;">
                    {{ __('No notification preferences available for your account type.') }}
                </div>
            @endif
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" style="padding:10px 24px; background:#10b981; color:#fff; border:none; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">
                <i class="fas fa-save"></i> {{ __('Save Preferences') }}
            </button>
        </div>
    </form>

    <div style="margin-top:24px; padding:14px 16px; background:#f0f7ff; color:#1e3a8a; border-radius:8px; font-size:12px; line-height:1.6;">
        <i class="fas fa-info-circle"></i>
        <strong>{{ __('Tip:') }}</strong>
        {{ __('Push notifications require Pusher to be configured by the administrator. If push is off site-wide, toggling it on here will silently fall back to in-app + email.') }}
    </div>
</div>

<style>
    .mbs-toggle {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 22px;
        cursor: pointer;
    }
    .mbs-toggle input { opacity: 0; width: 0; height: 0; }
    .mbs-toggle__slider {
        position: absolute; inset: 0;
        background: #d1d5db;
        border-radius: 22px;
        transition: background 0.2s;
    }
    .mbs-toggle__slider::before {
        content: '';
        position: absolute;
        left: 3px; top: 3px;
        width: 16px; height: 16px;
        background: #fff;
        border-radius: 50%;
        transition: transform 0.2s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .mbs-toggle input:checked + .mbs-toggle__slider { background: #10b981; }
    .mbs-toggle input:checked + .mbs-toggle__slider::before { transform: translateX(18px); }
    .mbs-toggle input:focus-visible + .mbs-toggle__slider { box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25); }
</style>
@endsection
