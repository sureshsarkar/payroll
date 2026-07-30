@extends('admin.master_layout')
@section('title')<title>{{ __('Custom Domain Settings') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-cog"></i> {{ __('Custom Domain Settings') }}</h1>
            <div class="section-header-breadcrumb">
                <a href="{{ route('admin.custom-domains.index') }}" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to domains') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.custom-domains.settings.update') }}">
                    @csrf

                    <div class="form-group">
                        <label class="d-block">{{ __('Enable custom-domain feature') }}</label>
                        <label class="custom-switch mt-2">
                            <input type="checkbox" name="custom_domain_enabled" value="1" class="custom-switch-input" @checked($settings->enabled())>
                            <span class="custom-switch-indicator"></span>
                            <span class="custom-switch-description">{{ __('Coaches can add custom domains') }}</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Platform server public IP') }}</label>
                        <input type="text" name="custom_domain_server_ip" class="form-control"
                               value="{{ old('custom_domain_server_ip', $settings->serverIp()) }}"
                               placeholder="e.g. 203.0.113.10">
                        <small class="form-text text-muted">
                            {{ __('The A-record target shown to coaches and verified against. Comma-separate for multiple origins. Leave blank to fall back to the COACH_PLATFORM_IP env value.') }}
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="d-block">{{ __('Require admin approval') }}</label>
                        <label class="custom-switch mt-2">
                            <input type="checkbox" name="custom_domain_requires_approval" value="1" class="custom-switch-input" @checked($settings->requiresApproval())>
                            <span class="custom-switch-indicator"></span>
                            <span class="custom-switch-description">{{ __('Verified domains wait for an admin to approve before going live') }}</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Max custom domains per coach') }}</label>
                        <input type="number" name="custom_domain_max_per_coach" class="form-control" style="max-width:160px;"
                               min="1" max="50" value="{{ old('custom_domain_max_per_coach', $settings->maxPerCoach()) }}">
                    </div>

                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> {{ __('Save settings') }}</button>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection
