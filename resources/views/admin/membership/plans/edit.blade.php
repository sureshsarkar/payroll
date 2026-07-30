@extends('admin.master_layout')
@section('title')<title>{{ __('Edit Plan') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <div class="section-header-back"><a href="{{ route('admin.membership-plans.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a></div>
            <h1>{{ __('Edit Plan') }}: {{ $plan->name }}</h1>
        </div>
        <div class="card"><div class="card-body">
            <form method="POST" action="{{ route('admin.membership-plans.update', $plan->id) }}">
                @csrf @method('PUT')
                @include('admin.membership.plans._form')
                <button class="btn btn-success"><i class="fas fa-save"></i> {{ __('Save changes') }}</button>
            </form>
        </div></div>
    </section>
</div>
@endsection
