@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<div class="corp-page" id="trainersPage">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-user-tie"></i> {{ __('Trainers') }}</h4>
            <p>{{ __('Your trainers and their session packages. Each trainer gets a public profile page with a Book Now button.') }}</p>
        </div>
    </div>

    @if(session('messege'))
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13.5px;">{{ session('messege') }}</div>
    @endif

    {{-- Add trainer --}}
    <div class="corp-form-card" style="margin-bottom:16px;">
        <div class="corp-form-card__body">
            <div style="font-weight:600;color:#1e293b;margin-bottom:10px;">{{ __('Add a trainer') }}</div>
            <form action="{{ route('instructor.trainers.store') }}" method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;align-items:end;">
                @csrf
                <label style="font-size:12.5px;color:#475569;">{{ __('Name') }} *
                    <input name="name" required class="form-control" placeholder="{{ __('Trainer name') }}" style="margin-top:4px;">
                </label>
                <label style="font-size:12.5px;color:#475569;">{{ __('Specialisation') }}
                    <input name="specialisation" class="form-control" placeholder="{{ __('e.g. Strength & Mobility') }}" style="margin-top:4px;">
                </label>
                <label style="font-size:12.5px;color:#475569;">{{ __('Experience') }}
                    <input name="experience" class="form-control" placeholder="{{ __('e.g. 12 yrs') }}" style="margin-top:4px;">
                </label>
                <label style="font-size:12.5px;color:#475569;">{{ __('Photo') }}
                    <input type="file" name="photo" accept="image/*" class="form-control" style="margin-top:4px;">
                </label>
                <button class="btn-corp-primary" style="white-space:nowrap;"><i class="fas fa-plus"></i> {{ __('Add trainer') }}</button>
            </form>
        </div>
    </div>

    @if ($trainers->count())
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;">
            @foreach ($trainers as $t)
                <div class="corp-form-card">
                    <div class="corp-form-card__body" style="display:flex;gap:12px;">
                        <div style="width:64px;height:64px;border-radius:12px;flex:none;overflow:hidden;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:20px;">
                            @if($t->photo)<img src="{{ \Illuminate\Support\Facades\Storage::url($t->photo) }}" alt="" style="width:100%;height:100%;object-fit:cover;">@else<i class="fas fa-user"></i>@endif
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-weight:600;color:#1e293b;">{{ $t->name }}</span>
                                @if($t->is_active)
                                    <span style="background:#ecfdf5;color:#065f46;font-size:10.5px;font-weight:600;padding:2px 8px;border-radius:999px;">{{ __('Active') }}</span>
                                @else
                                    <span style="background:#f1f5f9;color:#475569;font-size:10.5px;font-weight:600;padding:2px 8px;border-radius:999px;">{{ __('Hidden') }}</span>
                                @endif
                            </div>
                            <div style="color:#64748b;font-size:12.5px;">{{ $t->specialisation }}</div>
                            <div style="color:#94a3b8;font-size:12px;margin-top:2px;">{{ $t->packages_count }} {{ __('package(s)') }} · /{{ $t->slug }}</div>
                            <div style="display:flex;gap:6px;margin-top:9px;flex-wrap:wrap;">
                                <a href="{{ route('instructor.trainers.edit', $t->id) }}" class="btn-corp-secondary" style="padding:5px 10px;font-size:12px;"><i class="fas fa-pen"></i> {{ __('Manage') }}</a>
                                <form action="{{ route('instructor.trainers.toggle', $t->id) }}" method="POST" style="display:inline;">@csrf @method('PUT')
                                    <button class="btn-corp-secondary" style="padding:5px 10px;font-size:12px;">{{ $t->is_active ? __('Hide') : __('Show') }}</button>
                                </form>
                                <form action="{{ route('instructor.trainers.destroy', $t->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('{{ __('Delete this trainer and its packages?') }}');">@csrf @method('DELETE')
                                    <button class="btn-corp-secondary" style="padding:5px 10px;font-size:12px;color:#dc2626;"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="corp-form-card"><div class="corp-form-card__body" style="text-align:center;padding:36px 20px;color:#94a3b8;">
            <i class="fas fa-user-tie" style="font-size:30px;margin-bottom:10px;"></i>
            <p style="margin:0;">{{ __('No trainers yet. Add your first trainer above.') }}</p>
        </div></div>
    @endif
</div>
@endsection
