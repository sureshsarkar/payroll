<div class="tab-pane fade" id="logo_favicon_tab" role="tabpanel">
    <form action="{{ route('admin.update-logo-favicon') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row justify-content-center">

        <div class="form-group col-lg-4">
            <label>{{ __('Logo') }} <code>({{ __('Recommended') }}: 155X40 PX)</code></label>
            <div id="image-preview-1" class="image-preview">
                <label for="image-upload-1" id="image-label-1">{{ __('Image') }}</label>
                <input type="file" name="logo" id="image-upload-1">
            </div>
            @error('logo')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group col-lg-4">
            <label>{{ __('Favicon') }} <code>({{ __('Recommended') }}: 64X64 PX)</code></label>
            <div id="image-preview-2" class="image-preview">
                <label for="image-upload-2" id="image-label-2">{{ __('Image') }}</label>
                <input type="file" name="favicon" id="image-upload-2">
            </div>
            @error('favicon')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group col-lg-4">
            <label>{{ __('Preloader') }} <code>({{ __('Recommended') }}: 40X40 PX)</code></label>
            <div id="image-preview-3" class="image-preview">
                <label for="image-upload-3" id="image-label-3">{{ __('Image') }}</label>
                <input type="file" name="preloader" id="image-upload-3">
            </div>
            @error('preloader')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
        </div>

       
        <button class="btn btn-primary">{{ __('Update') }}</button>
    </form>
</div>