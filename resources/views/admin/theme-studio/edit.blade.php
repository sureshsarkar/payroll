@extends('admin.master_layout')
@section('title') <title>{{ __('Edit Theme — ') . $theme->name }}</title> @endsection
@section('admin-content')
<div class="main-content">
<section class="section">
<div class="section-body">

<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap:12px;">
        <div class="d-flex align-items-center" style="gap:12px;">
            <a href="{{ route('admin.themes.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h4 class="mb-0" style="font-weight:800;letter-spacing:-0.02em;">
                <i class="fa-solid fa-pen" style="color:#6366F1;"></i> {{ $theme->name }}
            </h4>
            @if($theme->is_enabled)
                <span style="background:#D1FAE5;color:#047857;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;">
                    <i class="fa-solid fa-circle-check"></i> {{ __('Enabled') }}
                </span>
            @else
                <span style="background:#F1F5F9;color:#64748B;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;">
                    {{ __('Draft') }}
                </span>
            @endif
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.themes.preview', $theme->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-eye"></i> {{ __('Preview') }}
            </a>
            <a href="{{ route('admin.themes.usage', $theme->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-users"></i> {{ __('Usage') }} ({{ $theme->activeUsageCount() }})
            </a>
            <form method="POST" action="{{ route('admin.themes.duplicate', $theme->id) }}" style="display:inline;">
                @csrf
                <button class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-copy"></i> {{ __('Duplicate') }}</button>
            </form>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="row g-3">
        {{-- Basic info --}}
        <div class="col-md-7">
            <form action="{{ route('admin.themes.update', $theme->id) }}" method="POST" class="card" style="border:0;">
                @csrf @method('PUT')
                <div class="card-header" style="background:#FAFBFF;font-weight:700;">{{ __('Theme settings') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" style="font-weight:600;">{{ __('Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $theme->name) }}" required maxlength="120" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-weight:600;">{{ __('Description') }}</label>
                            <textarea name="description" rows="3" maxlength="1000" class="form-control">{{ old('description', $theme->description) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-weight:600;">{{ __('Thumbnail URL') }}</label>
                            <input type="text" name="thumbnail_url" value="{{ old('thumbnail_url', $theme->thumbnail_url) }}" maxlength="500" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:600;">{{ __('Primary color') }}</label>
                            <input type="color" name="primary_color" value="{{ $theme->default_colors['primary'] ?? '#6366F1' }}" class="form-control" style="height:38px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:600;">{{ __('Accent color') }}</label>
                            <input type="color" name="accent_color" value="{{ $theme->default_colors['accent'] ?? '#8B5CF6' }}" class="form-control" style="height:38px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:600;">{{ __('Display font') }}</label>
                            <select name="display_font" class="form-control">
                                @foreach(['Plus Jakarta Sans','Inter','Poppins','Playfair Display','Montserrat','Raleway','Oswald','Bebas Neue','Merriweather'] as $f)
                                    <option value="{{ $f }}" {{ ($theme->default_fonts['display'] ?? '')===$f?'selected':'' }}>{{ $f }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:600;">{{ __('Body font') }}</label>
                            <select name="body_font" class="form-control">
                                @foreach(['Inter','Plus Jakarta Sans','Poppins','Open Sans','Lato','Nunito','DM Sans'] as $f)
                                    <option value="{{ $f }}" {{ ($theme->default_fonts['body'] ?? '')===$f?'selected':'' }}>{{ $f }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-weight:600;">{{ __('Categories') }}</label>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                @foreach($categories as $c)
                                    <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border:1.5px solid #E2E8F0;border-radius:999px;cursor:pointer;font-size:13px;">
                                        <input type="checkbox" name="categories[]" value="{{ $c->id }}" {{ $theme->categories->contains($c->id)?'checked':'' }}> {{ $c->name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;">
                                <input type="checkbox" name="is_premium" value="1" {{ $theme->is_premium?'checked':'' }}>
                                <i class="fa-solid fa-crown" style="color:#F59E0B;"></i> {{ __('Premium theme') }}
                            </label>
                        </div>
                    </div>
                </div>
                <div class="card-footer" style="background:#FAFBFF;display:flex;justify-content:flex-end;gap:8px;">
                    <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg,#6366F1,#8B5CF6);border:0;">
                        <i class="fa-solid fa-save"></i> {{ __('Save changes') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- Pages + sections inventory --}}
        <div class="col-md-5">
            <div class="card" style="border:0;">
                <div class="card-header" style="background:#FAFBFF;font-weight:700;">
                    {{ __('Pages & Sections') }}
                    <small class="text-muted ms-2">{{ $theme->pages->count() }} {{ __('pages') }}</small>
                </div>
                <div class="card-body" style="padding:14px;">
                    @forelse($theme->pages as $page)
                        <div style="border:1px solid #E2E8F0;border-radius:10px;padding:12px 14px;margin-bottom:8px;">
                            <div style="font-weight:700;font-size:14px;">
                                <i class="fa-solid fa-file-lines" style="color:#6366F1;"></i> {{ $page->title }}
                                <small class="text-muted">/{{ $page->slug }}</small>
                            </div>
                            <div style="font-size:12px;color:#64748B;margin-top:4px;">
                                {{ $page->sections->count() }} {{ __('sections') }}:
                                @foreach($page->sections as $s)
                                    <span style="font-family:monospace;font-size:11px;background:#F1F5F9;padding:1px 5px;border-radius:4px;margin-left:2px;">{{ $s->section_type }}</span>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0 py-3">{{ __('No pages yet.') }}</p>
                    @endforelse
                    <p class="text-muted mt-3" style="font-size:12.5px;">
                        <i class="fa-solid fa-info-circle"></i> {{ __('Theme content editing UI is built into the existing Coach editor — link coming in Phase 2.5.') }}
                    </p>
                </div>
            </div>

            {{-- Danger zone --}}
            <div class="card mt-3" style="border:1px solid #FCA5A5;">
                <div class="card-header" style="background:#FEF2F2;color:#991B1B;font-weight:700;">{{ __('Danger zone') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.themes.destroy', $theme->id) }}" onsubmit="return confirm('Delete this theme permanently?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm">
                            <i class="fa-solid fa-trash"></i> {{ __('Delete theme') }}
                        </button>
                        <small class="text-muted ms-2">{{ __('Blocked if coaches are using it.') }}</small>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
</section>
</div>
@endsection
