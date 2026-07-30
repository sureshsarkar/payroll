{{-- Coach-scoped order history. Filtered to orders containing courses
     owned by this coach (via orderItems.course.instructor_id). --}}
@extends('frontend.coach-site.layouts.master')

@section('content')
<section class="cs-pad">
    <div class="cs-container" style="max-width:1100px;">
        <h1 class="cs-h2" style="margin-bottom:6px;">{{ __('My orders') }}</h1>
        <p style="color:var(--brand-muted);margin-bottom:28px;">
            {{ __('Your purchase history with') }} <strong>{{ $brand?->name ?? config('app.name') }}</strong>.
        </p>

        @if($orders->isEmpty())
            <div style="text-align:center;padding:60px 20px;background:#F9FAFB;border-radius:12px;">
                <i class="fa-solid fa-file-invoice" style="font-size:48px;color:#D1D5DB;margin-bottom:18px;display:block;"></i>
                <h3 style="margin:0 0 8px;font-size:18px;color:#111827;">{{ __('No orders yet.') }}</h3>
                <a href="{{ route('coach.site.path', ['site_slug' => $coachSlug]) }}" class="cs-btn cs-btn--primary" style="margin-top:14px;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Browse courses') }}
                </a>
            </div>
        @else
            <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead style="background:#F9FAFB;">
                        <tr>
                            <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;font-size:12px;text-transform:uppercase;">{{ __('Invoice') }}</th>
                            <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;font-size:12px;text-transform:uppercase;">{{ __('Courses') }}</th>
                            <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;font-size:12px;text-transform:uppercase;">{{ __('Amount') }}</th>
                            <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;font-size:12px;text-transform:uppercase;">{{ __('Payment') }}</th>
                            <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;font-size:12px;text-transform:uppercase;">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $o)
                            <tr style="border-top:1px solid #F3F4F6;">
                                <td style="padding:14px 16px;color:#374151;font-weight:600;">#{{ $o->invoice_id }}</td>
                                <td style="padding:14px 16px;color:#6B7280;">
                                    @php
                                        $titles = $o->orderItems->map(fn($i) => $i->course?->title)->filter()->take(2)->all();
                                    @endphp
                                    {{ implode(', ', $titles) }}
                                    @if($o->orderItems->count() > 2)
                                        <small style="display:block;color:#9CA3AF;">+{{ $o->orderItems->count() - 2 }} {{ __('more') }}</small>
                                    @endif
                                </td>
                                <td style="padding:14px 16px;color:#111827;font-weight:600;">
                                    {{ currency($o->display_amount ?? $o->paid_amount) }}
                                    @if(!empty($o->is_multi_coach))
                                        <small style="display:block;color:#9CA3AF;font-weight:400;">{{ __('Your items only') }}</small>
                                    @endif
                                </td>
                                <td style="padding:14px 16px;">
                                    @php $isPaid = $o->payment_status === 'paid'; @endphp
                                    <span style="padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;{{ $isPaid ? 'background:#DCFCE7;color:#15803D' : 'background:#FEF3C7;color:#92400E' }};">
                                        {{ ucfirst($o->payment_status) }}
                                    </span>
                                </td>
                                <td style="padding:14px 16px;color:#9CA3AF;font-size:13px;">{{ $o->created_at?->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:26px;">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
