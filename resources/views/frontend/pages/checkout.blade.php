@extends('frontend.layouts.master')
@section('meta_title', 'Checkout' . ' || ' . $setting->app_name)
@section('contents')
    <!-- breadcrumb-area -->
    <x-frontend.breadcrumb :title="__('Make Payment')" :links="[
        ['url' => route('home'), 'text' => __('Home')],
        ['url' => route('checkout.index'), 'text' => __('Make Payment')],
    ]" />
    <!-- breadcrumb-area-end -->

    <!-- checkout-area -->
    <div class="checkout__area section-py-120">
        <div class="preloader-two preloader-two-fixed d-none">
            {{-- <div class="loader-icon-two"><img src="{{ asset(Cache::get('setting')->preloader) }}" alt="Preloader"></div> --}}
            <div class="loader-icon-two"><img src="{{ asset($setting->logo??Cache::get('setting')->preloader) }}" alt="Preloader"></div>
        </div>
        <div class="container"> 
            <div class="row">
                <div class="col-lg-8">
                    <div id="show_currency_notifications">
                        <div class="alert alert-warning d-none"></div>
                    </div>
                    <div class="wsus__payment_area">
                        <div class="row">
                            @if ($payable_amount > 0)
                                @foreach ($activeGateways as $gatewayKey => $gatewayDetails)
                                    <div class="col-lg-3 col-6 col-sm-4">
                                        <a class="wsus__single_payment place-order-btn" data-method="{{ $gatewayKey }}">
                                            <img src="{{ asset($gatewayDetails['logo']) }}"
                                                alt="{{ $gatewayDetails['name'] }}" class="img-fluid w-100">
                                        </a>
                                    </div>
                                @endforeach
                            @else
                                <div class="col-lg-3 col-6 col-sm-4">
                                    <form action="{{ route('pay-via-free-gateway') }}" method="POST">
                                        @csrf
                                        <button class="wsus__single_payment border-0">
                                            <img src="{{ asset('uploads/website-images/buy_now.png') }}"
                                                alt="Pay with stripe" class="img-fluid w-100">
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="cart__collaterals-wrap payment_slidebar">
                        <h2 class="title">{{ __('Cart totals') }}</h2>
                        <ul class="list-wrap pb-0">
                            <li>{{ __('Total Items') }}<span>{{ $cart_count }}</span></li>
                            <li>
                                @if (Session::has('coupon_code'))
                                    <p class="coupon-discount m-0">
                                        <span>{{ __('Discount') }}</span>
                                        <br>
                                        <small>{{ $coupon }} ({{ $discountPercent }} %)<a class="ms-2 text-danger"
                                                href="/remove-coupon">×</a></small>
                                    </p>
                                    <span class="discount-amount">{{ currency($discountAmount) }}</span>
                                @else
                                    <p class="coupon-discount m-0">
                                        <span>{{ __('Discount') }}</span>
                                    </p>
                                    <span class="discount-amount">{{ currency(0) }}</span>
                                @endif
                            </li>
                            <li>{{ __('Total') }} <span class="amount">{{ $total }}</span></li>

                            @if ($payable_amount > 0)
                                <h6 class="bold payable-bold">{{ __('payable with gateway charge') }}:</h6>

                                @php
                                    $currency = getSessionCurrency();
                                @endphp

                                @foreach ($activeGateways as $gatewayKey => $gatewayDetails)
                                    @if ($paymentService->isCurrencySupported($gatewayKey))
                                        @php
                                            $payableDetails = $paymentService->getPayableAmount(
                                                $gatewayKey,
                                                $payable_amount,
                                            );
                                        @endphp

                                        <p class="payable-text">
                                            {{ $gatewayDetails['name'] }}:
                                            <span>{{ $payableDetails->payable_with_charge }} {{ $currency }}</span>
                                        </p>
                                    @endif
                                @endforeach
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('frontend/js/default/checkout.js') }}"></script>
@endpush
