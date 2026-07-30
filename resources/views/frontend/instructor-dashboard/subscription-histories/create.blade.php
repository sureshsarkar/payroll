@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    <div class="dashboard__content-wrap">

        <div class="dashboard__content-title d-flex justify-content-between">
            <h4 class="title">{{ __('Get Subscription') }}</h4>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="instructor__profile-form-wrap">
                    <form action="{{ route('instructor.subscriptions.store') }}"
                        class="instructor__profile-form course-form" method="post">
                        @csrf
                        <div class="row p-2">
                            <div class="col-md-4">
                                <div class="section-title">{{ __('Subscription Plan') }}*</div>
                                <select name="subscription_id" class="form-control" required>
                                    <option value="">Choose Plan</option>
                                    @foreach ($subscriptionsPlan as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach 
                                </select>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit">{{ __('Save') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
