@php
    $mercadopagoCredentials = (object) Modules\MercadoPagoPG\app\Models\MercadoPagoPG::pluck('value', 'key')->toArray();
@endphp
<div class="tab-pane fade" id="mercado_tab" role="tabpanel">
    <form action="{{ route('admin.mercadopagopg.update', 1) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="form-group col-md-6">
                <label for="mercadopago_charge">{{ __('Gateway charge (%)') }} <span class="text-danger">*</span></label>
                <input step="0.01" type="number" class="form-control" name="mercadopago_charge"
                    id="mercadopago_charge" value="{{ @$mercadopagoCredentials?->mercadopago_charge }}" required="true"
                    placeholder="{{ __('Gateway charge (%)') }}">
            </div>
            <div class="form-group col-md-6">
                <label>{{ __('Sandbox Status') }} <span class="text-danger">*</span></label>
                <select name="mercadopago_sandbox" class="form-control select2" id="mercadopago_sandbox">
                    <option value="">{{ __('Select Sandbox Status') }}</option>
                    <option value="{{ config('basicpayment.default_status.active_int') }}" @selected(config('basicpayment.default_status.active_int') == old('mercadopago_sandbox', @$mercadopagoCredentials?->mercadopago_sandbox))>
                        {{ __('Sandbox') }}</option>
                    <option value="{{ config('basicpayment.default_status.inactive_int') }}"
                        @selected(config('basicpayment.default_status.inactive_int') == old('mercadopago_sandbox', @$mercadopagoCredentials?->mercadopago_sandbox))>{{ __('Live') }}</option>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label for="public_key">{{ __('Public key') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="public_key" id="public_key"
                    value="{{ @$mercadopagoCredentials?->public_key }}" required="true"
                    placeholder="{{ __('Public key') }}">
            </div>
            <div class="form-group col-md-6">
                <label for="access_token">{{ __('Access token') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="access_token" id="access_token"
                    value="{{ @$mercadopagoCredentials?->access_token }}" required="true"
                    placeholder="{{ __('Access token') }}">
            </div>
            <div class="form-group col-md-12">
                <label>{{ __('New Image') }}</label>
                <div id="image-preview-mercado" class="image-preview"
                    style="background-image: url({{ asset(@$mercadopagoCredentials?->mercadopago_image ?? 'uploads/website-images/mercado-pago.png') }});">
                    <label for="image-upload-mercado" id="image-label-mercado">{{ __('Image') }}</label>
                    <input type="file" name="mercadopago_image" id="image-upload-mercado">
                </div>
            </div>

            <div class="form-group col-md-12">
                <label class="d-flex align-items-center">
                    <input type="hidden" value="{{ config('basicpayment.default_status.inactive_text') }}"
                        name="mercadopago_status" class="custom-switch-input">
                    <input type="checkbox" @checked(config('basicpayment.default_status.active_text') ==
                            old('mercadopago_status', @$mercadopagoCredentials?->mercadopago_status))
                        value="{{ config('basicpayment.default_status.active_text') }}" name="mercadopago_status"
                        class="custom-switch-input"
                        {{ @$mercadopagoCredentials?->mercadopago_status == 'active' ? 'checked' : '' }}>
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
