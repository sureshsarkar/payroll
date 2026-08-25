@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@php
    $label = 'display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:5px;';
    $input = 'width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;';
@endphp
<div style="max-width:720px;margin:0 auto;padding:8px 4px;">
    <h1 style="font-size:20px;font-weight:700;margin:0 0 2px;">{{ __('Company Settings') }}</h1>
    <p style="color:#64748b;margin:0 0 18px;font-size:13px;">{{ __('This is the letterhead printed on pay slips and statutory wage registers.') }}</p>

    @if (session('success'))
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;padding:10px 14px;border-radius:8px;margin-bottom:14px;">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:10px 14px;border-radius:8px;margin-bottom:14px;">
            <ul style="margin:0;padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('hr.companies.update', $company) }}" enctype="multipart/form-data"
          style="display:grid;gap:14px;background:#fff;border:1px solid #e8ecf2;border-radius:12px;padding:20px;">
        @csrf @method('PUT')

        <div>
            <label style="{{ $label }}">{{ __('Company name') }} <span style="color:#dc2626;">*</span></label>
            <input type="text" name="name" value="{{ old('name', $company->name) }}" required style="{{ $input }}">
        </div>

        <div>
            <label style="{{ $label }}">{{ __('Logo') }}</label>
            <div style="display:flex;align-items:center;gap:12px;">
                @if ($company->logo_path)
                    <img src="{{ asset($company->logo_path) }}" alt="" style="height:44px;border:1px solid #e2e8f0;border-radius:6px;padding:3px;background:#fff;">
                @endif
                <input type="file" name="logo" accept="image/png,image/jpeg" style="{{ $input }}">
            </div>
            <p style="color:#94a3b8;font-size:11px;margin:5px 0 0;">{{ __('PNG or JPG, up to 1 MB. Printed at the top-left of the pay slip.') }}</p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div><label style="{{ $label }}">{{ __('Industry') }}</label><input type="text" name="industry" value="{{ old('industry', $company->industry) }}" style="{{ $input }}"></div>
            <div><label style="{{ $label }}">{{ __('Timezone') }}</label><input type="text" name="timezone" value="{{ old('timezone', $company->timezone) }}" style="{{ $input }}"></div>
        </div>

        <div>
            <label style="{{ $label }}">{{ __('Address') }}</label>
            <input type="text" name="address" value="{{ old('address', $company->address) }}" style="{{ $input }}" placeholder="{{ __('e.g. E-47/6, Okhla Ind. Area, Ph-II') }}">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;">
            <div><label style="{{ $label }}">{{ __('City') }}</label><input type="text" name="city" value="{{ old('city', $company->city) }}" style="{{ $input }}"></div>
            <div><label style="{{ $label }}">{{ __('State') }}</label><input type="text" name="state" value="{{ old('state', $company->state) }}" style="{{ $input }}"></div>
            <div><label style="{{ $label }}">{{ __('PIN') }}</label><input type="text" name="postal_code" value="{{ old('postal_code', $company->postal_code) }}" style="{{ $input }}"></div>
            <div><label style="{{ $label }}">{{ __('Country') }}</label><input type="text" name="country" value="{{ old('country', $company->country) }}" style="{{ $input }}"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div><label style="{{ $label }}">{{ __('Establishment PF code') }}</label><input type="text" name="pf_number" value="{{ old('pf_number', $company->pf_number) }}" style="{{ $input }}"></div>
            <div><label style="{{ $label }}">{{ __('Establishment ESI code') }}</label><input type="text" name="esi_number" value="{{ old('esi_number', $company->esi_number) }}" style="{{ $input }}"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div><label style="{{ $label }}">{{ __('Contact phone') }}</label><input type="text" name="phone" value="{{ old('phone', $company->phone) }}" style="{{ $input }}" placeholder="{{ __('Shown on payslips, if set') }}"></div>
            <div><label style="{{ $label }}">{{ __('Contact email') }}</label><input type="email" name="email" value="{{ old('email', $company->email) }}" style="{{ $input }}" placeholder="{{ __('Shown on payslips, if set') }}"></div>
        </div>

        <div style="display:flex;gap:10px;margin-top:4px;">
            <button type="submit" style="background:#10b981;color:#fff;padding:10px 18px;border:none;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;">{{ __('Save settings') }}</button>
            <a href="{{ route('hr.companies.index') }}" style="padding:10px 18px;border:1px solid #cbd5e1;border-radius:8px;font-weight:600;font-size:14px;color:#334155;text-decoration:none;">{{ __('Back') }}</a>
        </div>
    </form>
</div>
@endsection
