{{-- Coach onboarding — theme picker gallery (standalone, no instructor sidebar) --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Pick a theme — Welcome to your coaching website') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('frontend/css/fontawesome-all.min.css') }}">
    <style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: 'Inter', system-ui, sans-serif;
    color: #0F172A;
    background: linear-gradient(180deg, #FAFBFF 0%, #F1F5F9 100%);
    min-height: 100vh;
    letter-spacing: -0.011em;
}
.tp-shell { max-width: 1280px; margin: 0 auto; padding: 40px 24px 80px; }

.tp-hero { text-align: center; margin-bottom: 36px; }
.tp-hero__eyebrow {
    display: inline-block;
    font-size: 11.5px; font-weight: 700; letter-spacing: 1.4px;
    text-transform: uppercase;
    padding: 6px 14px;
    background: linear-gradient(135deg, #ecfdf5, #FAE8FF);
    color: #4F46E5;
    border-radius: 999px;
    margin-bottom: 14px;
}
.tp-hero h1 {
    font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
    font-size: 42px; font-weight: 800;
    margin: 0 0 12px; letter-spacing: -0.025em; line-height: 1.1;
    color: #0F172A;
}
.tp-hero p {
    font-size: 17px; color: #64748B;
    max-width: 580px; margin: 0 auto;
    line-height: 1.55;
}

.tp-toolbar {
    display: flex; justify-content: space-between; align-items: center;
    gap: 14px; flex-wrap: wrap;
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 12px 16px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(15,23,42,0.04);
}
.tp-cats { display: flex; gap: 6px; flex-wrap: wrap; }
.tp-cat {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 14px;
    border-radius: 999px;
    background: #F1F5F9;
    color: #475569;
    font-size: 13px; font-weight: 600;
    text-decoration: none;
    border: 1.5px solid transparent;
    transition: all 0.15s;
}
.tp-cat:hover { background: #fff; border-color: #CBD5E1; color: #0F172A; }
.tp-cat--active { background: linear-gradient(135deg, #10b981, #059669); color: #fff; box-shadow: 0 4px 12px rgba(16, 185, 129,0.30); }
.tp-cat--active:hover { color: #fff; background: linear-gradient(135deg, #10b981, #059669); border-color: transparent; }
.tp-cat i { font-size: 11px; }

.tp-skip {
    color: #64748B; text-decoration: none; font-size: 13px; font-weight: 600;
    padding: 8px 14px; border-radius: 8px;
    transition: background 0.15s;
}
.tp-skip:hover { background: #F1F5F9; color: #0F172A; }

.tp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 22px;
}

.tp-card {
    background: #fff;
    border: 1.5px solid #E2E8F0;
    border-radius: 18px;
    overflow: hidden;
    transition: transform 0.2s cubic-bezier(0.4,0,0.2,1), box-shadow 0.2s, border-color 0.2s;
    display: flex; flex-direction: column;
}
.tp-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 36px rgba(15,23,42,0.10);
    border-color: #a7f3d0;
}
.tp-card__thumb {
    aspect-ratio: 16/10;
    position: relative;
    overflow: hidden;
    display: flex; align-items: center; justify-content: center;
}
.tp-card__thumb img { width: 100%; height: 100%; object-fit: cover; }
.tp-card__placeholder {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800; font-size: 26px;
    color: #fff;
    text-align: center; padding: 0 24px;
    letter-spacing: -0.02em;
}
.tp-card__premium {
    position: absolute; top: 10px; left: 10px;
    background: #F59E0B; color: #fff;
    font-size: 10.5px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.8px;
    padding: 4px 9px; border-radius: 999px;
}

.tp-card__body { padding: 18px 22px 20px; flex: 1; display: flex; flex-direction: column; }
.tp-card__title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 18px; font-weight: 700;
    margin: 0 0 6px; letter-spacing: -0.015em;
}
.tp-card__desc {
    font-size: 13.5px; color: #64748B;
    line-height: 1.55; margin: 0 0 14px; flex: 1;
}
.tp-card__cats { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px; }
.tp-card__cats span {
    font-size: 10.5px; padding: 3px 8px;
    background: #ecfdf5; color: #4F46E5;
    border-radius: 5px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.tp-card__actions { display: flex; gap: 8px; }
.tp-card__preview {
    flex: 0 0 auto; padding: 11px 14px;
    background: #F1F5F9; color: #475569;
    border-radius: 10px; font-weight: 600; font-size: 13px;
    text-decoration: none;
    transition: all 0.15s;
    display: inline-flex; align-items: center; gap: 6px;
}
.tp-card__preview:hover { background: #E2E8F0; color: #0F172A; }
.tp-card__use {
    flex: 1;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff !important;
    border: 0; padding: 11px 14px;
    border-radius: 10px;
    font-weight: 600; font-size: 13.5px;
    cursor: pointer;
    box-shadow: 0 6px 14px rgba(16, 185, 129,0.30);
    transition: transform 0.15s, box-shadow 0.15s, filter 0.15s;
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
.tp-card__use:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(16, 185, 129,0.40); filter: brightness(1.05); }

.tp-empty {
    text-align: center; padding: 60px 24px;
    color: #94A3B8; font-size: 14px;
    background: #fff; border: 1.5px dashed #CBD5E1;
    border-radius: 18px;
}
    </style>
<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] body { background:#17233a; color:#e2e8f0; }
html[data-theme="dark"] .tp-hero h1 { color:#e2e8f0; }
html[data-theme="dark"] .tp-hero p { color:#94a3b8; }
html[data-theme="dark"] .tp-toolbar { background:#1e293b; border-color:#2a3a55; box-shadow:none; }
html[data-theme="dark"] .tp-cat { background:#22304a; color:#94a3b8; }
html[data-theme="dark"] .tp-cat:hover { background:#1e293b; border-color:#2a3a55; color:#e2e8f0; }
html[data-theme="dark"] .tp-skip { color:#94a3b8; }
html[data-theme="dark"] .tp-skip:hover { background:#22304a; color:#e2e8f0; }
html[data-theme="dark"] .tp-card { background:#1e293b; border-color:#2a3a55; }
html[data-theme="dark"] .tp-card:hover { box-shadow:none; }
html[data-theme="dark"] .tp-card__desc { color:#94a3b8; }
html[data-theme="dark"] .tp-card__preview { background:#22304a; color:#94a3b8; }
html[data-theme="dark"] .tp-card__preview:hover { background:#22304a; color:#e2e8f0; }
html[data-theme="dark"] .tp-empty { background:#1e293b; border-color:#2a3a55; color:#94a3b8; }
</style>
</head>
<body>
<div class="tp-shell">

    <div class="tp-hero">
        <span class="tp-hero__eyebrow"><i class="fa-solid fa-palette"></i> Welcome to your coaching website</span>
        <h1>{{ __('Pick a theme to get started') }}</h1>
        <p>{{ __('Choose a design that matches your coaching style — your branding gets applied automatically. You can customize everything afterwards.') }}</p>
    </div>

    <div class="tp-toolbar">
        <div class="tp-cats">
            <a href="{{ route('onboarding.theme-picker') }}" class="tp-cat {{ empty($currentCategory) ? 'tp-cat--active' : '' }}">
                <i class="fa-solid fa-circle-nodes"></i> {{ __('All themes') }}
            </a>
            @foreach($categories as $cat)
                @if($cat->themes_count > 0)
                    <a href="{{ route('onboarding.theme-picker', ['category' => $cat->slug]) }}"
                       class="tp-cat {{ $currentCategory === $cat->slug ? 'tp-cat--active' : '' }}">
                        <i class="{{ $cat->icon ?? 'fa-solid fa-tag' }}"></i> {{ $cat->name }}
                    </a>
                @endif
            @endforeach
        </div>
        <a href="{{ route('onboarding.skip') }}" class="tp-skip">
            {{ __('Skip — build from scratch') }} <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    @if($themes->isEmpty())
        <div class="tp-empty">
            <i class="fa-solid fa-palette" style="font-size:48px;opacity:0.4;display:block;margin-bottom:14px;"></i>
            {{ __('No themes available right now. You can still build your site from scratch.') }}
        </div>
    @else
        <div class="tp-grid">
            @foreach($themes as $theme)
                @php
                    $primary = $theme->default_colors['primary'] ?? '#10b981';
                    $accent  = $theme->default_colors['accent']  ?? '#059669';
                @endphp
                <div class="tp-card">
                    <div class="tp-card__thumb" style="background:linear-gradient(135deg,{{ $primary }} 0%,{{ $accent }} 100%);">
                        @if($theme->thumbnail_url)
                            <img src="{{ $theme->thumbnail_url }}" alt="{{ $theme->name }}">
                        @else
                            <div class="tp-card__placeholder">{{ $theme->name }}</div>
                        @endif
                        @if($theme->is_premium)
                            <span class="tp-card__premium"><i class="fa-solid fa-crown"></i> Premium</span>
                        @endif
                    </div>
                    <div class="tp-card__body">
                        <h3 class="tp-card__title">{{ $theme->name }}</h3>
                        @if($theme->description)
                            <p class="tp-card__desc">{{ \Illuminate\Support\Str::limit($theme->description, 110) }}</p>
                        @endif
                        @if($theme->categories->isNotEmpty())
                            <div class="tp-card__cats">
                                @foreach($theme->categories as $cat)
                                    <span>{{ $cat->name }}</span>
                                @endforeach
                            </div>
                        @endif
                        <div class="tp-card__actions">
                            <a href="{{ route('admin.themes.preview', $theme->id) }}" target="_blank" class="tp-card__preview" title="{{ __('Preview') }}">
                                <i class="fa-solid fa-eye"></i> {{ __('Preview') }}
                            </a>
                            <form method="POST" action="{{ route('onboarding.apply-theme') }}" style="flex:1;">
                                @csrf
                                <input type="hidden" name="theme_id" value="{{ $theme->id }}">
                                <button type="submit" class="tp-card__use">
                                    {{ __('Use this theme') }} <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
</body>
</html>
