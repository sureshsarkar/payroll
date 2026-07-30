{{-- Coach-scoped student dashboard. Only shows data for this coach. --}}
@extends('frontend.coach-site.layouts.master')

@section('content')
<section class="cs-pad">
    <div class="cs-container" style="max-width:1100px;">
        <h1 class="cs-h2" style="margin-bottom:6px;">{{ __('My dashboard') }}</h1>
        <p style="color:var(--brand-muted);margin-bottom:32px;">
            {{ __('Your activity at') }} <strong>{{ $brand?->name ?? config('app.name') }}</strong>.
        </p>

        {{-- Stats --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-bottom:32px;">
            <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:22px 24px;">
                <div style="color:#6B7280;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">{{ __('Enrolled courses') }}</div>
                <div style="font-size:32px;font-weight:800;color:var(--brand-primary);">{{ $stats['totalEnrolledCourses'] }}</div>
            </div>
            <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:22px 24px;">
                <div style="color:#6B7280;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">{{ __('Total orders') }}</div>
                <div style="font-size:32px;font-weight:800;color:var(--brand-primary);">{{ $stats['totalOrders'] }}</div>
            </div>
        </div>

        {{-- Resume learning --}}
        @if($resume && $resume->course)
            <div style="background:linear-gradient(135deg,var(--brand-primary) 0%,var(--brand-accent,#8B5CF6) 100%);color:#fff;border-radius:14px;padding:24px 28px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:18px;">
                <div>
                    <div style="font-size:13px;opacity:0.85;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">{{ __('Resume learning') }}</div>
                    <h3 style="margin:0 0 4px;font-size:18px;color:#fff;">{{ $resume->course->title }}</h3>
                </div>
                <a href="{{ url('/student/learning/' . $resume->course->slug) }}" class="cs-btn" style="background:#fff;color:var(--brand-primary);text-decoration:none;">
                    <i class="fa-solid fa-play"></i> {{ __('Continue') }}
                </a>
            </div>
        @endif

        {{-- Quick links --}}
        <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:32px;">
            <a href="{{ route('coach.student.courses', ['coachSlug' => $coachSlug]) }}" class="cs-btn cs-btn--primary">
                <i class="fa-solid fa-graduation-cap"></i> {{ __('My courses') }}
            </a>
            <a href="{{ route('coach.student.orders', ['coachSlug' => $coachSlug]) }}" class="cs-btn" style="background:#F3F4F6;color:#111827;text-decoration:none;">
                <i class="fa-solid fa-file-invoice"></i> {{ __('My orders') }}
            </a>
            <a href="{{ route('coach.site.path', ['site_slug' => $coachSlug]) }}" class="cs-btn" style="background:#F3F4F6;color:#111827;text-decoration:none;">
                <i class="fa-solid fa-house"></i> {{ __('Coach home') }}
            </a>
        </div>

        {{-- Recent orders --}}
        @if($orders->count() > 0)
            <h3 style="font-size:16px;color:#111827;margin-bottom:14px;">{{ __('Recent orders') }}</h3>
            <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead style="background:#F9FAFB;">
                        <tr>
                            <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;font-size:12px;text-transform:uppercase;">{{ __('Invoice') }}</th>
                            <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;font-size:12px;text-transform:uppercase;">{{ __('Amount') }}</th>
                            <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;font-size:12px;text-transform:uppercase;">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $o)
                            <tr style="border-top:1px solid #F3F4F6;">
                                <td style="padding:14px 16px;color:#374151;">#{{ $o->invoice_id }}</td>
                                <td style="padding:14px 16px;color:#111827;font-weight:600;">{{ currency($o->display_amount ?? $o->paid_amount) }}</td>
                                <td style="padding:14px 16px;">
                                    @php $isPaid = $o->payment_status === 'paid'; @endphp
                                    <span style="padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;{{ $isPaid ? 'background:#DCFCE7;color:#15803D' : 'background:#FEF3C7;color:#92400E' }};">
                                        {{ ucfirst($o->payment_status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@endsection
