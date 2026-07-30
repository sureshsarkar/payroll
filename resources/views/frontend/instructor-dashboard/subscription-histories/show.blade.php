@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Subscription History') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('instructor.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('Subscription History') }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="invoice">
                    <div class="invoice-print">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="row">
                                    <div class="col-6">
                                        <h3>{{ __('Subscription History') }}</h3>
                                        <address>
                                            <strong>{{ __('Name') }}:</strong> 
                                            {{ $subscription_history->subscription->name??"" }}<br>
                                            <strong>{{ __('Price') }}:</strong> ₹{{ $subscription_history->price }}<br> 
                                            <strong>{{ __('Duration Days') }}:</strong> {{ $subscription_history->subscription->duration_days }} Days<br> 
                                            <strong>{{ __('Status') }}:</strong> {{ ucfirst($subscription_history->status) }}<br> 
                                            <strong>{{ __('Description') }}:</strong> {{ ucfirst($subscription_history->description) }}<br> 
                                            <strong>{{ __('Start Date') }}:</strong> {{ ucfirst($subscription_history->start_date) }}<br> 
                                            <strong>{{ __('End Date') }}:</strong> {{ ucfirst($subscription_history->end_date) }}<br> 
                                        </address>
                                      
                                    </div>

                                    <div class="col-6 text-right">
                                        <h6>{{ __('Subscription History') }} #<span
                                                class="text-primary">{{ $subscription_history->invoice_id }}</span></h6>
                                        <address>
                                            <strong>{{ __('Subscription History Date') }}:</strong><br>
                                            {{ formatDate($subscription_history->created_at) }}<br><br>
                                        </address>
                                      
                                    </div>
                                </div>
                            </div>
                        </div>
 
                    </div>
                    <hr>
                    <div class="text-md-right">

                        <a href="{{ route('instructor.subscription-histories.index') }}"
                            class="btn btn-warning btn-icon icon-left print-btn"><i class="fas fa-arrow-left"></i>
                            {{ __('Back') }}</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
 
@endsection
