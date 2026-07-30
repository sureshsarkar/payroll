@extends('admin.master_layout')
@section('title')
    <title>{{ __('Edit Page Template Builder') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Edit Page Template Builder') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item active"><a
                            href="{{ route('admin.page-template-builder.index') }}">{{ __('Page Builder') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('Edit Page Template Builder') }} </div>
                </div>
            </div>

            <div class="section-body">
                <div class="mt-4 row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <h4>{{ __('Edit Page Template Builder') }}</h4>

                            </div>
                            <div class="card-body">
                                <form action="{{ route('admin.page-template-builder.update', $page->id) }}" method="post"
                                    enctype="multipart/form-data">
                                    @csrf
                                    @method('PUT')
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="name">{{ __('Category Name') }}<span
                                                        class="text-danger">*</span></label>

                                                <select name="category" class="form-control" required>
                                                    <option value="">{{ __('Select Category') }}</option>
                                                    @foreach ($category as $c)
                                                        <option value="{{ $c->id }}"
                                                            {{ $page->category == $c->id ? 'selected' : '' }}>
                                                            {{ $c->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('category')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                           <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="category">{{ __('Template Name') }}<span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="template_name"
                                                    value="{{ $page->template_name }}" placeholder="" class="form-control">
                                                @error('template_name')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>



                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="question">{{ __('Image') }}<span
                                                        class="text-danger">*</span></label>
                                                <input type="file" id="slug" name="image"
                                                    value="{{ old('image') }}" placeholder="" class="form-control"
                                                    accept=".jpg,.jpeg,.png,.webp">
                                                @error('image')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="question">{{ __('Choose Templete') }}<span
                                                        class="text-danger">*</span></label>
                                                <input type="file" id="slug" name="file"
                                                    value="{{ old('file') }}" placeholder="" class="form-control"
                                                    accept=".blade.php">
                                                @error('file')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="status">{{ __('Status') }}<span
                                                        class="text-danger">*</span></label>
                                                <select name="status" id="" class="form-control">
                                                    <option @selected($page->status == 1) value="1">{{ __('Active') }}
                                                    </option>
                                                    <option @selected($page->status == 0) value="0">
                                                        {{ __('Inactive') }}</option>
                                                </select>
                                                @error('status')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="">
                                        <x-admin.save-button :text="__('Save')">
                                        </x-admin.save-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('js')
    <script></script>
@endpush
