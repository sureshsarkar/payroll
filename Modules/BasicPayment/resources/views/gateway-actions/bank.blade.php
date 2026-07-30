@php
    $method = $paymentMethod;
    $bank_information = $paymentService->getGatewayDetails($method)->bank_information ?? '';
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Bank Checkout</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset($setting->favicon) }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/main.min.css') }}?v={{ $setting?->version }}">
    <link rel="stylesheet" href="{{ asset('global/toastr/toastr.min.css') }}">

</head>

<body>
    <section class="about-area-three section-py-120 vh-100 d-flex align-items-center justify-content-center">
        <div class="container d-flex justify-content-center">
            <div class="col-md-7">
                <div class="text-center mb-3">
                    <a href="{{ route('home') }}">
                        <img src="{{ asset($setting?->logo) }}" alt="{{ $setting?->app_name }}" width="220">
                    </a>
                </div>
                <div class="card singUp-wrap">
                    <div class="card-header bg-transparent">
                        {!! clean(nl2br(e($bank_information))) !!}
                    </div>
                    <div class="card-body">
                        <form
                            action="{{ isset($token) ? route('payment-api.bank-webview', ['bearer_token' => $token, 'order_id' => $order_id]) : route('pay-via-bank') }}"
                            method="post">
                            @csrf

                            <!-- Bank Name -->
                            <div class="my-1 form-group">
                                <label for="bank_name">{{ __('Bank Name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name"
                                    placeholder="{{ __('Your bank name') }}">
                                    @error('bank_name')
                                <small class="text-danger">{{$message}}</small>
                                @enderror
                            </div>

                            <!-- Account Number -->
                            <div class="my-1 form-group">
                                <label for="account_number">{{ __('Account Number') }} <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="account_number" name="account_number"
                                    placeholder="{{ __('Your bank account number') }}">
                                    @error('account_number')
                                <small class="text-danger">{{$message}}</small>
                                @enderror
                            </div>

                            <!-- Routing Number -->
                            <div class="my-1 form-group">
                                <label for="transaction">{{ __('Transaction ID') }}</label>
                                <input type="text" class="form-control" id="transaction" name="transaction"
                                    placeholder="{{ __('Your bank transaction ID') }}">
                                    @error('transaction')
                                <small class="text-danger">{{$message}}</small>
                                @enderror
                            </div>

                            <!-- Routing Number -->
                            <div class="my-1 form-group">
                                <label for="routing_number">{{ __('Routing Number') }}</label>
                                <input type="text" class="form-control" id="routing_number" name="routing_number"
                                    placeholder="{{ __('Your bank routing number') }}">
                                    @error('routing_number')
                                <small class="text-danger">{{$message}}</small>
                                @enderror
                            </div>

                            <!-- Branch -->
                            <div class="my-1 form-group">
                                <label for="branch">{{ __('Branch') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="branch" name="branch"
                                    placeholder="{{ __('Your bank branch name') }}">
                                    @error('branch')
                                <small class="text-danger">{{$message}}</small>
                                @enderror
                            </div>

                            <button class="mt-2 btn btn-primary">{{ __('Submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- dynamic Toastr Notification -->
    <script src="{{ asset('global/toastr/toastr.min.js') }}"></script>
    <script>
        "use strict";
        toastr.options.closeButton = true;
        toastr.options.progressBar = true;
        toastr.options.positionClass = 'toast-bottom-right';

        @session('messege')
        var type = "{{ Session::get('alert-type', 'info') }}"
        switch (type) {
            case 'info':
                toastr.info("{{ $value }}");
                break;
            case 'success':
                toastr.success("{{ $value }}");
                break;
            case 'warning':
                toastr.warning("{{ $value }}");
                break;
            case 'error':
                toastr.error("{{ $value }}");
                break;
        }
        @endsession
    </script>
</body>

</html>
