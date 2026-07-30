@extends('admin.master_layout')
@section('title') <title>{{ __('New Theme') }}</title> @endsection
@section('admin-content')
<div class="main-content">
<section class="section">
<div class="section-body">

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3" style="gap:12px;">
        <a href="{{ route('admin.themes.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h4 class="mb-0" style="font-weight:800;letter-spacing:-0.02em;">
            <i class="fa-solid fa-plus-circle" style="color:#6366F1;"></i> {{ __('Create New Theme') }}
        </h4>
    </div>

    <form action="{{ route('admin.themes.store') }}" method="POST" class="card" style="border:0;">
        @csrf
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" style="font-weight:600;">{{ __('Theme name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required maxlength="120" class="form-control" placeholder="e.g. Modern Yoga Studio">
                    @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" style="font-weight:600;">{{ __('URL slug') }} <small class="text-muted">(auto if blank)</small></label>
                    <input type="text" name="slug" pattern="[a-z0-9-]+" maxlength="140" class="form-control" placeholder="modern-yoga-studio">
                </div>

                <div class="col-12">
                    <label class="form-label" style="font-weight:600;">{{ __('Description') }}</label>
                    <textarea name="description" rows="3" maxlength="1000" class="form-control" placeholder="What kind of coach is this theme designed for?"></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label" style="font-weight:600;">{{ __('Thumbnail URL') }}</label>
                    <input type="text" name="thumbnail_url" maxlength="500" class="form-control" placeholder="https://...">
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-weight:600;">{{ __('Primary color') }}</label>
                    <input type="color" name="primary_color" value="#6366F1" class="form-control" style="height:38px;">
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-weight:600;">{{ __('Accent color') }}</label>
                    <input type="color" name="accent_color" value="#8B5CF6" class="form-control" style="height:38px;">
                </div>

                <div class="col-md-6">
                    <label class="form-label" style="font-weight:600;">{{ __('Display font') }}</label>
                    <select name="display_font" class="form-control">
                        @foreach(['Plus Jakarta Sans','Inter','Poppins','Playfair Display','Montserrat','Raleway','Oswald','Bebas Neue'] as $f)
                            <option value="{{ $f }}">{{ $f }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" style="font-weight:600;">{{ __('Body font') }}</label>
                    <select name="body_font" class="form-control">
                        @foreach(['Inter','Plus Jakarta Sans','Poppins','Open Sans','Lato','Nunito','DM Sans'] as $f)
                            <option value="{{ $f }}">{{ $f }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label" style="font-weight:600;">{{ __('Categories') }}</label>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        @foreach($categories as $c)
                            <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border:1.5px solid #E2E8F0;border-radius:999px;cursor:pointer;font-size:13px;">
                                <input type="checkbox" name="categories[]" value="{{ $c->id }}"> {{ $c->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-12">
                    <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;">
                        <input type="checkbox" name="is_premium" value="1">
                        <i class="fa-solid fa-crown" style="color:#F59E0B;"></i> {{ __('Premium theme (paid tier)') }}
                    </label>
                </div>
            </div>
        </div>
        <div class="card-footer" style="background:#FAFBFF;display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('admin.themes.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg,#6366F1,#8B5CF6);border:0;">
                <i class="fa-solid fa-save"></i> {{ __('Create Theme') }}
            </button>
        </div>
    </form>
</div>

</div>
</section>
</div>
@endsection
