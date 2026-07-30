@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<div class="corp-page csd-page">

    {{-- Hero header with gradient ────────────────────────────────── --}}
    <div class="csd-hero">
        <div class="csd-hero__bg"></div>
        <div class="csd-hero__inner">
            <div class="csd-hero__left">
                <span class="csd-hero__eyebrow">
                    <i class="fas fa-globe"></i>
                    {{ __('Coach Marketing Website') }}
                </span>
                <h1 class="csd-hero__title">{{ __('Build your branded website') }}</h1>
                <p class="csd-hero__sub">{{ __('Multi-page, mobile-ready, lead capture built-in. Your students see only your brand.') }}</p>
            </div>
            <div class="csd-hero__right" style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="{{ route('instructor.web-page.menu') }}" class="csd-btn csd-btn--white">
                    <i class="fas fa-bars"></i> {{ __('Menu') }}
                </a>
                <a href="{{ route('instructor.web-page.footer') }}" class="csd-btn csd-btn--white">
                    <i class="fas fa-shoe-prints"></i> {{ __('Global Footer') }}
                </a>
                <a href="{{ route('instructor.web-page.theme-picker') }}" class="csd-btn csd-btn--white">
                    <i class="fas fa-palette"></i> {{ __('Change Theme') }}
                </a>
                @if($site && $site->slug)
                    <a href="{{ url('/coach/' . $site->slug) }}" target="_blank" class="csd-btn csd-btn--white">
                        <i class="fas fa-external-link-alt"></i> {{ __('Preview live site') }}
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="csd-alert">
            <i class="fas fa-check-circle"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- PAGES LIST — primary action, shown first ──────────────────── --}}
    <section class="csd-card">
        <header class="csd-card__head">
            <div class="csd-card__head-left">
                <div class="csd-card__icon csd-card__icon--accent"><i class="fas fa-file-alt"></i></div>
                <div>
                    <h2 class="csd-card__title">{{ __('Pages') }} <span class="csd-card__count">{{ $pages->count() }}</span></h2>
                    <p class="csd-card__sub">{{ __('Click "Edit" on any page to add sections, change content, and publish.') }}</p>
                </div>
            </div>
            <button type="button" class="csd-btn csd-btn--primary" onclick="document.getElementById('newPageModal').showModal()">
                <i class="fas fa-plus"></i> {{ __('New page') }}
            </button>
        </header>
        <div class="csd-card__body">
            @if($pages->isEmpty())
                <div class="csd-empty">
                    <div class="csd-empty__ic"><i class="fas fa-file-alt"></i></div>
                    <h3>{{ __('No pages yet') }}</h3>
                    <p>{{ __('Start with a Home page, then add About, Services, and Contact.') }}</p>
                    <button type="button" class="csd-btn csd-btn--primary" onclick="document.getElementById('newPageModal').showModal()">
                        <i class="fas fa-plus"></i> {{ __('Create your first page') }}
                    </button>
                </div>
            @else
                <div class="csd-pages">
                    @foreach($pages as $p)
                        @php
                            $typeColors = [
                                'home' => 'brand', 'about' => 'info', 'services' => 'success',
                                'pricing' => 'warning', 'testimonials' => 'pink', 'contact' => 'cyan',
                                'blog_index' => 'purple', 'custom' => 'slate',
                            ];
                            $color = $typeColors[$p->page_type] ?? 'slate';
                        @endphp
                        <div class="csd-page-row">
                            <div class="csd-page-row__lead">
                                <div class="csd-page-row__icon csd-page-row__icon--{{ $color }}">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="csd-page-row__body">
                                    <div class="csd-page-row__title">
                                        <span class="csd-page-row__name">{{ $p->title }}</span>
                                        <span class="csd-tag csd-tag--{{ $color }}">{{ ucfirst($p->page_type) }}</span>
                                    </div>
                                    <div class="csd-page-row__meta">
                                        <span class="csd-meta-item"><i class="fas fa-link"></i> /{{ $p->slug === 'home' ? '' : $p->slug }}</span>
                                        <span class="csd-meta-item"><i class="fas fa-layer-group"></i> {{ $p->sections_count }} {{ __('sections') }}</span>
                                        @if($p->is_published)
                                            <span class="csd-pill csd-pill--ok"><span class="csd-pill__dot"></span> {{ __('Live') }}</span>
                                        @else
                                            <span class="csd-pill csd-pill--draft"><span class="csd-pill__dot"></span> {{ __('Draft') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="csd-page-row__actions">
                                <a href="{{ route('instructor.web-page.edit', $p->id) }}" class="csd-btn csd-btn--primary csd-btn--sm">
                                    <i class="fas fa-pen"></i> {{ __('Edit') }}
                                </a>
                                @if($p->page_type !== 'home')
                                    <form action="{{ route('instructor.web-page.destroy', $p->id) }}" method="POST" onsubmit="return confirm('{{ __('Delete this page?') }}')" style="display:inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="csd-btn csd-btn--icon csd-btn--icon-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- SITE SETTINGS — collapsible, shown second ─────────────────── --}}
    <details class="csd-card csd-card--collapsible" {{ ($pages->isEmpty() || empty($site->website_name)) ? 'open' : '' }}>
        <summary class="csd-card__head csd-card__head--summary">
            <div class="csd-card__head-left">
                <div class="csd-card__icon csd-card__icon--brand"><i class="fas fa-cog"></i></div>
                <div>
                    <h2 class="csd-card__title">{{ __('Site settings') }}</h2>
                    <p class="csd-card__sub">
                        @if($site && $site->slug)
                            {{ __('Website name') }}: <strong>{{ $site->website_name }}</strong> ·
                            {{ __('URL') }}: <strong>{{ $site->slug }}.{{ config('app.coach_domain') }}</strong>
                        @else
                            {{ __('Configure your website name and subdomain') }}
                        @endif
                    </p>
                </div>
            </div>
            <span class="csd-collapse-chev"><i class="fas fa-chevron-down"></i></span>
        </summary>
        <div class="csd-card__body">
            <form action="{{ route('instructor.web-page.site') }}" method="POST" class="csd-form">
                @csrf
                <div class="csd-form-grid">
                    <div class="csd-field">
                        <label class="csd-label">{{ __('Website name') }} <span class="csd-req">*</span></label>
                        <input type="text" name="website_name"
                               value="{{ old('website_name', $site->website_name ?? '') }}"
                               required maxlength="80" class="csd-input"
                               placeholder="My Coaching Studio">
                        @error('website_name')<small class="csd-err">{{ $message }}</small>@enderror
                    </div>
                    <div class="csd-field">
                        <label class="csd-label">{{ __('Subdomain') }} <span class="csd-req">*</span></label>
                        <div class="csd-input-group">
                            <input type="text" name="subdomain"
                                   value="{{ old('subdomain', $site ? str_replace('.' . config('app.coach_domain'), '', $site->subdomain ?? '') : '') }}"
                                   required pattern="[a-z0-9-]+" maxlength="50" class="csd-input"
                                   placeholder="my-site">
                            <span class="csd-input-group__suffix">.{{ config('app.coach_domain') }}</span>
                        </div>
                        @error('subdomain')<small class="csd-err">{{ $message }}</small>@enderror
                    </div>
                </div>
                <div class="csd-form-actions">
                    <button type="submit" class="csd-btn csd-btn--primary">
                        <i class="fas fa-save"></i> {{ __('Save settings') }}
                    </button>
                </div>
            </form>
        </div>
    </details>

