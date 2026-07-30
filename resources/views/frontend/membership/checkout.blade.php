@php
    $u = Auth::guard('web')->user();
    $isCoach = $u && $u->role === 'instructor';
    $dashLayout = $isCoach
        ? 'frontend.instructor-dashboard.layouts.master'
        : 'frontend.student-dashboard.layouts.master';
    // Defensive: $billing ships with the annual-billing release; default monthly.
    $billing = ($billing ?? 'monthly');
    $isAnnual = $billing === 'annual';
    // Real "who it's for" descriptor + feature list from the plan itself.
    $feats  = collect($plan->features ?? []);
    $whoFor = $feats->first(fn ($f) => \Illuminate\Support\Str::startsWith($f, ['Ideal for', 'For ']));
    $featList = $whoFor ? $feats->reject(fn ($f) => $f === $whoFor)->values() : $feats;
@endphp
@extends($dashLayout)

@section('dashboard-contents')
<div class="mbs-co">
    <div class="mbs-co__head">
        <a href="{{ route('membership.index') }}" class="mbs-co__back"><i class="fas fa-arrow-left"></i> {{ __('Back to plans') }}</a>
        <h2>{{ __('Complete your purchase') }}</h2>
        <p>{{ __('Review your plan and apply any referral wallet credit before you pay.') }}</p>
    </div>

    <div class="mbs-co__grid">
        {{-- Left — what you're getting --}}
        <div class="mbs-co__left">
            <div class="mbs-co__planhead">
                <div>
                    <div class="mbs-co__name">{{ $plan->name }}</div>
                    @if ($whoFor)<div class="mbs-co__who">{{ $whoFor }}</div>@endif
                </div>
                <span class="mbs-co__period">
                    <i class="fas fa-calendar-alt"></i>
                    @if ($isAnnual)
                        {{ __('Billed annually') }}
                    @elseif ($plan->duration_days > 0)
                        {{ __('Billed every :n days', ['n' => $plan->duration_days]) }}
                    @else
                        {{ __('Lifetime') }}
                    @endif
                </span>
            </div>

            @if ($featList->isNotEmpty())
                <div class="mbs-co__inclhdr">{{ __("What's included") }}</div>
                <ul class="mbs-co__feats">
                    @foreach ($featList as $f)
                        <li><i class="fas fa-check"></i> {{ $f }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="mbs-co__trust">
                <span><i class="fas fa-shield-alt"></i> {{ __('Secure payment') }}</span>
                <span><i class="fas fa-lock"></i> {{ __('Encrypted checkout') }}</span>
                <span><i class="fas fa-eye-slash"></i> {{ __('No hidden fees') }}</span>
            </div>
        </div>

        {{-- Right — order summary --}}
        <div class="mbs-co__right">
            <div class="mbs-co__summary">
                <div class="mbs-co__sumttl">{{ __('Order summary') }}</div>

                <form method="POST" action="{{ route('membership.pay', $plan->id) }}">
                    @csrf
                    {{-- carry the chosen billing period so annual stays annual at pay() --}}
                    <input type="hidden" name="billing" value="{{ $billing }}">

                    <label class="mbs-co__wallet" id="walletToggle">
                        <input type="checkbox" name="apply_wallet" value="1" checked id="applyWallet">
                        <span>
                            <strong>{{ __('Apply referral wallet credit') }}</strong>
                            <span class="bal">{{ __('Available') }}: <strong>{{ currency($u->referral_wallet_balance ?? 0) }}</strong></span>
                        </span>
                    </label>

                    <div class="mbs-co__lines">
                        <div class="ln"><span>{{ $plan->name }} {{ $isAnnual ? __('(annual)') : '' }}</span><strong id="sumPrice">{{ currency($quoteWith['plan_price']) }}</strong></div>
                        <div class="ln muted-green"><span>{{ __('Wallet credit') }}</span><strong id="sumWallet">−{{ currency($quoteWith['wallet_used']) }}</strong></div>
                        <div class="ln final"><span>{{ __('Amount to pay') }}</span><strong id="sumFinal">{{ currency($quoteWith['final']) }}</strong></div>
                    </div>

                    @if ($quoteWith['final'] > 0)
                        @php
                            $payInfo = \Cache::get('payment_setting');
                            if (!$payInfo) {
                                $rows = \Modules\BasicPayment\app\Models\PaymentGateway::get();
                                $bag  = [];
                                foreach ($rows as $r) { $bag[$r->key] = $r->value; }
                                $payInfo = (object) $bag;
                            }
                            $razorpayReady = !empty($payInfo->razorpay_key ?? null) && !empty($payInfo->razorpay_secret ?? null);
                        @endphp
                        <label class="mbs-co__method">
                            <span>{{ __('Payment method') }}</span>
                            <select name="payment_method" required>
                                @if ($razorpayReady)
                                    <option value="razorpay" selected>{{ __('Razorpay — card / UPI / netbanking') }}</option>
                                @endif
                                <option value="manual">{{ __('Manual / Bank transfer') }}</option>
                            </select>
                        </label>
                        <p class="mbs-co__note">
                            @if ($razorpayReady)
                                {{ __('Razorpay confirms instantly. Manual transfers are activated after admin verification.') }}
                            @else
                                {{ __('Manual payments are activated by admin once verified.') }}
                            @endif
                        </p>
                    @endif

                    <button type="submit" class="mbs-co__btn">
                        @if ($quoteWith['final'] == 0)
                            <i class="fas fa-bolt"></i> {{ __('Activate now') }}
                        @else
                            {{ __('Continue to payment') }} <i class="fas fa-arrow-right"></i>
                        @endif
                    </button>

                    <div class="mbs-co__secure"><i class="fas fa-shield-alt"></i> {{ __('Secure payment · your details are encrypted') }}</div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .mbs-co { color:#0b1220; max-width:980px; margin:0 auto; padding:18px 4px; }
    .mbs-co__back { display:inline-flex; align-items:center; gap:6px; color:#6b7280; text-decoration:none; font-size:13px; margin-bottom:10px; }
    .mbs-co__back:hover { color:#4f46e5; }
    .mbs-co__head h2 { margin:2px 0 2px; font-size:22px; font-weight:800; letter-spacing:-.02em; }
    .mbs-co__head p { color:#6b7280; font-size:13.5px; margin:0 0 18px; }
    .mbs-co__grid { display:grid; grid-template-columns:1.2fr 1fr; gap:18px; align-items:start; }
    @media (max-width:820px){ .mbs-co__grid { grid-template-columns:1fr; } }

    .mbs-co__left { background:#fff; border:1px solid #e8eaf0; border-radius:16px; padding:22px 24px; box-shadow:0 1px 2px rgba(16,24,40,.04); }
    .mbs-co__planhead { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding-bottom:16px; border-bottom:1px solid #f1f2f6; }
    .mbs-co__name { font-size:18px; font-weight:800; letter-spacing:-.01em; }
    .mbs-co__who { font-size:12.5px; color:#4f46e5; font-weight:600; margin-top:3px; }
    .mbs-co__period { display:inline-flex; align-items:center; gap:6px; flex-shrink:0; background:#eef1ff; color:#4338ca; font-size:11.5px; font-weight:600; padding:5px 11px; border-radius:999px; white-space:nowrap; }
    .mbs-co__period i { font-size:10px; }
    .mbs-co__inclhdr { font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#9aa1b0; margin:18px 0 10px; }
    .mbs-co__feats { list-style:none; padding:0; margin:0; }
    .mbs-co__feats li { display:flex; align-items:flex-start; gap:10px; padding:6px 0; font-size:13.5px; color:#3d4453; line-height:1.5; }
    .mbs-co__feats li i { color:#16a34a; font-size:12px; margin-top:4px; flex-shrink:0; }
    .mbs-co__trust { display:flex; flex-wrap:wrap; gap:16px; margin-top:18px; padding-top:16px; border-top:1px solid #f1f2f6; font-size:12px; color:#6b7280; }
    .mbs-co__trust i { color:#4f46e5; margin-right:5px; }

    .mbs-co__right { position:sticky; top:14px; }
    .mbs-co__summary { background:#fff; border:1px solid #e8eaf0; border-radius:16px; padding:20px 22px; box-shadow:0 8px 28px -12px rgba(16,24,40,.12); }
    .mbs-co__sumttl { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#0b1220; margin-bottom:14px; }
    .mbs-co__wallet { display:flex; gap:12px; align-items:center; padding:13px 14px; border:1px solid #e8eaf0; border-radius:12px; cursor:pointer; margin-bottom:14px; }
    .mbs-co__wallet input { width:18px; height:18px; accent-color:#4f46e5; }
    .mbs-co__wallet strong { font-size:13px; }
    .mbs-co__wallet .bal { display:block; font-size:12px; color:#6b7280; margin-top:2px; }
    .mbs-co__lines { border-top:1px solid #f1f2f6; padding-top:12px; }
    .mbs-co__lines .ln { display:flex; justify-content:space-between; gap:10px; padding:7px 0; font-size:14px; color:#3d4453; }
    .mbs-co__lines .muted-green strong { color:#16a34a; }
    .mbs-co__lines .final { border-top:1.5px solid #eef0f4; margin-top:6px; padding-top:13px; font-size:18px; font-weight:700; color:#0b1220; }
    .mbs-co__lines .final strong { color:#4f46e5; }
    .mbs-co__method { display:block; margin-top:16px; }
    .mbs-co__method span { font-size:12.5px; font-weight:600; color:#374151; display:block; margin-bottom:6px; }
    .mbs-co__method select { width:100%; padding:11px 12px; border:1px solid #e8eaf0; border-radius:10px; font-size:13px; background:#fff; }
    .mbs-co__note { font-size:11.5px; color:#9aa1b0; margin:8px 0 0; line-height:1.5; }
    .mbs-co__btn { display:flex; justify-content:center; align-items:center; gap:8px; width:100%; padding:14px;
        background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; border:none; border-radius:12px; font-weight:600; font-size:14px; cursor:pointer; margin-top:18px;
        box-shadow:0 8px 20px -8px rgba(79,70,229,.5); transition:transform .14s; }
    .mbs-co__btn:hover { transform:translateY(-1px); }
    .mbs-co__secure { text-align:center; font-size:11.5px; color:#9aa1b0; margin-top:12px; }
    .mbs-co__secure i { color:#16a34a; }
</style>

<script>
(function () {
    const toggle = document.getElementById('applyWallet');
    const sumWallet = document.getElementById('sumWallet');
    const sumFinal = document.getElementById('sumFinal');
    if (!toggle) return;
    const withApplied    = { wallet_used: '−{{ currency($quoteWith['wallet_used']) }}', final: '{{ currency($quoteWith['final']) }}' };
    const withoutApplied = { wallet_used: '−{{ currency(0) }}', final: '{{ currency($quoteWithout['final']) }}' };
    toggle.addEventListener('change', () => {
        const v = toggle.checked ? withApplied : withoutApplied;
        sumWallet.textContent = v.wallet_used;
        sumFinal.textContent = v.final;
    });
})();
</script>
@endsection
