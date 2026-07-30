@php
    $cryptoCredentials = (object) Modules\CryptoPayment\app\Models\CryptoPG::pluck('value', 'key')->toArray();
@endphp
<div class="tab-pane fade" id="coingate_tab" role="tabpanel">
    <form action="{{ route('admin.cryptopayment.update', 1) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="row">

            <div class="form-group col-md-6">
                <label>{{ __('Sandbox Status') }} <span class="text-danger">*</span></label>
                <select name="crypto_sandbox" class="form-control select2" id="crypto_sandbox">
                    <option value="">{{ __('Select Sandbox Status') }}</option>
                    <option value="{{ config('basicpayment.default_status.active_int') }}" @selected(config('basicpayment.default_status.active_int') == old('crypto_sandbox', @$cryptoCredentials?->crypto_sandbox))>
                        {{ __('Sandbox') }}</option>
                    <option value="{{ config('basicpayment.default_status.inactive_int') }}"
                        @selected(config('basicpayment.default_status.inactive_int') == old('crypto_sandbox', @$cryptoCredentials?->crypto_sandbox))>{{ __('Live') }}</option>
                </select>
            </div>

            <div class="form-group col-md-6">
                <label for="crypto_token">{{ __('CoinGate Token') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="crypto_token" id="crypto_token"
                    value="{{ @$cryptoCredentials?->crypto_token }}" required="true"
                    placeholder="{{ __('CoinGate Token') }}">
            </div>

            <div class="form-group col-md-6">
                <label for="crypto_receive_currency">{{ __('CoinGate Receiving Currency') }}
                    <span class="text-danger">*</span></label>
                @php
                    $currencies = [
                        'BTC' => 'Bitcoin',
                        'EUR' => 'Euro',
                        'USD' => 'US Dollar',
                        'GBP' => 'British Pound',
                        'ETH' => 'Ethereum',
                    ];

                @endphp
                <select name="crypto_receive_currency" class="form-control select2" id="crypto_receive_currency">
                    <option value="">{{ __('Select Receiving Currency') }}</option>
                    @foreach ($currencies as $currency => $name)
                        <option value="{{ $currency }}" @selected($currency == old('crypto_receive_currency', @$cryptoCredentials?->crypto_receive_currency))>
                            {{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group col-md-6">
                <label for="crypto_charge">{{ __('Charge') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="crypto_charge" id="crypto_charge"
                    value="{{ @$cryptoCredentials?->crypto_charge ?? 0 }}" required="true"
                    placeholder="{{ __('Charge') }}">
            </div>

            <div class="form-group col-md-12">
                <label>{{ __('New Image') }}</label>
                <div id="image-preview-coingate" class="image-preview"
                    style="background-image: url({{ asset(@$cryptoCredentials?->crypto_image ?? 'uploads/website-images/coingate.webp') }});">
                    <label for="image-upload-coingate" id="image-label-coingate">{{ __('Image') }}</label>
                    <input type="file" name="crypto_image" id="image-upload-coingate">
                </div>

            </div>

            <div class="form-group col-md-12">
                <label class="d-flex align-items-center">
                    <input type="hidden" value="{{ config('basicpayment.default_status.inactive_text') }}"
                        name="crypto_status" class="custom-switch-input">
                    <input type="checkbox" @checked(config('basicpayment.default_status.active_text') == old('crypto_status', @$cryptoCredentials?->crypto_status))
                        value="{{ config('basicpayment.default_status.active_text') }}" name="crypto_status"
                        class="custom-switch-input"
                        {{ @$cryptoCredentials?->crypto_status == 'active' ? 'checked' : '' }}>
                    <span class="custom-switch-indicator"></span>
                    <span class="custom-switch-description">{{ __('Status') }}</span>
                </label>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <button class="btn btn-primary">{{ __('Update') }}</button>
            </div>
        </div>
    </form>
</div>