</div>

{{-- New page modal ──────────────────────────────────────────────────────── --}}
<dialog id="newPageModal" class="csd-dialog">
    <form action="{{ route('instructor.web-page.pages.store') }}" method="POST" class="csd-dialog__form">
        @csrf
        <header class="csd-dialog__head">
            <h3>{{ __('Add new page') }}</h3>
            <button type="button" class="csd-btn csd-btn--icon" onclick="this.closest('dialog').close()"><i class="fas fa-times"></i></button>
        </header>
        <div class="csd-dialog__body">
            <div class="csd-field">
                <label class="csd-label">{{ __('Page type') }}</label>
                <select name="page_type" required class="csd-input">
                    <option value="about">{{ __('About') }}</option>
                    <option value="services">{{ __('Services') }}</option>
                    <option value="pricing">{{ __('Pricing') }}</option>
                    <option value="testimonials">{{ __('Testimonials') }}</option>
                    <option value="contact">{{ __('Contact') }}</option>
                    <option value="custom">{{ __('Custom page') }}</option>
                </select>
            </div>
            <div class="csd-field">
                <label class="csd-label">{{ __('Page title') }} <span class="csd-req">*</span></label>
                <input type="text" name="title" required maxlength="160" placeholder="e.g. About Me, Pricing, Retreats" class="csd-input">
            </div>
        </div>
        <footer class="csd-dialog__foot">
            <button type="button" class="csd-btn csd-btn--ghost" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
            <button type="submit" class="csd-btn csd-btn--primary"><i class="fas fa-plus"></i> {{ __('Create page') }}</button>
        </footer>
    </form>
</dialog>

<style>
/* ============================================================
 * Coach Site Dashboard — Premium design (csd-)
 * Higher specificity to defeat platform Bootstrap defaults.
 * ============================================================ */
.csd-page { display: flex; flex-direction: column; gap: 18px; }

/* Hero header */
.csd-hero {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    padding: 28px 32px;
    color: #fff !important;
    background: linear-gradient(135deg, #10b981 0%, #059669 50%, #EC4899 100%);
    box-shadow: 0 20px 50px rgba(16, 185, 129, 0.25);
}
.csd-hero__bg {
    position: absolute; inset: 0;
    background-image:
        radial-gradient(circle at 20% 50%, rgba(255,255,255,.18) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(255,255,255,.12) 0%, transparent 60%);
    pointer-events: none;
}
.csd-hero__inner { position: relative; display: flex; justify-content: space-between; align-items: center; gap: 24px; flex-wrap: wrap; }
.csd-hero__eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11.5px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1.4px;
    padding: 6px 12px;
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(8px);
    border-radius: 999px;
    margin-bottom: 10px;
    color: #fff;
}
.csd-hero__title {
    margin: 0 0 6px !important;
    font-size: 28px !important;
    font-weight: 800 !important;
    letter-spacing: -0.025em;
    line-height: 1.15 !important;
    color: #fff !important;
    font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
}
.csd-hero__sub { margin: 0 !important; opacity: 0.92; font-size: 14.5px; line-height: 1.55; max-width: 580px; color: #fff !important; }

/* Alert */
.csd-alert {
    display: flex; gap: 12px; align-items: center;
    padding: 14px 18px;
    background: #ECFDF5;
    border: 1px solid #A7F3D0;
    color: #065F46;
    border-radius: 12px;
    font-weight: 500;
    font-size: 13.5px;
}
.csd-alert i { color: #10B981; font-size: 16px; }

/* Cards */
.csd-card {
    background: #fff !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 18px !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    display: block !important;
    /* NOTE: no overflow: hidden — was clipping the page rows */
}
.csd-card > .csd-card__body,
.csd-card > details > .csd-card__body { border-radius: 0 0 18px 18px; }
.csd-card > .csd-card__head:last-child,
.csd-card > details > summary.csd-card__head:last-child { border-radius: 0 0 18px 18px; }
.csd-card--collapsible { padding: 0; }
.csd-card--collapsible > summary { list-style: none; cursor: pointer; }
.csd-card--collapsible > summary::-webkit-details-marker { display: none; }
.csd-card--collapsible[open] > summary .csd-collapse-chev { transform: rotate(180deg); }
.csd-collapse-chev {
    width: 32px; height: 32px;
    border-radius: 10px;
    background: #F1F5F9;
    color: #64748B;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 12px;
    transition: transform 0.25s, background 0.2s, color 0.2s;
    flex-shrink: 0;
}
.csd-card--collapsible > summary:hover .csd-collapse-chev { background: #E2E8F0; color: #0F172A; }

.csd-card__head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 22px 26px;
    border-bottom: 1px solid #F1F5F9;
    background: linear-gradient(180deg, #FAFBFF 0%, #FFFFFF 100%);
    gap: 16px; flex-wrap: wrap;
}
.csd-card__head--summary { user-select: none; }
.csd-card--collapsible:not([open]) > .csd-card__head--summary { border-bottom: 0; }
.csd-card__head-left { display: flex; gap: 14px; align-items: center; flex: 1; min-width: 0; }
.csd-card__icon {
    width: 44px; height: 44px;
    border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px;
    flex-shrink: 0;
    color: #fff !important;
}
.csd-card__icon--brand  { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.30); }
.csd-card__icon--accent { background: linear-gradient(135deg, #EC4899, #F59E0B); box-shadow: 0 6px 16px rgba(236, 72, 153, 0.30); }
.csd-card__title {
    margin: 0 !important;
    font-size: 16.5px !important;
    font-weight: 700 !important;
    color: #0F172A !important;
    letter-spacing: -0.015em;
    font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
    display: flex; align-items: center; gap: 10px;
}
.csd-card__count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 24px; height: 24px; padding: 0 7px;
    border-radius: 999px;
    background: #ecfdf5;
    color: #4F46E5;
    font-size: 12px; font-weight: 700;
    letter-spacing: 0;
}
.csd-card__sub { margin: 3px 0 0 !important; font-size: 13px !important; color: #64748B !important; line-height: 1.5; font-weight: 400 !important; }
.csd-card__sub strong { color: #0F172A; font-weight: 600; }
.csd-card__body { padding: 22px 26px; background: #fff; }

/* Empty state */
.csd-empty { text-align: center; padding: 50px 24px; }
.csd-empty__ic {
    width: 64px; height: 64px;
    border-radius: 18px;
    background: linear-gradient(135deg, #ecfdf5, #FAE8FF);
    color: #10b981;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}
.csd-empty h3 { margin: 0 0 6px; font-size: 18px; font-weight: 700; color: #0F172A; font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
.csd-empty p { margin: 0 0 20px; font-size: 14px; color: #64748B; }

/* Form primitives (high specificity to defeat Bootstrap form-control) */
.csd-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 720px) { .csd-form-grid { grid-template-columns: 1fr; } }
.csd-form-actions { margin-top: 18px; display: flex; gap: 10px; }
.csd-field { display: flex; flex-direction: column; }
.csd-label {
    font-size: 12.5px !important;
    font-weight: 600 !important;
    color: #334155 !important;
    margin-bottom: 6px !important;
    letter-spacing: -0.005em;
    display: block !important;
    line-height: 1.4;
}
.csd-req { color: #EF4444; }
.csd-err { color: #EF4444; font-size: 12px; margin-top: 5px; }
.csd-page input.csd-input,
.csd-dialog input.csd-input,
.csd-page select.csd-input,
.csd-dialog select.csd-input,
.csd-page textarea.csd-input {
    width: 100% !important;
    padding: 11px 14px !important;
    border: 1.5px solid #E2E8F0 !important;
    border-radius: 11px !important;
    font-size: 14px !important;
    color: #0F172A !important;
    background: #fff !important;
    transition: border-color 0.15s, box-shadow 0.15s !important;
    font-family: inherit !important;
    line-height: 1.5 !important;
    height: auto !important;
    box-shadow: none !important;
}
.csd-page input.csd-input::placeholder,
.csd-dialog input.csd-input::placeholder { color: #94A3B8 !important; }
.csd-page input.csd-input:hover:not(:focus),
.csd-dialog input.csd-input:hover:not(:focus) { border-color: #CBD5E1 !important; }
.csd-page input.csd-input:focus,
.csd-dialog input.csd-input:focus,
.csd-page select.csd-input:focus,
.csd-dialog select.csd-input:focus { outline: 0 !important; border-color: #10b981 !important; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.16) !important; }
.csd-input-group { display: flex; }
.csd-input-group .csd-input { border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important; }
.csd-input-group__suffix {
    padding: 11px 16px;
    background: #F1F5F9;
    border: 1.5px solid #E2E8F0; border-left: 0;
    border-top-right-radius: 11px; border-bottom-right-radius: 11px;
    color: #475569; font-size: 13px; font-weight: 500;
    display: flex; align-items: center; white-space: nowrap;
}

/* Buttons */
.csd-btn {
    display: inline-flex !important; align-items: center; gap: 7px;
    padding: 10px 18px;
    border-radius: 11px;
    border: 0; cursor: pointer;
    font-weight: 600; font-size: 13.5px;
    font-family: inherit;
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none !important;
    line-height: 1;
    letter-spacing: -0.005em;
}
.csd-btn--primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    color: #fff !important;
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.30);
}
.csd-btn--primary:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(16, 185, 129, 0.40); filter: brightness(1.05); color: #fff !important; }
.csd-btn--ghost { background: #F1F5F9 !important; color: #334155 !important; border: 1.5px solid transparent !important; }
.csd-btn--ghost:hover { background: #fff !important; border-color: #CBD5E1 !important; color: #0F172A !important; }
.csd-btn--white { background: #fff !important; color: #10b981 !important; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15); }
.csd-btn--white:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(0, 0, 0, 0.20); color: #4F46E5 !important; }
.csd-btn--sm { padding: 7px 13px; font-size: 12.5px; }
.csd-btn--icon { width: 36px; height: 36px; padding: 0 !important; justify-content: center; background: #F1F5F9 !important; color: #64748B !important; }
.csd-btn--icon:hover { background: #E2E8F0 !important; color: #0F172A !important; }
.csd-btn--icon-danger { background: #FEE2E2 !important; color: #DC2626 !important; }
.csd-btn--icon-danger:hover { background: #FECACA !important; color: #B91C1C !important; }

/* Pages list */
.csd-pages {
    display: flex !important;
    flex-direction: column !important;
    gap: 10px !important;
    width: 100% !important;
}
.csd-page-row {
    display: flex !important;
    flex-direction: row !important;
    justify-content: space-between !important;
    align-items: center !important;
    background: linear-gradient(180deg, #FAFBFF 0%, #F5F7FF 100%) !important;
    border: 1.5px solid #E2E8F0 !important;
    border-radius: 14px !important;
    padding: 18px 22px !important;
    gap: 16px !important;
    min-height: 80px !important;       /* never collapse */
    width: 100% !important;
    box-sizing: border-box !important;
    visibility: visible !important;
    opacity: 1 !important;
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    margin: 0 !important;
    position: relative;
}
.csd-page-row:hover { border-color: #a7f3d0 !important; transform: translateY(-1px); box-shadow: 0 6px 18px rgba(16, 185, 129, 0.10); background: linear-gradient(180deg, #FFFFFF 0%, #ecfdf5 100%) !important; }
.csd-page-row__lead {
    display: flex !important;
    gap: 14px !important;
    align-items: center !important;
    flex: 1 1 auto !important;
    min-width: 0 !important;
}
.csd-page-row__body { flex: 1 1 auto !important; min-width: 0 !important; }
.csd-page-row__name { font-weight: 700 !important; color: #0F172A !important; font-size: 15px !important; letter-spacing: -0.01em; }
.csd-page-row__icon {
    width: 44px !important; height: 44px !important;
    border-radius: 12px !important;
    display: flex !important; align-items: center !important; justify-content: center !important;
    font-size: 16px !important;
    flex-shrink: 0 !important;
}
.csd-page-row__icon--brand   { background: linear-gradient(135deg, #ecfdf5, #E0E7FF); color: #10b981; }
.csd-page-row__icon--info    { background: linear-gradient(135deg, #E0F2FE, #BAE6FD); color: #0EA5E9; }
.csd-page-row__icon--success { background: linear-gradient(135deg, #D1FAE5, #A7F3D0); color: #10B981; }
.csd-page-row__icon--warning { background: linear-gradient(135deg, #FEF3C7, #FDE68A); color: #F59E0B; }
.csd-page-row__icon--pink    { background: linear-gradient(135deg, #FCE7F3, #FBCFE8); color: #EC4899; }
.csd-page-row__icon--cyan    { background: linear-gradient(135deg, #CFFAFE, #A5F3FC); color: #06B6D4; }
.csd-page-row__icon--purple  { background: linear-gradient(135deg, #F3E8FF, #E9D5FF); color: #A855F7; }
.csd-page-row__icon--slate   { background: linear-gradient(135deg, #F1F5F9, #E2E8F0); color: #64748B; }

.csd-page-row__title {
    display: flex !important;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    line-height: 1.3;
    margin: 0;
}
.csd-page-row__meta {
    display: flex !important;
    gap: 14px !important;
    font-size: 12.5px !important;
    color: #64748B !important;
    margin-top: 6px !important;
    flex-wrap: wrap;
    align-items: center;
    line-height: 1.4;
}
.csd-meta-item { display: inline-flex; align-items: center; gap: 5px; }
.csd-meta-item i { color: #94A3B8; font-size: 11px; }

.csd-tag {
    font-size: 10.5px; padding: 2.5px 8px;
    border-radius: 6px;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
    line-height: 1.4;
}
.csd-tag--brand   { background: #ecfdf5; color: #4F46E5; }
.csd-tag--info    { background: #E0F2FE; color: #0369A1; }
.csd-tag--success { background: #D1FAE5; color: #047857; }
.csd-tag--warning { background: #FEF3C7; color: #92400E; }
.csd-tag--pink    { background: #FCE7F3; color: #BE185D; }
.csd-tag--cyan    { background: #CFFAFE; color: #0E7490; }
.csd-tag--purple  { background: #F3E8FF; color: #7C3AED; }
.csd-tag--slate   { background: #F1F5F9; color: #475569; }

.csd-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 600; padding: 3px 9px; border-radius: 999px; }
.csd-pill__dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.csd-pill--ok    { background: #D1FAE5; color: #047857; }
.csd-pill--ok .csd-pill__dot { box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18); }
.csd-pill--draft { background: #F1F5F9; color: #64748B; }

.csd-page-row__actions {
    display: flex !important;
    gap: 8px !important;
    align-items: center !important;
    flex-shrink: 0 !important;
}

/* Dialog */
.csd-dialog { border: 0; border-radius: 18px; padding: 0; box-shadow: 0 30px 80px rgba(15, 23, 42, 0.25); min-width: 440px; max-width: 90vw; background: #fff; }
.csd-dialog::backdrop { background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); }
.csd-dialog__head { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #F1F5F9; }
.csd-dialog__head h3 { margin: 0 !important; font-size: 17px !important; font-weight: 700 !important; font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; letter-spacing: -0.015em; color: #0F172A !important; }
.csd-dialog__body { padding: 22px 24px; display: flex; flex-direction: column; gap: 16px; }
.csd-dialog__foot { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #F1F5F9; background: #FAFBFF; border-bottom-left-radius: 18px; border-bottom-right-radius: 18px; }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
/* The hero (brand gradient), status pills, category icon/tag tints and the white
   on-hero buttons are semantic/brand and left as-is; only neutral chrome is themed.
   Rules mirror the source's !important usage so they win. */
html[data-theme="dark"] .csd-card{ background:#1e293b !important; border-color:#2a3a55 !important; box-shadow:none; }
html[data-theme="dark"] .csd-card__head{ background:#17233a; border-bottom-color:#2a3a55; }
html[data-theme="dark"] .csd-card__title{ color:#e2e8f0 !important; }
html[data-theme="dark"] .csd-card__sub{ color:#94a3b8 !important; }
html[data-theme="dark"] .csd-card__sub strong{ color:#e2e8f0; }
html[data-theme="dark"] .csd-card__body{ background:#1e293b; }
html[data-theme="dark"] .csd-collapse-chev{ background:#22304a; color:#94a3b8; }
html[data-theme="dark"] .csd-card--collapsible > summary:hover .csd-collapse-chev{ background:#2a3a55; color:#e2e8f0; }
html[data-theme="dark"] .csd-empty h3{ color:#e2e8f0; }
html[data-theme="dark"] .csd-empty p{ color:#94a3b8; }
html[data-theme="dark"] .csd-label{ color:#e2e8f0 !important; }
html[data-theme="dark"] .csd-page input.csd-input,
html[data-theme="dark"] .csd-dialog input.csd-input,
html[data-theme="dark"] .csd-page select.csd-input,
html[data-theme="dark"] .csd-dialog select.csd-input,
html[data-theme="dark"] .csd-page textarea.csd-input{ background:#1e293b !important; border-color:#2a3a55 !important; color:#e2e8f0 !important; }
html[data-theme="dark"] .csd-page input.csd-input:hover:not(:focus),
html[data-theme="dark"] .csd-dialog input.csd-input:hover:not(:focus){ border-color:#3a4a63 !important; }
html[data-theme="dark"] .csd-input-group__suffix{ background:#22304a; border-color:#2a3a55; color:#94a3b8; }
html[data-theme="dark"] .csd-btn--ghost{ background:#22304a !important; color:#e2e8f0 !important; }
html[data-theme="dark"] .csd-btn--ghost:hover{ background:#1e293b !important; border-color:#3a4a63 !important; color:#e2e8f0 !important; }
html[data-theme="dark"] .csd-btn--icon{ background:#22304a !important; color:#94a3b8 !important; }
html[data-theme="dark"] .csd-btn--icon:hover{ background:#2a3a55 !important; color:#e2e8f0 !important; }
html[data-theme="dark"] .csd-page-row{ background:#17233a !important; border-color:#2a3a55 !important; }
html[data-theme="dark"] .csd-page-row__name{ color:#e2e8f0 !important; }
html[data-theme="dark"] .csd-page-row__meta{ color:#94a3b8 !important; }
html[data-theme="dark"] .csd-tag--slate{ background:#22304a; color:#94a3b8; }
html[data-theme="dark"] .csd-pill--draft{ background:#22304a; color:#94a3b8; }
html[data-theme="dark"] .csd-dialog{ background:#1e293b; }
html[data-theme="dark"] .csd-dialog__head{ border-bottom-color:#2a3a55; }
html[data-theme="dark"] .csd-dialog__head h3{ color:#e2e8f0 !important; }
html[data-theme="dark"] .csd-dialog__foot{ background:#17233a; border-top-color:#2a3a55; }
</style>
@endsection
