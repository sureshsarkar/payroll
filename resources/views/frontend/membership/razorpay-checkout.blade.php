@php
    $u = Auth::guard('web')->user();
    $isCoach = $u && $u->role === 'instructor';
    $dashLayout = $isCoach
        ? 'frontend.instructor-dashboard.layouts.master'
        : 'frontend.student-dashboard.layouts.master';
@endphp
@extends($dashLayout)

@section('dashboard-contents')
<div class="py-4" style="font-family:'Plus Jakarta Sans',sans-serif;">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div style="background:#fff; border:1px solid #eef0f3; border-radius:16px; padding:32px; text-align:center; box-shadow:0 4px 12px rgba(15,23,42,0.04);">
                <div style="width:64px; height:64px; margin:0 auto 16px; background:linear-gradient(135deg,#5751e1,#7a73ff); border-radius:50%; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-credit-card" style="color:#fff; font-size:24px;"></i>
                </div>
                <h2 style="margin:0 0 6px; font-size:22px; font-weight:700;">{{ __('Complete your payment') }}</h2>
                <p style="color:#6b7280; font-size:13px; margin-bottom:18px;">{{ __('Pay securely via Razorpay to activate your') }} <strong>{{ $membership->plan->name }}</strong>.</p>

                <div style="background:#f9fafb; border-radius:12px; padding:14px 18px; margin:20px 0; text-align:left;">
                    <div style="display:flex; justify-content:space-between; padding:4px 0;"><span style="font-size:13px; color:#6b7280;">{{ __('Plan') }}</span><strong>{{ $membership->plan->name }}</strong></div>
                    <div style="display:flex; justify-content:space-between; padding:4px 0;"><span style="font-size:13px; color:#6b7280;">{{ __('Wallet credit applied') }}</span><strong style="color:#10b981;">−{{ currency($membership->wallet_credit_used) }}</strong></div>
                    <div style="display:flex; justify-content:space-between; padding:8px 0; border-top:2px solid #f3f4f6; margin-top:6px;"><span style="font-weight:600;">{{ __('Pay now') }}</span><strong style="font-size:18px; color:#5751e1;">{{ currency($amount / 100) }}</strong></div>
                </div>

                <button id="rzp-pay-btn" type="button" style="display:inline-flex; gap:8px; align-items:center; justify-content:center; width:100%; padding:14px; background:linear-gradient(135deg,#5751e1,#7a73ff); color:#fff; border:none; border-radius:12px; font-weight:600; font-size:14px; cursor:pointer;">
                    <i class="fas fa-lock"></i> {{ __('Pay securely') }}
                </button>

                <a href="{{ route('membership.index') }}" style="display:inline-block; margin-top:14px; color:#6b7280; text-decoration:none; font-size:13px;">
                    <i class="fas fa-arrow-left"></i> {{ __('Cancel and go back') }}
                </a>
            </div>
        </div>
    </div>
</div>

<form id="rzp-callback-form" method="POST" action="{{ route('membership.razorpay.callback', $membership->id) }}" style="display:none;">
    @csrf
    <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
    <input type="hidden" name="razorpay_order_id"   id="razorpay_order_id">
    <input type="hidden" name="razorpay_signature"  id="razorpay_signature">
</form>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    const options = {
        key:       @json($razorpayKey),
        amount:    {{ $amount }},
        currency:  @json($currency),
        name:      @json(\Cache::get('setting')?->app_name ?? 'Membership'),
        description: @json(__('Membership: ') . $membership->plan->name),
        order_id:  @json($rzpOrderId),
        prefill: {
            name:    @json($user->name),
            email:   @json($user->email),
            contact: @json($user->phone ?? ''),
        },
        theme: { color: '#5751e1' },
        handler: function (response) {
            document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
            document.getElementById('razorpay_order_id').value   = response.razorpay_order_id;
            document.getElementById('razorpay_signature').value  = response.razorpay_signature;
            document.getElementById('rzp-callback-form').submit();
        },
        modal: {
            ondismiss: function () { /* user closed the modal — stay on this page */ }
        }
    };

    const rzp = new Razorpay(options);
    rzp.on('payment.failed', function (resp) {
        alert(@json(__('Payment failed:')) + ' ' + (resp.error?.description || ''));
    });

    document.getElementById('rzp-pay-btn').addEventListener('click', () => rzp.open());

    // Auto-open the checkout modal on page load for a friction-free flow.
    rzp.open();
})();
</script>
@endsection
