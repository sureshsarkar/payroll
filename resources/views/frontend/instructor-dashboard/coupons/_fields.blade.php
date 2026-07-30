@php $c = $c ?? null; @endphp
<style>
    .cpf-grp{ margin-bottom:14px; }
    .cpf-grp label{ display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:5px; }
    .cpf-grp label .req{ color:#dc2626; }
    .cpf-grp input, .cpf-grp select{ width:100%; height:42px; border:1px solid #e2e8f0; border-radius:10px; padding:0 12px; font-size:14px; background:#fff; }
    .cpf-grp input:focus, .cpf-grp select:focus{ outline:none; border-color:#10b981; box-shadow:0 0 0 3px rgba(16, 185, 129,.15); }
    .cpf-row{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .cpf-hint{ font-size:11.5px; color:#94a3b8; margin-top:4px; }
</style>

<style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] .cpf-grp label{ color:#e2e8f0; }
    html[data-theme="dark"] .cpf-grp input,
    html[data-theme="dark"] .cpf-grp select{ background:#1e293b; border-color:#2a3a55; color:#e2e8f0; }
</style>

<div class="cpf-grp">
    <label>{{ __('Coupon code') }} <span class="req">*</span></label>
    <input type="text" name="coupon_code" maxlength="32" autocomplete="off" placeholder="{{ __('e.g. SAVE20') }}" value="{{ old('coupon_code', $c?->coupon_code ?? '') }}" style="text-transform:uppercase;letter-spacing:.04em;font-weight:600;">
    <p class="cpf-hint">{{ __('Students type this at checkout on your website.') }}</p>
</div>

<div class="cpf-row">
    <div class="cpf-grp">
        <label>{{ __('Discount') }} (%) <span class="req">*</span></label>
        <input type="number" name="offer_percentage" min="1" max="100" step="1" value="{{ old('offer_percentage', $c?->offer_percentage ? (int) $c->offer_percentage : '') }}">
    </div>
    <div class="cpf-grp">
        <label>{{ __('Min purchase') }} <span class="req">*</span></label>
        <input type="number" name="min_price" min="0" step="0.01" value="{{ old('min_price', $c?->min_price ?? 0) }}">
    </div>
</div>

<div class="cpf-row">
    <div class="cpf-grp">
        <label>{{ __('Expires on') }} <span class="req">*</span></label>
        <input type="date" name="expired_date" min="{{ $minDate }}" value="{{ old('expired_date', $c?->expired_date ? \Carbon\Carbon::parse($c->expired_date)->format('Y-m-d') : '') }}">
    </div>
    <div class="cpf-grp">
        <label>{{ __('Status') }} <span class="req">*</span></label>
        <select name="status">
            <option value="active" {{ old('status', $c?->status ?? 'active') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
            <option value="inactive" {{ old('status', $c?->status ?? '') === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
        </select>
    </div>
</div>

<div class="cpf-row">
    <div class="cpf-grp">
        <label>{{ __('Total usage limit') }}</label>
        <input type="number" name="usage_limit" min="1" step="1" placeholder="{{ __('Unlimited') }}" value="{{ old('usage_limit', $c?->usage_limit ?? '') }}">
        <p class="cpf-hint">{{ __('Leave blank for unlimited.') }}</p>
    </div>
    <div class="cpf-grp">
        <label>{{ __('Per-student limit') }}</label>
        <input type="number" name="per_user_limit" min="1" step="1" placeholder="{{ __('Unlimited') }}" value="{{ old('per_user_limit', $c?->per_user_limit ?? '') }}">
        <p class="cpf-hint">{{ __('Max times one student can use it.') }}</p>
    </div>
</div>
