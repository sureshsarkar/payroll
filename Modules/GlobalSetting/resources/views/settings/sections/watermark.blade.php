<div class="tab-pane fade" id="watermark_tab" role="tabpanel">
    <form action="{{ route('admin.update-video-watermark') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row">

            <div class="form-group col-12">
                <label>{{ __('Video Watermark') }} <code>({{ __('Recommended') }}: 155X40 PX)</code></label>
                <div id="image-preview-4" class="image-preview">
                    <label for="image-upload-3" id="image-label-4">{{ __('Image') }}</label>
                    <input type="file" name="watermark_img" id="image-upload-4">
                </div>
                @error('watermark_img')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-md-4">
                <label for="position">{{ __('Position') }} <code>*</code></label>
                <select name="position" id="position" class="form-control">
                    <option {{ @$setting?->position == 'top_right' ? 'selected' : '' }} value="top_right">
                        {{ __('Top Right') }}</option>
                    <option {{ @$setting?->position == 'top_left' ? 'selected' : '' }} value="top_left">
                        {{ __('Top Left') }}</option>
                    <option {{ @$setting?->position == 'bottom_right' ? 'selected' : '' }} value="bottom_right">
                        {{ __('Bottom Right') }}</option>
                    <option {{ @$setting?->position == 'bottom_left' ? 'selected' : '' }} value="bottom_left">
                        {{ __('Bottom Left') }}</option>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label for="opacity">{{ __('Opacity') }} <code>*</code></label>
                <select name="opacity" id="opacity" class="form-control">
                    <option {{ @$setting?->opacity == '0.1' ? 'selected' : '' }} value="0.1">0.1</option>
                    <option {{ @$setting?->opacity == '0.2' ? 'selected' : '' }} value="0.2">0.2</option>
                    <option {{ @$setting?->opacity == '0.3' ? 'selected' : '' }} value="0.3">0.3</option>
                    <option {{ @$setting?->opacity == '0.4' ? 'selected' : '' }} value="0.4">0.4</option>
                    <option {{ @$setting?->opacity == '0.5' ? 'selected' : '' }} value="0.5">0.5</option>
                    <option {{ @$setting?->opacity == '0.6' ? 'selected' : '' }} value="0.6">0.6</option>
                    <option {{ @$setting?->opacity == '0.7' ? 'selected' : '' }} value="0.7">0.7</option>
                    <option {{ @$setting?->opacity == '0.8' ? 'selected' : '' }} value="0.8">0.8</option>
                    <option {{ @$setting?->opacity == '0.9' ? 'selected' : '' }} value="0.9">0.9</option>
                    <option {{ @$setting?->opacity == '1' ? 'selected' : '' }} value="1">1</option>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label for="max_width">{{ __('Max width') }} <code>*</code></label>
                <div class="input-group">
                    <input class="form-control" type="text" name="max_width" id="max_width"
                        value="{{ @$setting?->max_width ?? '300' }}">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span>PX</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group col-12">
                <label class="d-flex align-items-center">
                    <input type="hidden" value="inactive" name="watermark_status" class="custom-switch-input">
                    <input type="checkbox" value="active" name="watermark_status"
                        class="custom-switch-input" {{ @$setting?->watermark_status == 'active' ? 'checked' : '' }}>
                    <span class="custom-switch-indicator"></span>
                    <span
                        class="custom-switch-description">{{ __('Status') }}</span>
                </label>
            </div>
        </div>


        <button class="btn btn-primary">{{ __('Update') }}</button>
    </form>
</div>
