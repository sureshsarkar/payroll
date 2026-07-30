@extends('admin.master_layout')
@section('title')
    <title>{{ __('Edit Category') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Edit Category') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item active"><a
                            href="{{ route('admin.page-template-category.index') }}">{{ __('Category') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('Edit Category') }} </div>
                </div>
            </div>
            
            <div class="section-body">
                <div class="mt-4 row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <h4>{{ __('Edit Category') }}</h4>

                            </div>
                            <div class="card-body">
                                <form
                                    action="{{ route('admin.page-template-category.update',$page->id) }}"
                                    method="post">
                                    @csrf
                                    @method('PUT')
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="name">{{ __('Category Name') }}<span
                                                        class="text-danger">*</span></label>
                                                <input type="text" data-translate="true" id="name" name="name"
                                                    value="{{ $page->name }}" placeholder=""
                                                    class="form-control">
                                                @error('name')
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
    <script>
   
 
    </script>
@endpush
