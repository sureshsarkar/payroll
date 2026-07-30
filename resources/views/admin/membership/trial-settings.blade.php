@extends('admin.master_layout')
@section('title')<title>{{ __('Coach Trial Settings') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-hourglass-half"></i> {{ __('Coach Trial Settings') }}</h1>
        </div>

        <div class="section-body">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h4>{{ __('Free trial configuration') }}</h4>
                        </div>
                        <form method="POST" action="{{ route('admin.coach-trial-settings.update') }}">
                            @csrf
                            <div class="card-body">

                                <p class="text-muted" style="font-size:13px;">
                                    {{ __('Controls the automatic free trial every new coach receives. Changes apply to coaches granted a trial from now on; existing trials keep their current end date.') }}
                                </p>

                                <div class="form-group row align-items-center">
                                    <label class="col-sm-4 col-form-label">{{ __('Enable free trial') }}</label>
                                    <div class="col-sm-8">
                                        <label class="custom-switch mt-2" style="padding-left:0;">
                                            <input type="checkbox" name="enabled" value="1" class="custom-switch-input"
                                                {{ !empty($config['enabled']) ? 'checked' : '' }}>
                                            <span class="custom-switch-indicator"></span>
                                            <span class="custom-switch-description">{{ __('New coaches start on a trial automatically') }}</span>
                                        </label>
                                        <small class="d-block text-muted">{{ __('Off = coaches must pick a paid plan immediately.') }}</small>
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">{{ __('Trial length (days)') }}</label>
                                    <div class="col-sm-8">
                                        <input type="number" name="days" min="0" max="365" required
                                            class="form-control @error('days') is-invalid @enderror"
                                            value="{{ old('days', $config['days']) }}">
                                        @error('days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="text-muted">{{ __('0 = unlimited (never expires).') }}</small>
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">{{ __('Grace period (days)') }}</label>
                                    <div class="col-sm-8">
                                        <input type="number" name="grace" min="0" max="90" required
                                            class="form-control @error('grace') is-invalid @enderror"
                                            value="{{ old('grace', $config['grace']) }}">
                                        @error('grace')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="text-muted">{{ __('Soft window AFTER expiry before the gate kicks in. Coach keeps full access during grace, with a warning.') }}</small>
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">{{ __('After trial + grace') }}</label>
                                    <div class="col-sm-8">
                                        @php $after = old('after', $config['after']); @endphp
                                        <select name="after" class="form-control">
                                            <option value="soft" {{ $after === 'soft' ? 'selected' : '' }}>{{ __('Soft gate — dashboard stays, premium actions blocked (recommended)') }}</option>
                                            <option value="hard" {{ $after === 'hard' ? 'selected' : '' }}>{{ __('Hard gate — whole coach panel blocked until they subscribe') }}</option>
                                            <option value="none" {{ $after === 'none' ? 'selected' : '' }}>{{ __('No gate — warn only, never block') }}</option>
                                        </select>
                                    </div>
                                </div>

                            </div>
                            <div class="card-footer text-right">
                                <button type="submit" class="btn btn-primary">{{ __('Save settings') }}</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h4>{{ __('Current behaviour') }}</h4></div>
                        <div class="card-body" style="font-size:13px;">
                            <p>{{ __('A new coach gets') }}
                                <strong>{{ $config['days'] > 0 ? $config['days'].' '.__('days') : __('unlimited') }}</strong>
                                {{ __('free, then a') }}
                                <strong>{{ $config['grace'] }} {{ __('day') }}</strong> {{ __('grace window.') }}</p>
                            <p class="mb-0">{{ __('After that:') }}
                                <strong>
                                    @switch($config['after'])
                                        @case('soft') {{ __('premium actions are gated') }} @break
                                        @case('hard') {{ __('the panel is fully gated') }} @break
                                        @default {{ __('no gating (warn only)') }}
                                    @endswitch
                                </strong>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
