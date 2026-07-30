@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<div class="corp-page" id="trainerEdit">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-user-tie"></i> {{ $trainer->name }}</h4>
            <p>{{ __('Edit the trainer profile and manage session packages.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.trainers.index') }}" class="btn-corp-secondary">← {{ __('All trainers') }}</a>
        </div>
    </div>

    @if(session('messege'))
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13.5px;">{{ session('messege') }}</div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 1.2fr;gap:14px;align-items:start;">

        {{-- Trainer profile --}}
        <div class="corp-form-card">
            <div class="corp-form-card__body">
                <div style="font-weight:600;color:#1e293b;margin-bottom:12px;">{{ __('Profile') }}</div>
                <form action="{{ route('instructor.trainers.update', $trainer->id) }}" method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:10px;">
                    @csrf @method('PUT')
                    <div style="display:flex;gap:12px;align-items:center;">
                        <div style="width:70px;height:70px;border-radius:12px;overflow:hidden;flex:none;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:22px;">
                            @if($trainer->photo)<img src="{{ \Illuminate\Support\Facades\Storage::url($trainer->photo) }}" alt="" style="width:100%;height:100%;object-fit:cover;">@else<i class="fas fa-user"></i>@endif
                        </div>
                        <label style="font-size:12.5px;color:#475569;flex:1;">{{ __('Replace photo') }}
                            <input type="file" name="photo" accept="image/*" class="form-control" style="margin-top:4px;">
                        </label>
                    </div>
                    <label style="font-size:12.5px;color:#475569;">{{ __('Name') }} *<input name="name" required value="{{ $trainer->name }}" class="form-control" style="margin-top:4px;"></label>
                    <label style="font-size:12.5px;color:#475569;">{{ __('Specialisation') }}<input name="specialisation" value="{{ $trainer->specialisation }}" class="form-control" style="margin-top:4px;"></label>
                    <label style="font-size:12.5px;color:#475569;">{{ __('Experience') }}<input name="experience" value="{{ $trainer->experience }}" class="form-control" style="margin-top:4px;"></label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <label style="font-size:12.5px;color:#475569;">{{ __('Certificate issue date') }}<input name="certificate_date" value="{{ $trainer->certificate_date }}" class="form-control" placeholder="14-Jan-2026" style="margin-top:4px;"></label>
                        <label style="font-size:12.5px;color:#475569;">{{ __('Certificate number') }}<input name="certificate_number" value="{{ $trainer->certificate_number }}" class="form-control" placeholder="12345678958776" style="margin-top:4px;"></label>
                    </div>
                    <label style="font-size:12.5px;color:#475569;">{{ __('Specialisation tags (comma-separated)') }}<input name="tags" value="{{ is_array($trainer->tags) ? implode(', ', $trainer->tags) : '' }}" class="form-control" placeholder="Hatha, Strength, Rehab" style="margin-top:4px;"></label>
                    <label style="font-size:12.5px;color:#475569;">{{ __('Bio') }}<textarea name="bio" rows="3" class="form-control" style="margin-top:4px;">{{ $trainer->bio }}</textarea></label>

                    <div style="border-top:1px solid #eef0f5;padding-top:10px;margin-top:2px;">
                        <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:8px;">{{ __('Booking form options (comma-separated)') }}</div>
                        <label style="font-size:12.5px;color:#475569;">{{ __('Plan types') }}<input name="plan_types" value="{{ is_array($trainer->plan_types) ? implode(', ', $trainer->plan_types) : '' }}" class="form-control" placeholder="Online, Offline" style="margin-top:4px;"></label>
                        <label style="font-size:12.5px;color:#475569;margin-top:8px;display:block;">{{ __('Course types') }}<input name="course_types" value="{{ is_array($trainer->course_types) ? implode(', ', $trainer->course_types) : '' }}" class="form-control" placeholder="Individual Plan, Couple Plan" style="margin-top:4px;"></label>
                        <label style="font-size:12.5px;color:#475569;margin-top:8px;display:block;">{{ __('Reasons') }}<input name="reasons" value="{{ is_array($trainer->reasons) ? implode(', ', $trainer->reasons) : '' }}" class="form-control" placeholder="Fitness, Weight Loss, Problem" style="margin-top:4px;"></label>
                        <div style="font-size:11px;color:#94a3b8;margin-top:6px;">{{ __('Leave blank to use defaults.') }}</div>
                    </div>
                    <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#334155;">
                        <input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" {{ $trainer->is_active ? 'checked' : '' }}> {{ __('Active (visible on the public site)') }}
                    </label>
                    <div><button class="btn-corp-primary">{{ __('Save profile') }}</button></div>
                </form>
            </div>
        </div>

        {{-- Session packages --}}
        <div class="corp-form-card">
            <div class="corp-form-card__body">
                <div style="font-weight:600;color:#1e293b;margin-bottom:12px;">{{ __('Session packages') }}</div>

                @if($packages->count())
                    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">
                        @foreach($packages as $p)
                            <div style="border:1px solid #eef0f5;border-radius:10px;padding:10px;display:flex;gap:8px;align-items:end;{{ $p->is_active ? '' : 'opacity:.55;' }}">
                                <form action="{{ route('instructor.trainers.packages.update', [$trainer->id, $p->id]) }}" method="POST" style="flex:1;display:grid;grid-template-columns:1.4fr .8fr .8fr .8fr 1fr auto;gap:8px;align-items:end;">
                                    @csrf @method('PUT')
                                    <label style="font-size:11px;color:#94a3b8;">{{ __('Name (optional)') }}<input name="name" value="{{ $p->name }}" class="form-control form-control-sm" placeholder="{{ $p->sessions }} sessions"></label>
                                    <label style="font-size:11px;color:#94a3b8;">{{ __('Sessions') }}<input name="sessions" type="number" min="1" value="{{ $p->sessions }}" class="form-control form-control-sm"></label>
                                    <label style="font-size:11px;color:#94a3b8;">{{ __('Validity') }}<input name="validity_value" type="number" min="1" value="{{ $p->validity_value }}" class="form-control form-control-sm"></label>
                                    <label style="font-size:11px;color:#94a3b8;">{{ __('Unit') }}
                                        <select name="validity_unit" class="form-control form-control-sm">
                                            @foreach(['days','weeks','months'] as $u)<option value="{{ $u }}" {{ $p->validity_unit===$u?'selected':'' }}>{{ ucfirst($u) }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label style="font-size:11px;color:#94a3b8;">{{ __('Price') }}<input name="price" type="number" step="0.01" min="0" value="{{ (float)$p->price }}" class="form-control form-control-sm"></label>
                                    <button class="btn-corp-secondary" style="padding:5px 8px;" title="{{ __('Save') }}"><i class="fas fa-check"></i></button>
                                </form>
                                <form action="{{ route('instructor.trainers.packages.toggle', [$trainer->id, $p->id]) }}" method="POST">@csrf @method('PUT')<button class="btn-corp-secondary" style="padding:5px 8px;" title="{{ $p->is_active?__('Hide'):__('Show') }}"><i class="fas {{ $p->is_active?'fa-eye':'fa-eye-slash' }}"></i></button></form>
                                <form action="{{ route('instructor.trainers.packages.destroy', [$trainer->id, $p->id]) }}" method="POST" onsubmit="return confirm('{{ __('Delete package?') }}');">@csrf @method('DELETE')<button class="btn-corp-secondary" style="padding:5px 8px;color:#dc2626;"><i class="fas fa-trash"></i></button></form>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="color:#94a3b8;font-size:13px;margin-bottom:12px;">{{ __('No packages yet. Add one below.') }}</div>
                @endif

                <div style="font-weight:500;color:#475569;font-size:12.5px;margin-bottom:6px;">{{ __('Add package') }}</div>
                <form action="{{ route('instructor.trainers.packages.store', $trainer->id) }}" method="POST" style="display:grid;grid-template-columns:1.4fr .8fr .8fr .8fr 1fr auto;gap:8px;align-items:end;">
                    @csrf
                    <label style="font-size:11px;color:#94a3b8;">{{ __('Name (optional)') }}<input name="name" class="form-control form-control-sm" placeholder="{{ __('e.g. Starter') }}"></label>
                    <label style="font-size:11px;color:#94a3b8;">{{ __('Sessions') }} *<input name="sessions" type="number" min="1" value="1" required class="form-control form-control-sm"></label>
                    <label style="font-size:11px;color:#94a3b8;">{{ __('Validity') }} *<input name="validity_value" type="number" min="1" value="30" required class="form-control form-control-sm"></label>
                    <label style="font-size:11px;color:#94a3b8;">{{ __('Unit') }}<select name="validity_unit" class="form-control form-control-sm"><option>days</option><option>weeks</option><option>months</option></select></label>
                    <label style="font-size:11px;color:#94a3b8;">{{ __('Price') }} *<input name="price" type="number" step="0.01" min="0" value="0" required class="form-control form-control-sm"></label>
                    <button class="btn-corp-primary" style="padding:6px 10px;"><i class="fas fa-plus"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
