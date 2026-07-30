@extends('admin.master_layout')
@section('title')
    <title>{{ __('Order Details') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Invoice') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('Invoice') }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="invoice">
                    <div class="invoice-print">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="row">
                                    <div class="col-6">
                                        <h3>{{ __('Invoice') }}</h3>
                                        <address>
                                            <strong>{{ __('Name') }}:</strong> 
                                            {{ $subscription->name }}<br>
                                            <strong>{{ __('Price') }}:</strong> {{ $subscription->price }}<br> 
                                            <strong>{{ __('Duration Days') }}:</strong> {{ $subscription->duration_days }}<br> 
                                            <strong>{{ __('Status') }}:</strong> {{ ucfirst($subscription->status) }}<br> 
                                        </address>
                                      
                                    </div>

                                    <div class="col-6 text-right">
                                        <h6>{{ __('Subscription ') }} #<span
                                                class="text-primary">{{ $subscription->invoice_id }}</span></h6>
                                        <address>
                                            <strong>{{ __('Subscription Date') }}:</strong><br>
                                            {{ formatDate($subscription->created_at) }}<br><br>
                                        </address>
                                        @if ($subscription->isBundleOrder())
                                            <address>
                                                <strong>{{ __('Bundle Name') }}:</strong><br>
                                                {{ $order?->order_details?->title }}<br>
                                            </address>
                                        @endif
                                        @if ($subscription->isGiftOrder())
                                            <address>
                                                <strong>{{ __('Gift') }}:</strong><br>
                                                {{ __('Recipient Name') }}:
                                                {{ $order?->order_details?->recipient_name }}<br>
                                                {{ __('Recipient Email') }}:
                                                {{ $order?->order_details?->recipient_email }}<br>
                                                @if (empty($order?->order_details?->verification_token))
                                                    <div class="badge badge-success my-2">{{ __('Claimed') }}</div>
                                                @else
                                                    <div class="badge badge-warning my-2">{{ __('Pending') }}</div>
                                                    <br>
                                                    <a href="{{route('admin.resend.gift-claim-mail',$order?->invoice_id)}}" class="btn btn-sm btn-success">{{__('Re Send Claim Email')}}</a>
                                                @endif
                                            </address>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
 
                    </div>
                    <hr>
                    <div class="text-md-right">

                        <a href="{{ route('admin.subscriptions') }}"
                            class="btn btn-warning btn-icon icon-left print-btn"><i class="fas fa-arrow-left"></i>
                            {{ __('Back') }}</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
 
@endsection
