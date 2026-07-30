@php
    $bkashCredentials = (object) Modules\BkashPG\app\Models\BkashPGModel::pluck('value', 'key')->toArray();
@endphp
<div class="tab-pane fade" id="bkash_tab" role="tabpanel">
    <form action="{{ route('admin.bkash.update', 1) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="form-group col-md-6">
                <label for="bkash_charge">{{ __('Gateway charge (%)') }} <span class="text-danger">*</span></label>
                <input step="0.01" type="number" class="form-control" name="bkash_charge" id="bkash_charge"
                    value="{{ @$bkashCredentials?->bkash_charge }}" required="true"
                    placeholder="{{ __('Gateway charge (%)') }}">
            </div>
            <div class="form-group col-md-6">
                <label>{{ __('Sandbox Status') }} <span class="text-danger">*</span></label>
                <select name="bkash_sandbox" class="form-control select2" id="bkash_sandbox">
                    <option value="">{{ __('Select Sandbox Status') }}</option>
                    <option value="{{ config('basicpayment.default_status.active_int') }}" @selected(config('basicpayment.default_status.active_int') == old('bkash_sandbox', @$bkashCredentials?->bkash_sandbox))>
                        {{ __('Sandbox') }}</option>
                    <option value="{{ config('basicpayment.default_status.inactive_int') }}"
                        @selected(config('basicpayment.default_status.inactive_int') == old('bkash_sandbox', @$bkashCredentials?->bkash_sandbox))>{{ __('Live') }}</option>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label for="bkash_key">{{ __('Bkash Key') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="bkash_key" id="bkash_key"
                    value="{{ @$bkashCredentials?->bkash_key }}" required="true" placeholder="{{ __('Bkash Key') }}">
            </div>
            <div class="form-group col-md-6">
                <label for="bkash_secret">{{ __('Bkash Secret') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="bkash_secret" id="bkash_secret"
                    value="{{ @$bkashCredentials?->bkash_secret }}" required="true"
                    placeholder="{{ __('Bkash Secret') }}">
            </div>
            <div class="form-group col-md-6">
                <label for="bkash_username">{{ __('Bkash Username') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="bkash_username" id="bkash_username"
                    value="{{ @$bkashCredentials?->bkash_username }}" required="true"
                    placeholder="{{ __('Bkash Username') }}">
            </div>
            <div class="form-group col-md-6">
                <label for="bkash_password">{{ __('Bkash Password') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="bkash_password" id="bkash_password"
                    value="{{ @$bkashCredentials?->bkash_password }}" required="true"
                    placeholder="{{ __('Bkash Password') }}">
            </div>

            <div class="form-group col-md-12">
                <label>{{ __('New Image') }}</label>
                <div id="image-preview-bkash" class="image-preview"
                    style="background-image: url({{ asset(@$bkashCredentials?->bkash_image ?? 'uploads/website-images/bkash.png') }});">
                    <label for="image-upload-bkash" id="image-label-bkash">{{ __('Image') }}</label>
                    <input type="file" name="bkash_image" id="image-upload-bkash">
                </div>

            </div>

            <div class="form-group col-md-12">
                <label class="d-flex align-items-center">
                    <input type="hidden" value="{{ config('basicpayment.default_status.inactive_text') }}"
                        name="bkash_status" class="custom-switch-input">
                    <input type="checkbox" @checked(config('basicpayment.default_status.active_text') == old('bkash_status', @$bkashCredentials?->bkash_status))
                        value="{{ config('basicpayment.default_status.active_text') }}" name="bkash_status"
                        class="custom-switch-input"
                        {{ @$bkashCredentials?->bkash_status == 'active' ? 'checked' : '' }}>
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
