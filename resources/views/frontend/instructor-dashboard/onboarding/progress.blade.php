{{-- Onboarding progress page — shown while ApplyThemeJob runs --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Setting up your website…') }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('frontend/css/fontawesome-all.min.css') }}">
    <style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: 'Inter', system-ui, sans-serif;
    color: #0F172A;
    background: linear-gradient(135deg, #ecfdf5 0%, #FAE8FF 100%);
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    letter-spacing: -0.011em;
}
.tp-card {
    background: #fff;
    border-radius: 22px;
    box-shadow: 0 30px 70px rgba(15,23,42,0.15);
    padding: 48px 44px;
    text-align: center;
    max-width: 480px;
    width: 90%;
}
.tp-spinner {
    width: 64px; height: 64px;
    border: 5px solid #ecfdf5;
    border-top-color: #10b981;
    border-radius: 50%;
    margin: 0 auto 22px;
    animation: tp-spin 0.9s linear infinite;
}
@keyframes tp-spin { to { transform: rotate(360deg); } }
.tp-card h1 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 26px; font-weight: 800;
    margin: 0 0 10px; letter-spacing: -0.02em;
}
.tp-card p { font-size: 14.5px; color: #64748B; line-height: 1.6; margin: 0 0 24px; }
.tp-success-ic {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, #10B981, #059669);
    border-radius: 50%;
    margin: 0 auto 22px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 28px;
    box-shadow: 0 12px 28px rgba(16,185,129,0.30);
}
.tp-btn {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff !important; text-decoration: none;
    padding: 13px 30px; border-radius: 11px;
    font-weight: 600; font-size: 14.5px;
    display: inline-flex; align-items: center; gap: 8px;
    box-shadow: 0 8px 20px rgba(16, 185, 129,0.30);
    transition: transform 0.15s, filter 0.15s;
}
.tp-btn:hover { transform: translateY(-2px); filter: brightness(1.05); }
.tp-progress {
    height: 6px; background: #F1F5F9; border-radius: 999px;
    overflow: hidden; margin-bottom: 24px;
}
.tp-progress__fill {
    height: 100%; background: linear-gradient(90deg, #10b981, #059669);
    width: 0%;
    animation: tp-fill 4s ease-in-out infinite;
    border-radius: 999px;
}
@keyframes tp-fill {
    0%   { width: 8%;  }
    50%  { width: 70%; }
    100% { width: 95%; }
}
.tp-steps {
    list-style: none; padding: 0; margin: 0;
    text-align: left;
    background: #F8FAFC;
    border-radius: 12px;
    padding: 18px 22px;
}
.tp-steps li {
    font-size: 13.5px; color: #64748B;
    padding: 5px 0;
    display: flex; align-items: center; gap: 10px;
}
.tp-steps li.done { color: #047857; }
.tp-steps li i { font-size: 13px; }
    </style>
<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] body { color:#e2e8f0; }
html[data-theme="dark"] .tp-card { background:#1e293b; box-shadow:none; }
html[data-theme="dark"] .tp-card p { color:#94a3b8; }
html[data-theme="dark"] .tp-progress { background:#22304a; }
html[data-theme="dark"] .tp-steps { background:#17233a; }
html[data-theme="dark"] .tp-steps li { color:#94a3b8; }
</style>
</head>
<body>
<div class="tp-card" id="tpRoot">
    @if($done)
        <div class="tp-success-ic"><i class="fa-solid fa-check"></i></div>
        <h1>{{ __('Your website is ready!') }}</h1>
        <p>{{ __('We have set up your pages, applied your branding, and prepared everything for you.') }}</p>
        <a href="{{ route('instructor.web-page.index') }}" class="tp-btn">
            {{ __('Open your editor') }} <i class="fa-solid fa-arrow-right"></i>
        </a>
    @else
        <div class="tp-spinner"></div>
        <h1>{{ __('Setting up your website…') }}</h1>
        <p>{{ __('Hang tight — we are cloning the theme, applying your branding, and preparing your pages. This takes 10–20 seconds.') }}</p>
        <div class="tp-progress"><div class="tp-progress__fill"></div></div>
        <ul class="tp-steps">
            <li><i class="fa-solid fa-circle-check" style="color:#10B981;"></i> {{ __('Creating pages from theme') }}</li>
            <li><i class="fa-solid fa-circle-check" style="color:#10B981;"></i> {{ __('Cloning sections') }}</li>
            <li><i class="fa-solid fa-spinner fa-spin" style="color:#10b981;"></i> {{ __('Applying your brand identity') }}</li>
            <li><i class="fa-regular fa-circle" style="color:#CBD5E1;"></i> {{ __('Finalizing your site') }}</li>
        </ul>
    @endif
</div>

@unless($done)
<script>
(function poll() {
    setTimeout(async function check() {
        try {
            const res = await fetch("{{ route('onboarding.progress', ['theme' => $theme?->id]) }}", {
                headers: { 'Accept':'application/json' },
                credentials: 'same-origin',
            }).then(r => r.json());
            if (res && res.done) {
                window.location.href = "{{ route('onboarding.progress', ['theme' => $theme?->id]) }}";
                return;
            }
        } catch (e) {}
        setTimeout(check, 2500);
    }, 2500);
})();
</script>
@endunless
</body>
</html>
