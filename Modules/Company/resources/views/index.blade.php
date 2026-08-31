@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div style="max-width:860px;margin:0 auto;padding:8px 4px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin:0;">{{ __('My Companies') }}</h1>
            <p style="color:#64748b;margin:2px 0 0;font-size:13px;">{{ __('Switch the active company or register a new one.') }}</p>
        </div>
        <a href="{{ route('hr.companies.create') }}" style="background:#10b981;color:#fff;padding:9px 14px;border-radius:8px;font-weight:600;font-size:13px;text-decoration:none;">
            + {{ __('Register New Company') }}
        </a>
    </div>

    @if (session('success')) <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:10px 14px;border-radius:8px;margin-bottom:12px;">{{ session('success') }}</div> @endif
    @if (session('info')) <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:10px 14px;border-radius:8px;margin-bottom:12px;">{{ session('info') }}</div> @endif

    <div style="display:grid;gap:10px;">
        @forelse ($companies as $company)
            <div style="border:1px solid {{ $company->id === $activeId ? '#10b981' : '#e8ecf2' }};border-radius:11px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;background:{{ $company->id === $activeId ? '#ecfdf5' : '#fff' }};">
                <div>
                    <div style="font-weight:700;font-size:15px;">{{ $company->name }}
                        @if ($company->id === $activeId)
                            <span style="font-size:10px;background:#10b981;color:#fff;padding:2px 7px;border-radius:20px;margin-left:6px;vertical-align:middle;">{{ __('ACTIVE') }}</span>
                        @endif
                    </div>
                    <div style="color:#94a3b8;font-size:12px;margin-top:2px;">{{ $company->industry ?: __('No industry set') }} · {{ $company->timezone }}</div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <a href="{{ route('hr.companies.edit', $company) }}" style="border:1px solid #cbd5e1;background:#fff;padding:7px 13px;border-radius:8px;font-weight:600;font-size:13px;color:#334155;text-decoration:none;">{{ __('Settings') }}</a>
                    @if ($company->id !== $activeId)
                        <form method="POST" action="{{ route('hr.companies.switch', $company) }}">@csrf
                            <button type="submit" style="border:1px solid #cbd5e1;background:#fff;padding:7px 13px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;">{{ __('Switch') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div style="border:1px dashed #cbd5e1;border-radius:11px;padding:28px;text-align:center;color:#64748b;">
                {{ __('You have no companies yet.') }}
                <a href="{{ route('hr.companies.create') }}" style="color:#10b981;font-weight:600;">{{ __('Register your first company') }}</a>.
            </div>
        @endforelse
    </div>
</div>
@endsection
