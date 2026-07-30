{{-- Coach-scoped checkout. 2026-06-16 — premium white-label, matches the
     approved mockup: radio-select payment cards + a single "Pay" button + GST.
     The pay action keeps the EXACT checkout.js hook: hidden .place-order-btn
     triggers (one per gateway, fixed data-method) are clicked by the Pay button,
     so the platform POST-to-/place-order flow is unchanged. --}}
@extends('frontend.coach-site.layouts.master')

@section('content')
@php
    $u = auth('web')->user();
    $initials = $u ? strtoupper(\Illuminate\Support\Str::of($u->name ?? 'U')->explode(' ')->take(2)->map(fn ($p) => substr($p, 0, 1))->implode('')) : 'U';
    $subtotal = 0;
    foreach ($products as $it) {
        $cc = $it->course ?? null;
        $subtotal += $cc ? ((($cc->discount ?? 0) > 0) ? $cc->discount : $cc->price) : 0;
    }
    $tax = $tax ?? ['has_tax' => false, 'tax_amount' => 0, 'rate' => 0, 'label' => __('GST')];
    $hasGateways = ($payable_amount > 0) && ! empty($activeGateways);
@endphp

<style>
    .pco{ --acc:var(--brand-primary,#4f46e5); --acc-soft:color-mix(in srgb, var(--brand-primary,#4f46e5) 12%, #fff); }
    .pco-head{ display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:22px; flex-wrap:wrap; }
    .pco-head__l{ display:flex; align-items:center; gap:11px; }
    .pco-chip{ width:40px; height:40px; border-radius:11px; background:var(--acc-soft); color:var(--acc); display:flex; align-items:center; justify-content:center; font-size:18px; flex:0 0 auto; }
    .pco-title{ margin:0; font-size:22px; font-weight:800; letter-spacing:-.02em; color:#0f172a; }
    .pco-sub{ margin:2px 0 0; font-size:13px; color:var(--brand-muted,#64748b); }
    .pco-back{ font-size:13.5px; color:var(--acc); text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .pco-grid{ display:grid; grid-template-columns:minmax(0,1.55fr) minmax(0,1fr); gap:20px; align-items:start; }
    @media (max-width:768px){ .pco-grid{ grid-template-columns:1fr; } }
    .pco-card{ background:#fff; border:1px solid #eef0f5; border-radius:16px; padding:18px 20px; margin-bottom:16px; }
    .pco-card h3{ margin:0 0 12px; font-size:15px; font-weight:700; color:#0f172a; }
    .pco-bill{ display:flex; align-items:center; gap:12px; }
    .pco-avatar{ width:44px; height:44px; border-radius:50%; background:var(--acc-soft); color:var(--acc); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; flex:0 0 auto; }
    .pco-pays{ display:flex; flex-direction:column; gap:10px; }
    .pco-pay{ display:flex; align-items:center; gap:12px; padding:13px 15px; border:1.5px solid #e8eaf0; border-radius:14px; background:#fff; cursor:pointer; transition:border-color .15s, box-shadow .15s; }
    .pco-pay:hover{ border-color:var(--acc); }
    .pco-pay.sel{ border-color:var(--acc); box-shadow:0 0 0 3px color-mix(in srgb, var(--brand-primary,#4f46e5) 16%, transparent); }
    .pco-pay input{ position:absolute; opacity:0; pointer-events:none; }
    .pco-radio{ width:20px; height:20px; border-radius:50%; border:2px solid #cbd5e1; flex:0 0 auto; display:flex; align-items:center; justify-content:center; color:#fff; font-size:10px; transition:.15s; }
    .pco-radio i{ display:none; }
    .pco-pay.sel .pco-radio{ border-color:var(--acc); background:var(--acc); }
    .pco-pay.sel .pco-radio i{ display:inline; }
    .pco-pay__ico{ width:38px; height:38px; border-radius:9px; background:var(--acc-soft); color:var(--acc); display:flex; align-items:center; justify-content:center; font-size:16px; flex:0 0 auto; overflow:hidden; }
    .pco-pay__ico img{ max-width:100%; max-height:30px; object-fit:contain; }
    .pco-pay__name{ font-size:14px; font-weight:600; color:#0f172a; }
    .pco-pay__sub{ font-size:12px; color:var(--brand-muted,#64748b); }
    .pco-summary{ background:#fff; border:1.5px solid #e8eaf0; border-radius:16px; padding:18px 20px; position:sticky; top:90px; }
    .pco-line{ display:flex; gap:10px; padding:8px 0; align-items:center; border-bottom:1px dashed #f1f5f9; }
    .pco-line:last-of-type{ border-bottom:0; }
    .pco-srow{ display:flex; justify-content:space-between; font-size:13.5px; padding:7px 0; }
    .pco-total{ display:flex; align-items:baseline; justify-content:space-between; border-top:1px solid #f1f5f9; margin-top:6px; padding-top:12px; }
    .pco-pay-btn{ width:100%; margin-top:14px; border:0; background:var(--acc); color:#fff; font-weight:700; font-size:15px; padding:14px; border-radius:14px; display:inline-flex; align-items:center; justify-content:center; gap:8px; cursor:pointer; transition:filter .15s, transform .15s; }
    .pco-pay-btn:hover{ filter:brightness(.95); transform:translateY(-1px); }
    .pco-secure{ margin:12px 0 0; font-size:12px; color:var(--brand-muted,#64748b); display:flex; align-items:center; justify-content:center; gap:6px; }
</style>

<section class="cs-pad">
    <div class="cs-container pco" style="max-width:1040px;">

        <div class="pco-head">
            <div class="pco-head__l">
                <span class="pco-chip"><i class="fa-solid fa-lock"></i></span>
                <div>
                    <h1 class="pco-title">{{ __('Checkout') }}</h1>
                    <p class="pco-sub">{{ __('Secure payment') }} · {{ $brand?->name ?? config('app.name') }}</p>
                </div>
            </div>
            <a href="{{ coachCommerceUrl('cart', $coachSlug) }}" class="pco-back"><i class="fa-solid fa-arrow-left"></i> {{ __('Back to cart') }}</a>
        </div>

        <div class="pco-grid">

            <div>
                @if($u)
                    <div class="pco-card">
                        <h3>{{ __('Billing details') }}</h3>
                        <div class="pco-bill">
                            <span class="pco-avatar">{{ $initials }}</span>
                            <div>
                                <p style="margin:0;font-size:14px;font-weight:700;color:#0f172a;">{{ $u->name }}</p>
                                <p style="margin:1px 0 0;font-size:12.5px;color:var(--brand-muted,#64748b);">{{ $u->email }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="pco-card">
                    <h3>{{ __('Payment method') }}</h3>

                    @if($hasGateways)
                        <div class="pco-pays">
                            @foreach($activeGateways as $gatewayKey => $gatewayDetails)
                                <label class="pco-pay {{ $loop->first ? 'sel' : '' }}">
                                    <input type="radio" name="pco_gateway" value="{{ $gatewayKey }}" {{ $loop->first ? 'checked' : '' }}>
                                    <span class="pco-radio"><i class="fa-solid fa-check"></i></span>
                                    <span class="pco-pay__ico">
                                        @if(!empty($gatewayDetails['logo']))
                                            <img src="{{ $gatewayDetails['logo'] }}" alt="{{ $gatewayDetails['name'] ?? $gatewayKey }}">
                                        @else
                                            <i class="fa-solid fa-credit-card"></i>
                                        @endif
                                    </span>
                                    <span style="flex:1;min-width:0;">
                                        <span class="pco-pay__name" style="display:block;">{{ $gatewayDetails['name'] ?? $gatewayKey }}</span>
                                        <span class="pco-pay__sub">{{ __('Secure online payment') }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        {{-- Hidden per-gateway triggers — checkout.js binds .place-order-btn
                             (event delegation) + reads data-method. The Pay button clicks
                             the selected one, so the POST flow is byte-for-byte unchanged. --}}
                        <div style="display:none;" aria-hidden="true">
                            @foreach($activeGateways as $gatewayKey => $gatewayDetails)
                                <a class="place-order-btn" data-method="{{ $gatewayKey }}"></a>
                            @endforeach
                        </div>
                    @elseif($payable_amount <= 0)
                        <p style="color:var(--brand-muted,#64748b);margin:0;font-size:13.5px;">{{ __('This is a free enrolment — no payment needed.') }}</p>
                    @else
                        <p style="color:var(--brand-muted,#64748b);margin:0;">
                            {{ __('No payment gateway is currently active. Please contact your coach for support.') }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="pco-summary">
                <h3 style="margin:0 0 10px;font-size:15px;font-weight:700;color:#0f172a;">{{ __('Order summary') }}</h3>

                @foreach($products as $item)
                    @php $course = $item->course ?? null; $price = $course ? ((($course->discount ?? 0) > 0) ? $course->discount : $course->price) : 0; @endphp
                    <div class="pco-line">
                        @if($course && $course->thumbnail)
                            <img src="{{ str_starts_with($course->thumbnail, 'http') ? $course->thumbnail : asset($course->thumbnail) }}" alt="" style="width:44px;height:32px;object-fit:cover;border-radius:6px;flex:0 0 auto;">
                        @endif
                        <span style="flex:1;min-width:0;font-size:13px;color:#0f172a;line-height:1.35;">{{ $course?->title ?? __('Course') }}</span>
                        <span style="font-weight:600;font-size:13px;white-space:nowrap;">{{ currency($price) }}</span>
                    </div>
                @endforeach

                <div class="pco-srow" style="margin-top:6px;"><span style="color:var(--brand-muted,#64748b);">{{ __('Subtotal') }}</span><span style="font-weight:600;">{{ currency($subtotal) }}</span></div>
                @if(($discountAmount ?? 0) > 0)
                    <div class="pco-srow"><span style="color:#16a34a;"><i class="fa-solid fa-tag"></i> {{ __('Coupon') }} ({{ $coupon }}, {{ (int) $discountPercent }}%)</span><span style="color:#16a34a;font-weight:600;">&minus;{{ currency($discountAmount) }}</span></div>
                @endif
                @if(!empty($tax['has_tax']) && ($tax['tax_amount'] ?? 0) > 0)
                    <div class="pco-srow"><span style="color:var(--brand-muted,#64748b);">{{ $tax['label'] ?: __('GST') }} ({{ rtrim(rtrim(number_format((float) $tax['rate'], 2), '0'), '.') }}%)</span><span style="font-weight:600;">{{ currency($tax['tax_amount']) }}</span></div>
                @endif
                <div class="pco-total"><span style="font-size:14px;font-weight:600;color:#0f172a;">{{ __('Total payable') }}</span><span style="font-size:22px;font-weight:800;color:#0f172a;">{{ $total }}</span></div>

                @if($hasGateways)
                    <button type="button" id="pcoPayNow" class="pco-pay-btn"><i class="fa-solid fa-lock"></i> {{ __('Pay') }} {{ $total }} {{ __('securely') }}</button>
                @elseif($payable_amount <= 0)
                    <form action="{{ route('pay-via-free-gateway') }}" method="POST" style="margin:14px 0 0;">
                        @csrf
                        <button type="submit" class="pco-pay-btn"><i class="fa-solid fa-check"></i> {{ __('Complete free enrolment') }}</button>
                    </form>
                @endif

                <p class="pco-secure"><i class="fa-solid fa-shield-halved"></i> {{ __('256-bit encrypted · Secure checkout') }}</p>
            </div>

        </div>

        {{-- checkout.js owns the gateway-pick → POST /place-order flow (delegated
             on .place-order-btn). Load jQuery + toastr + base_url before it. --}}
        <link rel="stylesheet" href="{{ asset('global/toastr/toastr.min.css') }}">
        <script src="{{ asset('global/js/jquery-3.7.1.min.js') }}"></script>
        <script src="{{ asset('global/toastr/toastr.min.js') }}"></script>
        <script nonce="{{ csp_nonce() }}">var base_url = @json(url('/'));</script>
        <script src="{{ asset('frontend/js/default/checkout.js') }}"></script>
        <script nonce="{{ csp_nonce() }}">
        (function () {
            function highlight() {
                document.querySelectorAll('.pco-pay').forEach(function (p) {
                    var i = p.querySelector('input'); p.classList.toggle('sel', !!(i && i.checked));
                });
            }
            document.querySelectorAll('.pco-pay input[name="pco_gateway"]').forEach(function (r) {
                r.addEventListener('change', highlight);
            });
            var pay = document.getElementById('pcoPayNow');
            if (pay) {
                pay.addEventListener('click', function () {
                    var sel = document.querySelector('.pco-pay input[name="pco_gateway"]:checked');
                    if (!sel) { if (window.toastr) { toastr.error('Please select a payment method'); } return; }
                    var trigger = document.querySelector('.place-order-btn[data-method="' + sel.value + '"]');
                    if (trigger) { pay.disabled = true; pay.style.opacity = '0.7'; trigger.click(); }
                });
            }
        })();
        </script>
    </div>
</section>
@endsection
