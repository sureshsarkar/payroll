<div class="tab-pane fade" id="offline_payment_tab" role="tabpanel">
    <form action="{{ route('admin.update-offline-payment') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label>{{ __('New Image') }} <code>({{ __('Recommended') }}: 210X100 PX)</code></label>
            <div id="image-preview-offline" class="image-preview">
                <label for="image-upload-offline" id="image-label-offline">{{ __('Image') }}</label>
                <input type="file" name="offline_image" id="image-upload-offline">
            </div>

        </div>
        <div class="form-group">
            <label class="d-flex align-items-center">
                <input type="hidden" value="inactive" name="offline_status" class="custom-switch-input">
                <input type="checkbox" value="active" name="offline_status" class="custom-switch-input"
                    {{ $basic_payment?->offline_status == 'active' ? 'checked' : '' }}>
                <span class="custom-switch-indicator"></span>
                <span class="custom-switch-description">{{ __('Status') }}</span>
            </label>
        </div>

        <button class="btn btn-primary">{{ __('Update') }}</button>
    </form>
</div>
