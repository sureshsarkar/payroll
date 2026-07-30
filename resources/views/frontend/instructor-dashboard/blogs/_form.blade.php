@php $b = $blog ?? null; @endphp
<style>
    .bf-grp{ margin-bottom:16px; }
    .bf-grp label{ display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px; }
    .bf-grp label .req{ color:#dc2626; }
    .bf-grp input[type=text], .bf-grp input[type=datetime-local], .bf-grp select, .bf-grp textarea{
        width:100%; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; font-size:14px; background:#fff; }
    .bf-grp input:focus, .bf-grp select:focus, .bf-grp textarea:focus{ outline:none; border-color:#10b981; box-shadow:0 0 0 3px rgba(16, 185, 129,.15); }
    .bf-row{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .bf-hint{ font-size:11.5px; color:#94a3b8; margin-top:4px; }
    .bf-thumb{ width:120px; height:80px; object-fit:cover; border-radius:10px; border:1px solid #e2e8f0; margin-top:8px; }
    .bf-err{ color:#dc2626; font-size:12px; margin-top:4px; }
    @media (max-width:640px){ .bf-row{ grid-template-columns:1fr; } }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] .bf-grp label{ color:#e2e8f0; }
html[data-theme="dark"] .bf-grp input[type=text],
html[data-theme="dark"] .bf-grp input[type=datetime-local],
html[data-theme="dark"] .bf-grp select,
html[data-theme="dark"] .bf-grp textarea{ background:#1e293b; border-color:#2a3a55; color:#e2e8f0; }
html[data-theme="dark"] .bf-grp input::placeholder,
html[data-theme="dark"] .bf-grp textarea::placeholder{ color:#64748b; }
html[data-theme="dark"] .bf-hint{ color:#94a3b8; }
html[data-theme="dark"] .bf-thumb{ border-color:#2a3a55; }
</style>

@if ($errors->any())
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13px;">
        <ul style="margin:0;padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="bf-grp">
    <label>{{ __('Blog title') }} <span class="req">*</span></label>
    <input type="text" name="title" maxlength="255" value="{{ old('title', $b?->title ?? '') }}" placeholder="{{ __('e.g. 5 tips to crack the exam') }}">
</div>

<div class="bf-grp">
    <label>{{ __('Featured image') }}</label>
    <input type="file" name="image" accept="image/*">
    @if($b?->image)<div><img src="{{ asset($b->image) }}" class="bf-thumb" alt=""></div>@endif
    <p class="bf-hint">{{ __('JPG/PNG/WebP, up to 5 MB. Shown on the blog card and detail page.') }}</p>
</div>

<div class="bf-grp">
    <label>{{ __('Short description') }}</label>
    <textarea name="short_description" rows="2" maxlength="1000" placeholder="{{ __('One or two lines shown on the blog card.') }}">{{ old('short_description', $b?->short_description ?? '') }}</textarea>
</div>

<div class="bf-grp">
    <label>{{ __('Full content') }}</label>
    <textarea name="content" class="text-editor-img" rows="12">{{ old('content', $b?->content ?? '') }}</textarea>
</div>

<div class="bf-row">
    <div class="bf-grp">
        <label>{{ __('Status') }} <span class="req">*</span></label>
        <select name="status">
            <option value="draft" {{ old('status', $b?->status ?? 'draft') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
            <option value="published" {{ old('status', $b?->status ?? '') === 'published' ? 'selected' : '' }}>{{ __('Published') }}</option>
        </select>
        <p class="bf-hint">{{ __('Only published posts appear on your website.') }}</p>
    </div>
    <div class="bf-grp">
        <label>{{ __('Publish date') }}</label>
        <input type="datetime-local" name="published_at"
               value="{{ old('published_at', $b?->published_at ? \Carbon\Carbon::parse($b->published_at)->format('Y-m-d\TH:i') : '') }}">
        <p class="bf-hint">{{ __('Leave blank to publish now. Set a future date to schedule.') }}</p>
    </div>
</div>

<details style="margin-top:6px;">
    <summary style="cursor:pointer;font-size:13px;font-weight:600;color:#475569;margin-bottom:8px;">{{ __('SEO settings (optional)') }}</summary>
    <div class="bf-grp" style="margin-top:10px;">
        <label>{{ __('SEO meta title') }}</label>
        <input type="text" name="seo_title" maxlength="255" value="{{ old('seo_title', $b?->seo_title ?? '') }}">
    </div>
    <div class="bf-grp">
        <label>{{ __('SEO meta description') }}</label>
        <textarea name="seo_description" rows="2" maxlength="500">{{ old('seo_description', $b?->seo_description ?? '') }}</textarea>
    </div>
</details>
