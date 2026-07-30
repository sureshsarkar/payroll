{{-- M7 fix (2026-05-12) — every <label> now associates with its input
     via for/id. Pre-fix, screen readers couldn't link the labels to
     the fields and clicking the label text didn't focus the input. --}}
<div class="form-group">
    <label for="plan-name">{{ __('Plan name') }} <span class="text-danger">*</span></label>
    <input type="text" id="plan-name" name="name" value="{{ old('name', $plan->name ?? '') }}" class="form-control" required>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label for="plan-role">{{ __('Role') }} <span class="text-danger">*</span></label>
            <select name="role" id="plan-role" class="form-control" required>
                @foreach (['student'=>'Student','instructor'=>'Coach','all'=>'All (both)'] as $val => $label)
                    <option value="{{ $val }}" {{ old('role', $plan->role ?? 'all')===$val?'selected':'' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="plan-price">{{ __('Price') }} <span class="text-danger">*</span></label>
            <input type="number" id="plan-price" step="0.01" min="0" name="price" value="{{ old('price', $plan->price ?? 0) }}" class="form-control" required>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="plan-duration">{{ __('Duration (days, 0 = lifetime)') }} <span class="text-danger">*</span></label>
            <input type="number" id="plan-duration" min="0" max="36500" name="duration_days" value="{{ old('duration_days', $plan->duration_days ?? 30) }}" class="form-control" required>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="plan-status">{{ __('Status') }} <span class="text-danger">*</span></label>
            <select name="status" id="plan-status" class="form-control" required>
                <option value="active" {{ old('status', $plan->status ?? 'active')==='active'?'selected':'' }}>{{ __('Active') }}</option>
                <option value="inactive" {{ old('status', $plan->status ?? '')==='inactive'?'selected':'' }}>{{ __('Inactive') }}</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="plan-sort-order">{{ __('Sort order') }}</label>
            <input type="number" id="plan-sort-order" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" class="form-control">
        </div>
    </div>
</div>
{{-- 2026-06-24 — Pricing & Plan configuration (Super-Admin only). These drive
     the coach's setup fee, platform commission, student capacity and settlement
     behaviour. Coaches can only VIEW these; they can never edit them. --}}
<hr>
<h6 class="mb-2 text-uppercase" style="letter-spacing:.04em;color:#64748b;">{{ __('Pricing & Plan configuration') }}</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label for="plan-tier">{{ __('Tier') }} <span class="text-danger">*</span></label>
            <select name="tier" id="plan-tier" class="form-control" required>
                @foreach (['starter'=>'Starter','medium'=>'Medium','enterprise'=>'Enterprise','custom'=>'Custom'] as $val => $label)
                    <option value="{{ $val }}" {{ old('tier', $plan->tier ?? 'custom')===$val?'selected':'' }}>{{ $label }}</option>
                @endforeach
            </select>
            <small class="text-muted">{{ __('Enterprise enables custom setup + direct settlement.') }}</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="plan-setup-fee">{{ __('One-time setup fee') }}</label>
            <input type="number" id="plan-setup-fee" step="0.01" min="0" name="setup_fee" value="{{ old('setup_fee', $plan->setup_fee ?? 0) }}" class="form-control">
            <div class="custom-control custom-checkbox mt-1">
                <input type="checkbox" class="custom-control-input" id="plan-setup-custom" name="setup_fee_custom" value="1" {{ old('setup_fee_custom', $plan->setup_fee_custom ?? false) ? 'checked' : '' }}>
                <label class="custom-control-label" for="plan-setup-custom">{{ __('Custom (quoted per requirement)') }}</label>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="plan-capacity">{{ __('Student capacity') }}</label>
            <input type="number" id="plan-capacity" min="1" name="student_capacity" value="{{ old('student_capacity', $plan->student_capacity ?? '') }}" class="form-control" placeholder="{{ __('Blank = Unlimited') }}">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label for="plan-commission">{{ __('Platform commission %') }}</label>
            <input type="number" id="plan-commission" step="0.01" min="0" max="100" name="platform_commission_rate" value="{{ old('platform_commission_rate', $plan->platform_commission_rate ?? '') }}" class="form-control" placeholder="{{ __('Blank = use global / coach rate') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="plan-commission-min">{{ __('Commission min % (band)') }}</label>
            <input type="number" id="plan-commission-min" step="0.01" min="0" max="100" name="commission_min_rate" value="{{ old('commission_min_rate', $plan->commission_min_rate ?? '') }}" class="form-control" placeholder="{{ __('Enterprise e.g. 0') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="plan-commission-max">{{ __('Commission max % (band)') }}</label>
            <input type="number" id="plan-commission-max" step="0.01" min="0" max="100" name="commission_max_rate" value="{{ old('commission_max_rate', $plan->commission_max_rate ?? '') }}" class="form-control" placeholder="{{ __('Enterprise e.g. 1') }}">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="plan-payout-required" name="payout_required" value="1" {{ old('payout_required', $plan->payout_required ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="plan-payout-required">{{ __('Payout request required') }}</label>
            </div>
            <small class="text-muted">{{ __('Uncheck for Enterprise direct settlement (no payout requests).') }}</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="plan-direct-settlement" name="direct_settlement" value="1" {{ old('direct_settlement', $plan->direct_settlement ?? false) ? 'checked' : '' }}>
                <label class="custom-control-label" for="plan-direct-settlement">{{ __('Direct settlement into coach/institute bank') }}</label>
            </div>
            <small class="text-muted">{{ __('Commission is still auto-deducted per the rate above.') }}</small>
        </div>
    </div>
</div>
<hr>
<div class="form-group">
    <label for="plan-features">{{ __('Features (one per line)') }} <span class="text-danger">*</span></label>
    <textarea id="plan-features" name="features_text" rows="6" class="form-control" placeholder="Access all courses&#10;Live class invites&#10;Priority support" required>{{ old('features_text', isset($plan) ? implode("\n", $plan->features ?? []) : '') }}</textarea>
</div>
