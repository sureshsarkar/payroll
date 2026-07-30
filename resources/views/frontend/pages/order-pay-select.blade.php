@extends('frontend.layouts.master')
@section('meta_title', __('Choose a payment method') . ' || ' . ($setting->app_name ?? config('app.name')))
@section('contents')
    {{-- 2026-06-03 (Model B) — gateway picker for an EXISTING pending order whose
         stored payment_method isn't a usable gateway (e.g. a coach-created
         "coach_manual" invoice). Rendered by PaymentController@index. Each card
         links back to the payment page with the chosen method; index() then
         persists it on the order and renders that gateway's checkout. --}}
    <x-frontend.breadcrumb :title="__('Make Payment')" :links="[
        ['url' => route('home'), 'text' => __('Home')],
        ['url' => route('payment', ['invoice_id' => $order->invoice_id]), 'text' => __('Make Payment')],
    ]" />

    <div class="checkout__area section-py-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="wsus__payment_area">
                        <h4 class="title mb-1">{{ __('Choose a payment method') }}</h4>
                        <p class="mb-4">
                            {{ __('Invoice') }}: <strong>{{ $order->invoice_id }}</strong>
                        </p>
                        <div class="row">
                            @foreach ($gateways as $gatewayKey => $gatewayDetails)
                                <div class="col-lg-3 col-6 col-sm-4">
                                    <a class="wsus__single_payment"
                                       href="{{ route('payment', ['invoice_id' => $order->invoice_id, 'method' => $gatewayKey]) }}"
                                       title="{{ $gatewayDetails['name'] }}">
                                        <img src="{{ asset($gatewayDetails['logo']) }}"
                                             alt="{{ $gatewayDetails['name'] }}" class="img-fluid w-100">
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
