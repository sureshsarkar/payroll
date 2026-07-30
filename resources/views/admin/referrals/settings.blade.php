@extends('admin.master_layout')
@section('title')<title>{{ __('Referral Settings') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <div class="section-header-back">
                <a href="{{ route('admin.referrals.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
            <h1>{{ __('Referral Settings') }}</h1>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.referrals.settings.update') }}">
                            @csrf

                            {{-- M7 fix (2026-05-12) — number-input labels now associate via for/id.
                                 The label-wraps-input pattern below for switches is left as-is
                                 (valid HTML; for/id not needed when label nests the input). --}}
                            <h5 class="mb-3"><i class="fas fa-power-off"></i> {{ __('System') }}</h5>
                            <div class="form-group">
                                <label class="custom-switch">
                                    <input type="checkbox" name="enabled" value="1" class="custom-switch-input" {{ $settings->enabled ? 'checked' : '' }}>
                                    <span class="custom-switch-indicator"></span>
                                    <span class="custom-switch-description">{{ __('Referral system enabled') }}</span>
                                </label>
                            </div>

                            <hr>
                            <h5 class="mb-3"><i class="fas fa-coins"></i> {{ __('Reward amounts') }}</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="referral-reward-student">{{ __('When referred user is a Student') }} <span class="text-danger">*</span></label>
                                        <input id="referral-reward-student" type="number" step="0.01" min="0" name="reward_when_referred_is_student" value="{{ old('reward_when_referred_is_student', $settings->reward_when_referred_is_student) }}" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="referral-reward-coach">{{ __('When referred user is a Coach') }} <span class="text-danger">*</span></label>
                                        <input id="referral-reward-coach" type="number" step="0.01" min="0" name="reward_when_referred_is_coach" value="{{ old('reward_when_referred_is_coach', $settings->reward_when_referred_is_coach) }}" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="referral-min-amount">{{ __('Minimum membership cash spend to qualify') }}</label>
                                <input id="referral-min-amount" type="number" step="0.01" min="0" name="min_membership_amount" value="{{ old('min_membership_amount', $settings->min_membership_amount) }}" class="form-control">
                                <small class="text-muted">{{ __('Set to 0 for no minimum. Wallet credits do not count toward this threshold.') }}</small>
                            </div>

                            <hr>
                            <h5 class="mb-3"><i class="fas fa-shield-alt"></i> {{ __('Fraud guards') }}</h5>
                            <div class="form-group">
                                <label class="custom-switch">
                                    <input type="checkbox" name="block_self_referral" value="1" class="custom-switch-input" {{ $settings->block_self_referral ? 'checked' : '' }}>
                                    <span class="custom-switch-indicator"></span>
                                    <span class="custom-switch-description">{{ __('Block self-referral') }}</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="custom-switch">
                                    <input type="checkbox" name="block_same_ip_referral" value="1" class="custom-switch-input" {{ $settings->block_same_ip_referral ? 'checked' : '' }}>
                                    <span class="custom-switch-indicator"></span>
                                    <span class="custom-switch-description">{{ __('Block referrals from same IP') }}</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="custom-switch">
                                    <input type="checkbox" name="require_admin_approval" value="1" class="custom-switch-input" {{ $settings->require_admin_approval ? 'checked' : '' }}>
                                    <span class="custom-switch-indicator"></span>
                                    <span class="custom-switch-description">{{ __('Require admin approval for every reward') }}</span>
                                </label>
                                <small class="text-muted d-block mt-1">{{ __('When ON, rewards stay pending until manually approved on the referrals list.') }}</small>
                            </div>

                            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> {{ __('Save settings') }}</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5><i class="fas fa-info-circle"></i> {{ __('How it works') }}</h5>
                        <p style="font-size:13px; line-height:1.6;">
                            {{ __('Referral rewards are credited to a non-withdrawable referral wallet. The balance can ONLY be applied at membership checkout — it cannot be cashed out via the payouts module.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
