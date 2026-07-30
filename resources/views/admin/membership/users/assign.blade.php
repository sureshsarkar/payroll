@extends('admin.master_layout')
@section('title')<title>{{ __('Assign Coach Plan') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-user-tag"></i> {{ __('Assign Coach Plan') }}</h1>
            <div class="section-header-breadcrumb">
                <a href="{{ route('admin.user-memberships.billing') }}" class="btn btn-outline-primary"><i class="fas fa-chart-line"></i> {{ __('Coach billing') }}</a>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-muted">{{ __('Assign or change a coach\'s active pricing plan. This replaces their current active plan immediately and is audit-logged.') }}</p>
                <form method="POST" action="{{ route('admin.user-memberships.assign') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="coach_id">{{ __('Coach') }} <span class="text-danger">*</span></label>
                                <select name="coach_id" id="coach_id" class="form-control" required>
                                    <option value="">{{ __('Select coach') }}</option>
                                    @foreach ($coaches as $c)
                                        <option value="{{ $c->id }}" {{ old('coach_id')==$c->id?'selected':'' }}>{{ $c->name }} ({{ $c->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="plan_id">{{ __('Plan') }} <span class="text-danger">*</span></label>
                                <select name="plan_id" id="plan_id" class="form-control" required>
                                    <option value="">{{ __('Select plan') }}</option>
                                    @foreach ($plans as $p)
                                        <option value="{{ $p->id }}" {{ old('plan_id')==$p->id?'selected':'' }}>
                                            {{ $p->name }} — {{ currency($p->price) }}
                                            @if($p->platform_commission_rate !== null) · {{ (0+$p->platform_commission_rate) }}%@endif
                                            @if(!$p->isUnlimitedStudents()) · {{ number_format($p->student_capacity) }} {{ __('students') }}@else · {{ __('unlimited') }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> {{ __('Assign plan') }}</button>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection
