@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<div class="corp-page">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-palette"></i> {{ __('Change Theme') }}</h4>
            <p>{{ __('Switch to a different theme. Your existing pages will be snapshotted before replacement — revert from version history if needed.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.web-page.index') }}" class="btn-corp-secondary">
                <i class="fas fa-arrow-left"></i> {{ __('Back') }}
            </a>
        </div>
    </div>

    <div class="corp-form-card">
        <div class="corp-form-card__body">
            <div class="alert alert-warning" style="background:#FEF3C7;border:1px solid #FDE68A;color:#92400E;border-radius:10px;padding:14px 18px;font-size:13.5px;display:flex;gap:10px;align-items:flex-start;">
                <i class="fa-solid fa-shield-halved" style="font-size:16px;margin-top:1px;"></i>
                <div>
                    <strong>{{ __('Your website data is safe.') }}</strong>
                    {{ __('Changing the theme updates your website appearance, layout and styling only. Your pages, content, images, courses, menus, forms and SEO settings are NOT changed. You can switch back to a previous theme any time and your customizations are restored.') }}
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px;margin-top:18px;">
                @foreach($themes as $theme)
                    @php
                        $primary = $theme->default_colors['primary'] ?? '#10b981';
                        $accent  = $theme->default_colors['accent']  ?? '#059669';
                        $isCurrent = $theme->id === $currentThemeId;
                    @endphp
                    <div style="background:#fff;border:1.5px solid {{ $isCurrent ? '#10b981' : '#E2E8F0' }};border-radius:14px;overflow:hidden;{{ $isCurrent ? 'box-shadow:0 0 0 4px rgba(16, 185, 129,0.15);' : '' }}">
                        <div style="aspect-ratio:16/10;background:linear-gradient(135deg,{{ $primary }} 0%,{{ $accent }} 100%);display:flex;align-items:center;justify-content:center;position:relative;">
                            @if($theme->thumbnail_url)
                                <img src="{{ $theme->thumbnail_url }}" alt="{{ $theme->name }}" style="width:100%;height:100%;object-fit:cover;">
                            @else
                                <span style="color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:22px;letter-spacing:-0.02em;text-align:center;padding:0 20px;">{{ $theme->name }}</span>
                            @endif
                            @if($isCurrent)
                                <span style="position:absolute;top:10px;right:10px;background:#fff;color:#4F46E5;font-size:10.5px;font-weight:700;padding:4px 10px;border-radius:999px;text-transform:uppercase;letter-spacing:0.6px;">
                                    <i class="fa-solid fa-circle-check"></i> {{ __('Current') }}
                                </span>
                            @endif
                        </div>
                        <div style="padding:16px 18px;">
                            <h6 style="font-weight:700;margin:0 0 4px;font-size:15px;letter-spacing:-0.01em;">{{ $theme->name }}</h6>
                            @if($theme->description)
                                <p style="font-size:12.5px;color:#64748B;margin:0 0 12px;line-height:1.55;">{{ \Illuminate\Support\Str::limit($theme->description, 90) }}</p>
                            @endif
                            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;">
                                @foreach($theme->categories as $cat)
                                    <span style="font-size:10.5px;padding:2px 7px;background:#ecfdf5;color:#4F46E5;border-radius:5px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">{{ $cat->name }}</span>
                                @endforeach
                            </div>
                            <div style="display:flex;gap:6px;">
                                <a href="{{ route('admin.themes.preview', $theme->id) }}" target="_blank" class="btn-corp-secondary btn-corp-sm" style="flex:0 0 auto;">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                @if($isCurrent)
                                    <button type="button" class="btn-corp-secondary btn-corp-sm" disabled style="flex:1;opacity:0.6;cursor:not-allowed;">
                                        {{ __('Currently active') }}
                                    </button>
                                @else
                                    <button type="button" class="btn-corp-primary btn-corp-sm" style="flex:1;" data-switch-theme="{{ $theme->id }}" data-theme-name="{{ $theme->name }}">
                                        {{ __('Switch to this') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-switch-theme]').forEach(btn => {
    btn.addEventListener('click', async function() {
        const themeId = this.dataset.switchTheme;
        const themeName = this.dataset.themeName;
        if (! confirm("{{ __('You are changing the website theme to') }} \"" + themeName + "\". {{ __('Some layout and design elements may change, but your website data will remain safe. Do you want to continue?') }}")) return;
        this.disabled = true;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> {{ __("Switching…") }}';
        const res = await fetch("{{ route('instructor.web-page.change-theme') }}", {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept':'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ theme_id: themeId }),
        }).then(r => r.json()).catch(() => null);
        if (res && res.ok) {
            window.location.href = res.redirect_to;
        } else {
            alert((res && res.error) || 'Failed');
            this.disabled = false; this.textContent = "{{ __('Switch to this') }}";
        }
    });
});
</script>
@endsection
