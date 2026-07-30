@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<div class="corp-page">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-tags"></i> {{ __('Pricing Enquiries') }}</h4>
            <p>{{ __('Leads submitted from the Pricing & Plans section on your website.') }}</p>
        </div>
    </div>

    @if(session('messege'))
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13.5px;">{{ session('messege') }}</div>
    @endif

    {{-- 2026-07-14 — payment is collected automatically when a visitor books a
         PRICED plan (coach gateway if configured, else the platform default).
         Free / "Contact us" / unpriced plans stay lead-only. --}}
    <div class="corp-form-card" style="margin-bottom:14px;">
        <div class="corp-form-card__body" style="display:flex;align-items:center;gap:10px;">
            <i class="fas fa-credit-card" style="color:#0f766e;"></i>
            <div style="color:#475569;font-size:12.5px;">
                {{ __('Booking a priced plan sends the visitor to your payment gateway (your gateway if configured, else the platform default). Free / “Contact us” plans stay lead-only.') }}
            </div>
        </div>
    </div>

    @php $fsel = 'border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:13.5px;'; @endphp
    <form method="GET" style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('Search name / email / mobile / class / trainer…') }}"
               style="flex:1;min-width:210px;border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:14px;">
        <select name="status" style="{{ $fsel }}">
            <option value="">{{ __('All status') }}</option>
            @foreach(['new'=>'New','contacted'=>'Contacted','converted'=>'Converted','closed'=>'Closed'] as $k=>$v)
                <option value="{{ $k }}" {{ ($status ?? '')===$k ? 'selected' : '' }}>{{ __($v) }}</option>
            @endforeach
        </select>
        <select name="payment" style="{{ $fsel }}">
            <option value="">{{ __('All payments') }}</option>
            @foreach(['paid'=>'Paid','pending'=>'Pending','unpaid'=>'Unpaid','failed'=>'Failed','cancelled'=>'Cancelled'] as $k=>$v)
                <option value="{{ $k }}" {{ ($payment ?? '')===$k ? 'selected' : '' }}>{{ __($v) }}</option>
            @endforeach
        </select>
        @if(($classOpts ?? collect())->isNotEmpty())
        <select name="class" style="{{ $fsel }}">
            <option value="">{{ __('All classes') }}</option>
            @foreach($classOpts as $o)<option value="{{ $o }}" {{ ($class ?? '')===$o ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($o, 28) }}</option>@endforeach
        </select>
        @endif
        @if(($trainerOpts ?? collect())->isNotEmpty())
        <select name="trainer" style="{{ $fsel }}">
            <option value="">{{ __('All trainers') }}</option>
            @foreach($trainerOpts as $o)<option value="{{ $o }}" {{ ($trainer ?? '')===$o ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($o, 24) }}</option>@endforeach
        </select>
        @endif
        @if(($planOpts ?? collect())->isNotEmpty())
        <select name="plan" style="{{ $fsel }}">
            <option value="">{{ __('All plan types') }}</option>
            @foreach($planOpts as $o)<option value="{{ $o }}" {{ ($plan ?? '')===$o ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($o, 24) }}</option>@endforeach
        </select>
        @endif
        @if(($courseOpts ?? collect())->isNotEmpty())
        <select name="course" style="{{ $fsel }}">
            <option value="">{{ __('All course types') }}</option>
            @foreach($courseOpts as $o)<option value="{{ $o }}" {{ ($course ?? '')===$o ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($o, 24) }}</option>@endforeach
        </select>
        @endif
        <input type="date" name="from" value="{{ $from ?? '' }}" title="{{ __('From date') }}" style="{{ $fsel }}">
        <input type="date" name="to" value="{{ $to ?? '' }}" title="{{ __('To date') }}" style="{{ $fsel }}">
        <button class="btn-corp-light"><i class="fas fa-search"></i></button>
        @if(($search??'')!==''||($status??'')!==''||($payment??'')!==''||($class??'')!==''||($trainer??'')!==''||($plan??'')!==''||($course??'')!==''||($from??'')!==''||($to??'')!=='')
            <a href="{{ route('instructor.pricing-enquiries.index') }}" class="btn-corp-light" title="{{ __('Clear') }}"><i class="fas fa-times"></i></a>
        @endif
    </form>

    @if ($enquiries->count())
        <div class="corp-form-card">
            <div class="corp-form-card__body" style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;color:#64748b;border-bottom:1px solid #eef0f5;">
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Contact') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Plan') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Amount') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Payment') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Time slot') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Enquiry date') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Status') }}</th>
                            <th style="padding:10px 12px;font-weight:600;text-align:right;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($enquiries as $e)
                            <tr style="border-bottom:1px solid #f1f5f9;vertical-align:top;">
                                <td style="padding:11px 12px;">
                                    <div style="font-weight:600;color:#1e293b;">{{ $e->name }}</div>
                                    <div style="color:#64748b;">{{ $e->email }}</div>
                                    <div style="color:#64748b;">{{ $e->mobile }}</div>
                                    @php $d = $e->details ?? []; @endphp
                                    @if(!empty($d['person2']['name']))<div style="color:#94a3b8;font-size:12px;">+ {{ $d['person2']['name'] }} ({{ $d['person2']['gender'] ?? '' }})</div>@endif
                                </td>
                                <td style="padding:11px 12px;color:#475569;">
                                    <div><b>{{ $e->category }}</b></div>
                                    @if($e->course_type || $e->time_period)<div>{{ ucfirst($e->course_type) }}{{ $e->course_type && $e->time_period ? ' · ' : '' }}{{ $e->time_period }}</div>@endif
                                    @if($e->price)<div style="color:#0f766e;font-weight:600;">₹{{ $e->price }}</div>@endif
                                    @if($e->reason)<div style="color:#94a3b8;font-size:12px;">{{ $e->reason }}</div>@endif
                                    {{-- 2026-07-14 — Class Schedule booking: class + trainer (slot is the Time slot column). --}}
                                    @if(!empty($d['class_name']) || $e->trainer_id)
                                        <div style="color:#334155;font-size:12px;margin-top:2px;">
                                            @if(!empty($d['class_name']))<i class="fas fa-calendar-week" style="color:#94a3b8;"></i> {{ $d['class_name'] }}@endif
                                            @if($e->trainer_id)<span style="color:#94a3b8;"> · {{ $e->trainer_id }}</span>@endif
                                        </div>
                                    @endif
                                    {{-- Booking-enquiry context (reusable modal): where the lead came from + message. --}}
                                    @if($e->source_button)<div style="margin-top:3px;"><span style="display:inline-block;background:#ecfdf5;color:#065f46;font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;">{{ __('via') }} {{ $e->source_button }}</span></div>@endif
                                    @php $bd = $e->details ?? []; @endphp
                                    @if(!empty($bd['message']))<div style="color:#64748b;font-size:12px;margin-top:3px;">"{{ \Illuminate\Support\Str::limit($bd['message'], 120) }}"</div>@endif
                                </td>
                                @php
                                    $cur   = $e->currency ?: 'INR';
                                    $sym   = $cur === 'INR' ? '₹' : ($cur . ' ');
                                    $pay   = $e->effectivePayment();
                                    $ps    = $e->payment_status ?: 'unpaid';
                                    $psMap = [
                                        'paid'      => ['#065f46', '#ecfdf5', __('Paid')],
                                        'pending'   => ['#92400e', '#fffbeb', __('Pending')],
                                        'failed'    => ['#991b1b', '#fef2f2', __('Failed')],
                                        'cancelled' => ['#475569', '#f1f5f9', __('Cancelled')],
                                        'unpaid'    => ['#475569', '#f8fafc', __('Unpaid')],
                                    ];
                                    $psc = $psMap[$ps] ?? $psMap['unpaid'];
                                @endphp
                                <td style="padding:11px 12px;color:#475569;white-space:nowrap;">
                                    @if(!is_null($e->plan_amount))
                                        <div style="font-weight:700;color:#0f172a;">{{ $sym }}{{ number_format((float) $e->plan_amount, 2) }}</div>
                                    @elseif($e->price)
                                        <div style="font-weight:700;color:#0f172a;">₹{{ $e->price }}</div>
                                    @else
                                        <div style="color:#94a3b8;">—</div>
                                    @endif
                                    @if(!is_null($e->paid_amount) && (float) $e->paid_amount > 0)
                                        <div style="color:#16a34a;font-size:12px;">{{ __('Paid') }}: {{ $sym }}{{ number_format((float) $e->paid_amount, 2) }}</div>
                                    @endif
                                </td>
                                <td style="padding:11px 12px;color:#475569;">
                                    <span style="display:inline-block;background:{{ $psc[1] }};color:{{ $psc[0] }};font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;">{{ $psc[2] }}</span>
                                    @if($pay)
                                        <div style="color:#64748b;font-size:12px;margin-top:3px;">{{ ucfirst($pay->gateway) }}</div>
                                        @if($pay->transaction_id)<div style="color:#94a3b8;font-size:11px;font-family:monospace;">{{ $pay->transaction_id }}</div>@endif
                                        @if($pay->paid_at)<div style="color:#94a3b8;font-size:11px;">{{ $pay->paid_at->format('d M Y, h:i A') }}</div>@endif
                                    @endif
                                </td>
                                <td style="padding:11px 12px;color:#475569;">{{ $e->time_slot }}</td>
                                <td style="padding:11px 12px;color:#64748b;white-space:nowrap;">{{ $e->created_at?->format('d M Y, h:i A') }}</td>
                                <td style="padding:11px 12px;">
                                    <form action="{{ route('instructor.pricing-enquiries.status', $e->id) }}" method="POST">
                                        @csrf @method('PUT')
                                        <select name="status" onchange="this.form.submit()" style="border:1px solid #e2e8f0;border-radius:8px;padding:5px 8px;font-size:12px;">
                                            @foreach(['new'=>'New','contacted'=>'Contacted','converted'=>'Converted','closed'=>'Closed'] as $k=>$v)
                                                <option value="{{ $k }}" {{ $e->status===$k ? 'selected' : '' }}>{{ __($v) }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td style="padding:11px 12px;text-align:right;white-space:nowrap;">
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/','',$e->mobile) }}" target="_blank" rel="noopener" class="btn-corp-light" style="padding:6px 9px;color:#16a34a;" title="{{ __('WhatsApp') }}"><i class="fab fa-whatsapp"></i></a>
                                    <a href="mailto:{{ $e->email }}" class="btn-corp-light" style="padding:6px 9px;" title="{{ __('Email') }}"><i class="fas fa-envelope"></i></a>
                                    <form action="{{ route('instructor.pricing-enquiries.destroy', $e->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('{{ __('Delete this enquiry?') }}');">
                                        @csrf @method('DELETE')
                                        <button class="btn-corp-light" style="padding:6px 9px;color:#dc2626;"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div style="margin-top:16px;">{{ $enquiries->links() }}</div>
    @else
        <div class="corp-form-card">
            <div class="corp-form-card__body" style="text-align:center;padding:40px 20px;color:#94a3b8;">
                <i class="fas fa-inbox" style="font-size:32px;margin-bottom:10px;"></i>
                <p style="margin:0;">{{ __('No enquiries yet. They will appear here when visitors book from your Pricing & Plans section.') }}</p>
            </div>
        </div>
    @endif
</div>
@endsection
