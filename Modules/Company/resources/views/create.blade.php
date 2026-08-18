@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div style="max-width:640px;margin:0 auto;padding:8px 4px;">
    <h1 style="font-size:20px;font-weight:700;margin:0 0 2px;">{{ __('Register a Company') }}</h1>
    <p style="color:#64748b;margin:0 0 18px;font-size:13px;">{{ __('Set up a new company workspace. You can add employees, attendance and payroll once it is created.') }}</p>

    @if ($errors->any())
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:10px 14px;border-radius:8px;margin-bottom:14px;">
            <ul style="margin:0;padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('hr.companies.store') }}" style="display:grid;gap:14px;background:#fff;border:1px solid #e8ecf2;border-radius:12px;padding:20px;">
        @csrf
        @php $label='display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:5px;'; $input='width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;'; @endphp

        <div>
            <label style="{{ $label }}">{{ __('Company name') }} <span style="color:#dc2626;">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required style="{{ $input }}" placeholder="{{ __('e.g. TNR International') }}">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <label style="{{ $label }}">{{ __('Industry') }}</label>
                <input type="text" name="industry" value="{{ old('industry') }}" style="{{ $input }}" placeholder="{{ __('e.g. Manufacturing') }}">
            </div>
            <div>
                <label style="{{ $label }}">{{ __('Timezone') }}</label>
                <input type="text" name="timezone" value="{{ old('timezone', 'Asia/Kolkata') }}" style="{{ $input }}">
            </div>
        </div>
        <div>
            <label style="{{ $label }}">{{ __('Address') }}</label>
            <input type="text" name="address" value="{{ old('address') }}" style="{{ $input }}">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div><label style="{{ $label }}">{{ __('City') }}</label><input type="text" name="city" value="{{ old('city') }}" style="{{ $input }}"></div>
            <div><label style="{{ $label }}">{{ __('State') }}</label><input type="text" name="state" value="{{ old('state') }}" style="{{ $input }}"></div>
            <div><label style="{{ $label }}">{{ __('Country') }}</label><input type="text" name="country" value="{{ old('country') }}" style="{{ $input }}"></div>
        </div>

        <div style="display:flex;gap:10px;margin-top:4px;">
            <button type="submit" style="background:#10b981;color:#fff;padding:10px 18px;border:none;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;">{{ __('Create Company') }}</button>
            <a href="{{ route('hr.companies.index') }}" style="padding:10px 18px;border:1px solid #cbd5e1;border-radius:8px;font-weight:600;font-size:14px;color:#334155;text-decoration:none;">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
@endsection
