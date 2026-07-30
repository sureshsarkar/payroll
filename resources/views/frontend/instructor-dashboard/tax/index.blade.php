@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
{{-- 2026-06-13 — enterprise redesign. All styling is scoped to .tax-ui so it is
     independent of the theme's default form styles (which stretched inputs +
     rendered a bare checkbox). Field names / routes are unchanged. --}}
<div class="tax-ui">
    @include("frontend.instructor-dashboard.tax.partials.ui-styles")

    {{-- Page header --}}
    <div class="tx-head">
        <div>
            <h2>{{ __('Tax Settings') }}</h2>
            <p>{{ __('Configure GST / VAT for your courses. Optional and applies only to you.') }}</p>
        </div>
        <div class="d-flex align-items-center" style="gap:10px;">
            <span class="tx-badge {{ $profile->is_enabled ? 'tx-badge--on' : 'tx-badge--off' }}">
                <i class="fas fa-circle" style="font-size:7px;"></i>
                {{ $profile->is_enabled ? __('Tax enabled') : __('Tax disabled') }}
            </span>
            <a href="{{ route('instructor.tax.report') }}" class="tx-btn tx-btn--ghost tx-btn--sm">
                <i class="fas fa-chart-bar"></i> {{ __('Tax Report') }}
            </a>
        </div>
    </div>

    {{-- ─── Tax profile ─────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('instructor.tax.profile') }}" class="tx-card">
        @csrf
        <div class="tx-card__head">
            <span class="tx-card__ic"><i class="fas fa-building"></i></span>
            <div>
                <h3 class="tx-card__title">{{ __('Tax profile') }}</h3>
                <p class="tx-card__sub">{{ __('Your compliance identity shown on invoices.') }}</p>
            </div>
        </div>
        <div class="tx-card__body">
            <div class="tx-toggle-row">
                <label class="tx-switch" style="margin:0;">
                    <input type="checkbox" name="is_enabled" value="1" @checked($profile->is_enabled)>
                    <span class="tx-slider"></span>
                </label>
                <div>
                    <div class="tx-tt">{{ __('Enable tax on my checkout & invoices') }}</div>
                    <div class="tx-ts">{{ __('When off, your courses are sold with no tax — exactly as before.') }}</div>
                </div>
            </div>

            <div class="tx-grid">
                <div class="tx-field tx-col-2">
                    <label>{{ __('Price model') }} <span class="req">*</span></label>
                    <select name="mode" class="tx-input">
                        <option value="exclusive" @selected(($profile->mode ?? 'exclusive') === 'exclusive')>{{ __('Tax added on top of the price (exclusive)') }}</option>
                        <option value="inclusive" @selected(($profile->mode ?? '') === 'inclusive')>{{ __('Price already includes tax (inclusive)') }}</option>
                    </select>
                    <span class="tx-hint">{{ __('Exclusive: a ₹1000 course charges ₹1180 at 18%. Inclusive: the ₹1000 already contains the tax.') }}</span>
                </div>

                <div class="tx-field tx-col-2">
                    <label>{{ __('Legal / business name') }}</label>
                    <input type="text" class="tx-input" name="legal_name" value="{{ old('legal_name', $profile->legal_name) }}" placeholder="{{ __('e.g. Photon Gears Academy Pvt Ltd') }}">
                </div>

                <div class="tx-field">
                    <label>{{ __('Tax ID label') }}</label>
                    <input type="text" class="tx-input" name="registration_label" value="{{ old('registration_label', $profile->registration_label ?? 'GSTIN') }}" placeholder="GSTIN / VAT No.">
                </div>
                <div class="tx-field">
                    <label>{{ __('Tax ID number') }}</label>
                    <input type="text" class="tx-input" name="registration_number" value="{{ old('registration_number', $profile->registration_number) }}" placeholder="22AAAAA0000A1Z5">
                </div>

                <div class="tx-field">
                    <label>{{ __('Country') }}</label>
                    <input type="text" class="tx-input" name="country" value="{{ old('country', $profile->country) }}" placeholder="India">
                </div>
                <div class="tx-field">
                    <label>{{ __('State / Region') }}</label>
                    <input type="text" class="tx-input" name="state" value="{{ old('state', $profile->state) }}" placeholder="Karnataka">
                </div>

                <div class="tx-field tx-col-2">
                    <label>{{ __('Invoice note') }}</label>
                    <input type="text" class="tx-input" name="invoice_note" value="{{ old('invoice_note', $profile->invoice_note) }}" placeholder="{{ __('Shown on the invoice, e.g. compliance / reverse-charge line') }}">
                </div>
            </div>

            <div class="tx-actions">
                <button type="submit" class="tx-btn tx-btn--primary"><i class="fas fa-check"></i> {{ __('Save tax settings') }}</button>
            </div>
        </div>
    </form>

    {{-- ─── Tax rates ───────────────────────────────────────────── --}}
    <div class="tx-card">
        <div class="tx-card__head">
            <span class="tx-card__ic"><i class="fas fa-percent"></i></span>
            <div>
                <h3 class="tx-card__title">{{ __('Tax rates') }}</h3>
                <p class="tx-card__sub">{{ __('Add one or more rates and mark a default. A course can use a specific rate, otherwise your default applies.') }}</p>
            </div>
        </div>
        <div class="tx-card__body">
            <form method="POST" action="{{ route('instructor.tax.rates.store') }}" class="tx-addrate">
                @csrf
                <div class="tx-grid">
                    <div class="tx-field">
                        <label>{{ __('Name') }} <span class="req">*</span></label>
                        <input type="text" class="tx-input" name="name" placeholder="GST 18%" required>
                    </div>
                    <div class="tx-field">
                        <label>{{ __('Rate %') }} <span class="req">*</span></label>
                        <input type="number" class="tx-input" name="rate" step="0.001" min="0" max="100" placeholder="18" required>
                    </div>
                    <div class="tx-field tx-col-2">
                        <label>{{ __('Components') }} <span style="color:var(--tx-muted);font-weight:500;">({{ __('optional — e.g. CGST/SGST') }})</span></label>
                        <input type="text" class="tx-input" name="components" placeholder="CGST:9, SGST:9">
                        <span class="tx-hint">{{ __('Leave blank for a single rate. If filled, Rate % becomes the sum of components and each shows separately on the invoice.') }}</span>
                    </div>
                </div>
                <div class="tx-addrate__foot">
                    <label class="tx-check"><input type="checkbox" name="is_default" value="1"> {{ __('Set as default') }}</label>
                    <button type="submit" class="tx-btn tx-btn--primary tx-btn--sm"><i class="fas fa-plus"></i> {{ __('Add rate') }}</button>
                </div>
            </form>

            {{-- Per-row edit/delete forms live OUTSIDE the table; inputs associate
                 via the HTML5 form="" attribute. This avoids a <form> spanning <td>
                 cells (which parsers can auto-close, dropping later inputs). --}}
            @foreach ($rates as $rate)
                <form id="rate-form-{{ $rate->id }}" method="POST" action="{{ route('instructor.tax.rates.update', $rate->id) }}" class="d-none">@csrf @method('PUT')</form>
                <form id="rate-del-{{ $rate->id }}" method="POST" action="{{ route('instructor.tax.rates.destroy', $rate->id) }}" class="d-none" onsubmit="return confirm('{{ __('Delete this rate?') }}');">@csrf @method('DELETE')</form>
            @endforeach

            <div class="tx-table-wrap">
                <table class="tx-table">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th><th>{{ __('Rate') }}</th><th>{{ __('Components') }}</th>
                            <th>{{ __('Default') }}</th><th>{{ __('Status') }}</th><th style="text-align:right;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rates as $rate)
                            @php
                                $compStr = collect($rate->components ?? [])
                                    ->map(fn($c) => ($c['name'] ?? '') . ':' . rtrim(rtrim((string)($c['rate'] ?? 0),'0'),'.'))
                                    ->implode(', ');
                                $f = 'rate-form-' . $rate->id;
                            @endphp
                            <tr>
                                <td><input type="text" class="tx-input" form="{{ $f }}" name="name" value="{{ $rate->name }}" style="max-width:150px;"></td>
                                <td><input type="number" class="tx-input" form="{{ $f }}" name="rate" step="0.001" min="0" max="100" value="{{ rtrim(rtrim((string)$rate->rate,'0'),'.') }}" style="max-width:80px;"></td>
                                <td><input type="text" class="tx-input" form="{{ $f }}" name="components" value="{{ $compStr }}" placeholder="—" style="max-width:160px;" title="{{ __('e.g. CGST:9, SGST:9') }}"></td>
                                <td><input type="checkbox" form="{{ $f }}" name="is_default" value="1" @checked($rate->is_default) style="width:16px;height:16px;accent-color:var(--tx-accent);"></td>
                                <td>
                                    <select name="status" form="{{ $f }}" class="tx-input" style="max-width:120px;">
                                        <option value="active" @selected($rate->status==='active')>{{ __('Active') }}</option>
                                        <option value="inactive" @selected($rate->status==='inactive')>{{ __('Inactive') }}</option>
                                    </select>
                                </td>
                                <td style="text-align:right;white-space:nowrap;">
                                    @if ($rate->is_default)<span class="tx-pill tx-pill--default" style="margin-right:6px;"><i class="fas fa-star" style="font-size:9px;"></i>{{ __('Default') }}</span>@endif
                                    <button type="submit" form="{{ $f }}" class="tx-iconbtn tx-iconbtn--save" title="{{ __('Save') }}"><i class="fas fa-save"></i></button>
                                    <button type="submit" form="rate-del-{{ $rate->id }}" class="tx-iconbtn tx-iconbtn--danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="tx-empty"><i class="fas fa-percent" style="opacity:.4;"></i> &nbsp;{{ __('No tax rates yet. Add your first rate above.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
