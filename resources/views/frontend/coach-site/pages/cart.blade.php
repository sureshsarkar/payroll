{{-- Coach-scoped cart page — rendered inside the coach master layout so
     the URL stays /coach/{slug}/cart and the branding is the coach's.
     2026-06-16 — premium white-label redesign. Accent follows the coach brand
     (--brand-primary); structure/data/routes unchanged from the prior version. --}}
@extends('frontend.coach-site.layouts.master')

@section('content')
@php
    $sessEnrollments       = session()->get('enrollments') ?? [];
    $sessInstructorCourses = session()->get('instructor_courses') ?? [];
@endphp

<style>
    .pcart{ --acc:var(--brand-primary,#4f46e5); --acc-soft:color-mix(in srgb, var(--brand-primary,#4f46e5) 12%, #fff); }
    .pcart-head{ display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:22px; flex-wrap:wrap; }
    .pcart-head__l{ display:flex; align-items:center; gap:11px; }
    .pcart-chip{ width:40px; height:40px; border-radius:11px; background:var(--acc-soft); color:var(--acc); display:flex; align-items:center; justify-content:center; font-size:18px; flex:0 0 auto; }
    .pcart-title{ margin:0; font-size:22px; font-weight:800; letter-spacing:-.02em; color:#0f172a; }
    .pcart-sub{ margin:2px 0 0; font-size:13px; color:var(--brand-muted,#64748b); }
    .pcart-back{ font-size:13.5px; color:var(--acc); text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .pcart-grid{ display:grid; grid-template-columns:minmax(0,1.6fr) minmax(0,1fr); gap:20px; align-items:start; }
    @media (max-width:768px){ .pcart-grid{ grid-template-columns:1fr; } }
    .pcart-items{ display:flex; flex-direction:column; gap:12px; }
    .pcart-item{ display:flex; gap:14px; align-items:center; background:#fff; border:1px solid #eef0f5; border-radius:14px; padding:14px; transition:border-color .15s, box-shadow .15s, transform .15s; }
    .pcart-item:hover{ border-color:var(--acc); box-shadow:0 12px 28px rgba(15,23,42,.08); transform:translateY(-2px); }
    .pcart-thumb{ width:66px; height:66px; border-radius:10px; object-fit:cover; flex:0 0 auto; }
    .pcart-ico{ width:66px; height:66px; border-radius:10px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; background:var(--acc-soft); color:var(--acc); font-size:24px; }
    .pcart-name{ display:block; color:#0f172a; font-size:14.5px; font-weight:700; letter-spacing:-.01em; }
    .pcart-tag{ display:inline-block; margin-top:6px; background:#FEF3C7; color:#92400E; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:600; }
    .pcart-price{ font-weight:700; color:var(--acc); font-size:15px; white-space:nowrap; }
    .pcart-remove{ display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:8px; color:#9ca3af; text-decoration:none; font-size:16px; transition:.15s; }
    .pcart-remove:hover{ color:#dc2626; background:#fef2f2; }
    .pcart-summary{ background:#fff; border:1.5px solid #e8eaf0; border-radius:16px; padding:20px; position:sticky; top:90px; }
    .pcart-srow{ display:flex; align-items:center; justify-content:space-between; font-size:13.5px; padding:8px 0; }
    .pcart-total{ display:flex; align-items:baseline; justify-content:space-between; border-top:1px solid #f1f5f9; margin-top:6px; padding-top:12px; }
    .pcart-proceed{ display:flex; align-items:center; justify-content:center; gap:8px; width:100%; margin-top:14px; background:var(--acc); color:#fff; font-weight:700; font-size:15px; padding:13px; border-radius:12px; text-decoration:none; box-shadow:0 10px 24px rgba(15,23,42,.16); transition:filter .15s, transform .15s; }
    .pcart-proceed:hover{ filter:brightness(.95); transform:translateY(-1px); color:#fff; }
    .pcart-secure{ margin:12px 0 0; font-size:12px; color:var(--brand-muted,#64748b); display:flex; align-items:center; justify-content:center; gap:6px; }
    .pcart-coupon-form{ display:flex; gap:8px; margin:4px 0 14px; }
    .pcart-coupon-form input{ flex:1; min-width:0; height:40px; border:1px solid #e2e8f0; border-radius:10px; padding:0 12px; font-size:13px; }
    .pcart-coupon-form input:focus{ outline:none; border-color:var(--acc); box-shadow:0 0 0 3px color-mix(in srgb, var(--brand-primary,#4f46e5) 18%, transparent); }
    .pcart-coupon-form button{ flex:0 0 auto; border:0; background:var(--acc); color:#fff; font-weight:600; font-size:13px; padding:0 16px; border-radius:10px; cursor:pointer; }
    .pcart-coupon-applied{ display:flex; align-items:center; gap:8px; margin:4px 0 14px; padding:9px 12px; border-radius:10px; font-size:12.5px; font-weight:600; color:#0f766e; background:#ecfdf5; border:1px solid #d1fae5; }
    .pcart-coupon-applied a{ margin-left:auto; color:#dc2626; text-decoration:none; font-size:15px; line-height:1; }
    .pcart-srow.disc span{ color:var(--color-text-success,#0f766e); }
    .pcart-warn{ background:#FEF3C7; border-left:4px solid #F59E0B; padding:13px 18px; border-radius:8px; margin-bottom:14px; font-size:14px; color:#92400E; }
    .pcart-empty{ text-align:center; padding:64px 20px; background:#F9FAFB; border:1px solid #eef0f5; border-radius:16px; }
</style>

<section class="cs-pad">
    <div class="cs-container pcart" style="max-width:1100px;">

        <div class="pcart-head">
            <div class="pcart-head__l">
                <span class="pcart-chip"><i class="fa-solid fa-cart-shopping"></i></span>
                <div>
                    <h1 class="pcart-title">{{ __('Your cart') }}</h1>
                    <p class="pcart-sub">{{ (int) ($cart_count ?? 0) }} {{ __('item(s) in your cart') }}</p>
                </div>
            </div>
            <a href="{{ route('coach.site.path', ['site_slug' => $coachSlug]) }}" class="pcart-back">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Keep browsing') }}
            </a>
        </div>

        @auth('web')
            @foreach ($products as $item)
                @if (in_array($item?->course?->id, $sessEnrollments))
                    <div class="pcart-warn">{{ __('You have items in your cart that you already purchased. Please remove them before proceeding.') }}</div>
                    @break
                @elseif (in_array($item?->course?->id, $sessInstructorCourses))
                    <div class="pcart-warn">{{ __('You have your own courses in your cart. Please remove them before proceeding.') }}</div>
                    @break
                @endif
            @endforeach
        @endauth

        @if(($cart_count ?? 0) === 0)
            <div class="pcart-empty">
                <i class="fa-solid fa-cart-shopping" style="font-size:46px;color:#D1D5DB;margin-bottom:16px;display:block;"></i>
                <h3 style="margin:0 0 8px;font-size:18px;color:#111827;">{{ __('Your cart is empty.') }}</h3>
                <p style="color:#6B7280;margin-bottom:22px;">{{ __('Browse courses and add some to your cart.') }}</p>
                <a href="{{ route('coach.site.path', ['site_slug' => $coachSlug]) }}" class="cs-btn cs-btn--primary">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Continue browsing') }}
                </a>
            </div>
        @else
            <div class="pcart-grid">

                <div class="pcart-items">
                    @foreach($products as $item)
                        @php
                            $isLoggedIn = auth('web')->check();
                            $courseId   = $isLoggedIn ? ($item?->course?->id) : ($item->id ?? null);
                            $title      = $isLoggedIn ? ($item?->course?->title) : ($item->name ?? __('Course'));
                            $slug       = $isLoggedIn ? ($item?->course?->slug) : ($item->options['slug'] ?? null);
                            $thumbnail  = $isLoggedIn ? ($item?->course?->thumbnail) : ($item->options['thumbnail'] ?? null);
                            $rawPrice   = $isLoggedIn
                                ? (($item?->course?->discount > 0) ? $item->course->discount : ($item?->course?->price ?? 0))
                                : ($item->price ?? 0);
                            $removeUrl  = $isLoggedIn
                                ? route('remove-cart-item', [base64_encode($item?->id), $slug])
                                : (isset($item->rowId)
                                    ? route('remove-cart-item', [base64_encode($item->rowId), $slug ?? '0'])
                                    : null);
                        @endphp
                        <div class="pcart-item">
                            @if($thumbnail)
                                <img src="{{ str_starts_with($thumbnail, 'http') ? $thumbnail : asset($thumbnail) }}" alt="" class="pcart-thumb">
                            @else
                                <span class="pcart-ico"><i class="fa-solid fa-graduation-cap"></i></span>
                            @endif
                            <div style="flex:1;min-width:0;">
                                <strong class="pcart-name">{{ $title }}</strong>
                                @auth('web')
                                    @if(in_array($courseId, $sessEnrollments))
                                        <span class="pcart-tag">{{ __('Already purchased') }}</span>
                                    @elseif(in_array($courseId, $sessInstructorCourses))
                                        <span class="pcart-tag">{{ __('Own course') }}</span>
                                    @endif
                                @endauth
                            </div>
                            <span class="pcart-price">{{ currency($rawPrice) }}</span>
                            @if($removeUrl)
                                <a href="{{ $removeUrl }}" class="pcart-remove" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><i class="fa-solid fa-trash-can"></i></a>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="pcart-summary">
                    <p style="margin:0 0 10px;font-size:15px;font-weight:700;color:#0f172a;">{{ __('Order summary') }}</p>

                    @if(!empty($coupon))
                        <div class="pcart-coupon-applied">
                            <i class="fa-solid fa-ticket"></i>
                            {{ $coupon }} ({{ (int) $discountPercent }}%) {{ __('applied') }}
                            <a href="{{ route('remove-coupon') }}" title="{{ __('Remove coupon') }}" aria-label="{{ __('Remove coupon') }}">&times;</a>
                        </div>
                    @else
                        <form action="{{ route('apply-coupon') }}" method="POST" class="pcart-coupon-form">
                            @csrf
                            <input type="text" name="coupon" autocomplete="off" placeholder="{{ __('Coupon code') }}">
                            <button type="submit">{{ __('Apply') }}</button>
                        </form>
                    @endif

                    <div class="pcart-srow">
                        <span style="color:var(--brand-muted,#64748b);">{{ __('Subtotal') }}</span>
                        <span style="font-weight:600;color:#0f172a;">{{ currency($cartTotal) }}</span>
                    </div>
                    @if(($discountAmount ?? 0) > 0)
                        <div class="pcart-srow disc">
                            <span><i class="fa-solid fa-tag" style="margin-right:5px;"></i>{{ __('Coupon discount') }}</span>
                            <span style="font-weight:600;">&minus;{{ currency($discountAmount) }}</span>
                        </div>
                    @endif
                    <div class="pcart-total">
                        <span style="font-size:14px;font-weight:600;color:#0f172a;">{{ __('Total') }}</span>
                        <span style="font-size:22px;font-weight:800;color:#0f172a;">{{ $total }}</span>
                    </div>
                    <a href="{{ coachCommerceUrl('checkout', $coachSlug) }}" class="pcart-proceed">
                        {{ __('Proceed to checkout') }} <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <p class="pcart-secure"><i class="fa-solid fa-lock"></i> {{ __('Taxes are applied at checkout.') }}</p>
                </div>

            </div>
        @endif
    </div>
</section>

<script nonce="{{ csp_nonce() }}">
(function(){
    document.addEventListener('submit', function (e) {
        var form = e.target.closest && e.target.closest('.pcart-coupon-form');
        if (!form) return;
        e.preventDefault();
        var btn  = form.querySelector('button');
        var code = (form.querySelector('input[name=coupon]') || {}).value || '';
        var tok  = (form.querySelector('input[name=_token]') || {}).value || '';
        if (!code.trim()) return;
        btn.disabled = true; var orig = btn.textContent; btn.textContent = '…';
        fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': tok, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: 'coupon=' + encodeURIComponent(code)
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
              if (res.ok) { window.location.reload(); }
              else {
                  btn.disabled = false; btn.textContent = orig;
                  var msg = (res.j && res.j.message) ? res.j.message : 'Invalid coupon';
                  if (window.toastr) { toastr.error(msg); } else { alert(msg); }
              }
          }).catch(function () { btn.disabled = false; btn.textContent = orig; });
    });
})();
</script>
@endsection
