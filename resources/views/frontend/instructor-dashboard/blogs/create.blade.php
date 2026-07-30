@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<div class="corp-page">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-pen-nib"></i> {{ __('New blog post') }}</h4>
            <p>{{ __('Write a post for your website. Save as draft or publish it live.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.blogs.index') }}" class="btn-corp-light"><i class="fas fa-arrow-left"></i> {{ __('Back') }}</a>
        </div>
    </div>

    <div class="corp-form-card">
        <div class="corp-form-card__body">
            <form action="{{ route('instructor.blogs.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('frontend.instructor-dashboard.blogs._form')
                <div style="margin-top:18px;display:flex;gap:10px;">
                    <button type="submit" class="btn-corp-primary"><i class="fas fa-save"></i> {{ __('Save post') }}</button>
                    <a href="{{ route('instructor.blogs.index') }}" class="btn-corp-light">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
