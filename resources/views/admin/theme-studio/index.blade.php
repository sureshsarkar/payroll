@extends('admin.master_layout')
@section('title') <title>{{ __('Theme Studio') }}</title> @endsection
@section('admin-content')
<div class="main-content">
<section class="section">
<div class="section-body">

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap" style="gap:12px;">
        <div>
            <h4 class="mb-1" style="font-weight:800;letter-spacing:-0.02em;">
                <i class="fa-solid fa-palette" style="color:#6366F1;"></i> {{ __('Theme Studio') }}
            </h4>
            <p class="text-muted mb-0" style="font-size:13.5px;">
                {{ __('Curate the themes coaches can pick during onboarding.') }}
            </p>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.themes.categories.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-tag"></i> {{ __('Categories') }}
            </a>
            <a href="{{ route('admin.themes.create') }}" class="btn btn-primary"
               style="background:linear-gradient(135deg,#6366F1,#8B5CF6);border:0;">
                <i class="fa-solid fa-plus"></i> {{ __('New Theme') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="card mb-3" style="border:0;box-shadow:0 2px 8px rgba(15,23,42,0.05);">
        <div class="card-body py-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="{{ __('Search themes…') }}"
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="enabled"  {{ request('status')==='enabled' ?'selected':'' }}>{{ __('Enabled only') }}</option>
                        <option value="disabled" {{ request('status')==='disabled'?'selected':'' }}>{{ __('Disabled only') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-control">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->slug }}" {{ request('category')===$c->slug?'selected':'' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100"><i class="fa-solid fa-filter"></i></button>
                </div>
            </div>
        </div>
    </form>

    {{-- Theme grid --}}
    @if($themes->isEmpty())
        <div class="card" style="border:0;">
            <div class="card-body text-center py-5">
                <i class="fa-solid fa-palette" style="font-size:48px;color:#CBD5E1;"></i>
                <h5 class="mt-3">{{ __('No themes yet') }}</h5>
                <p class="text-muted">{{ __('Create your first theme to get started.') }}</p>
                <a href="{{ route('admin.themes.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Create theme') }}
                </a>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach($themes as $theme)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100" style="border:1px solid #E2E8F0;border-radius:14px;overflow:hidden;">
                        {{-- Thumbnail --}}
                        <div style="aspect-ratio:16/10;background:linear-gradient(135deg,
                                        {{ $theme->default_colors['primary'] ?? '#6366F1' }} 0%,
                                        {{ $theme->default_colors['accent']  ?? '#8B5CF6' }} 100%);
                                    position:relative;display:flex;align-items:center;justify-content:center;">
                            @if($theme->thumbnail_url)
                                <img src="{{ $theme->thumbnail_url }}" alt="{{ $theme->name }}"
                                     style="width:100%;height:100%;object-fit:cover;">
                            @else
                                <span style="color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:28px;letter-spacing:-0.02em;text-align:center;padding:0 20px;">
                                    {{ $theme->name }}
                                </span>
                            @endif
                            @if($theme->is_enabled)
                                <span style="position:absolute;top:10px;right:10px;background:#10B981;color:#fff;font-size:10px;font-weight:700;padding:4px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:0.8px;">
                                    <i class="fa-solid fa-circle-check"></i> {{ __('Enabled') }}
                                </span>
                            @else
                                <span style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,0.85);color:#475569;font-size:10px;font-weight:700;padding:4px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:0.8px;">
                                    {{ __('Draft') }}
                                </span>
                            @endif
                            @if($theme->is_premium)
                                <span style="position:absolute;top:10px;left:10px;background:#F59E0B;color:#fff;font-size:10px;font-weight:700;padding:4px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:0.8px;">
                                    <i class="fa-solid fa-crown"></i> {{ __('Premium') }}
                                </span>
                            @endif
                        </div>
                        <div class="card-body" style="padding:16px 18px;">
                            <h6 style="font-weight:700;margin:0 0 4px;font-size:15.5px;letter-spacing:-0.01em;">
                                {{ $theme->name }}
                            </h6>
                            @if($theme->description)
                                <p style="font-size:12.5px;color:#64748B;margin:0 0 10px;line-height:1.55;">
                                    {{ \Illuminate\Support\Str::limit($theme->description, 80) }}
                                </p>
                            @endif
                            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;">
                                @foreach($theme->categories as $cat)
                                    <span style="font-size:10.5px;padding:2px 7px;background:#F1F5F9;color:#475569;border-radius:5px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">
                                        {{ $cat->name }}
                                    </span>
                                @endforeach
                            </div>
                            <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;color:#64748B;margin-bottom:12px;">
                                <span><i class="fa-solid fa-users"></i> {{ $usage[$theme->id] ?? 0 }} {{ __('coaches') }}</span>
                                <span>v{{ $theme->version }}</span>
                            </div>
                            <div style="display:flex;gap:6px;">
                                <a href="{{ route('admin.themes.edit', $theme->id) }}" class="btn btn-sm btn-primary" style="flex:1;">
                                    <i class="fa-solid fa-pen"></i> {{ __('Edit') }}
                                </a>
                                <a href="{{ route('admin.themes.preview', $theme->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="{{ __('Preview') }}">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle-id="{{ $theme->id }}" title="{{ $theme->is_enabled ? __('Disable') : __('Enable') }}">
                                    <i class="fa-solid fa-{{ $theme->is_enabled ? 'toggle-on' : 'toggle-off' }}"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $themes->withQueryString()->links() }}
        </div>
    @endif
</div>

<script>
document.querySelectorAll('[data-toggle-id]').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.toggleId;
        const res = await fetch("{{ url('admin/themes') }}/" + id + "/toggle", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).then(r => r.json()).catch(() => null);
        if (res && res.ok) location.reload();
    });
});
</script>

</div>
</section>
</div>
@endsection
