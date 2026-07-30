@extends('admin.master_layout')
@section('title') <title>{{ __('Theme Categories') }}</title> @endsection
@section('admin-content')
<div class="main-content">
<section class="section">
<div class="section-body">

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3" style="gap:12px;">
        <a href="{{ route('admin.themes.index') }}" class="btn btn-outline-secondary btn-sm"
            title="{{ __('Back') }}"
            aria-label="{{ __('Back to themes') }}">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h4 class="mb-0" style="font-weight:800;letter-spacing:-0.02em;">
            <i class="fa-solid fa-tag" style="color:#6366F1;"></i> {{ __('Theme Categories') }}
        </h4>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row g-3">
        <div class="col-md-4">
            <form method="POST" action="{{ route('admin.themes.categories.store') }}" class="card" style="border:0;">
                @csrf
                <div class="card-header" style="background:#FAFBFF;font-weight:700;">{{ __('New category') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Name') }}</label>
                        <input type="text" name="name" required maxlength="80" class="form-control" placeholder="e.g. Mental Health">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Icon') }} <small class="text-muted">(Font Awesome class)</small></label>
                        <input type="text" name="icon" maxlength="60" class="form-control" placeholder="fa-solid fa-brain">
                    </div>
                </div>
                <div class="card-footer" style="background:#FAFBFF;">
                    <button class="btn btn-primary w-100"><i class="fa-solid fa-plus"></i> {{ __('Add') }}</button>
                </div>
            </form>
        </div>
        <div class="col-md-8">
            <div class="card" style="border:0;">
                <div class="card-header" style="background:#FAFBFF;font-weight:700;">{{ __('All categories') }}</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead>
                            <tr style="background:#F8FAFC;">
                                <th>{{ __('Icon') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Themes') }}</th>
                                <th style="width:120px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $cat)
                                <tr>
                                    <td><i class="{{ $cat->icon ?? 'fa-solid fa-tag' }}" style="color:#6366F1;font-size:18px;"></i></td>
                                    <td><strong>{{ $cat->name }}</strong><br><small class="text-muted">{{ $cat->slug }}</small></td>
                                    <td><span class="badge bg-primary">{{ $cat->themes_count }}</span></td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.themes.categories.destroy', $cat->id) }}" onsubmit="return confirm('Delete?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="button"
                                                title="{{ __('Delete') }}"
                                                aria-label="{{ __('Delete theme category') }}"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4 text-muted">{{ __('No categories yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
</section>
</div>
@endsection
