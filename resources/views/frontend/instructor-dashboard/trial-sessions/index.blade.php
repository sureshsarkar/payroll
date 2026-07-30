@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<style>
    .ts { --ts-primary:#10b981; --ts-ink:#1c1a4a; --ts-muted:#64748b; --ts-line:#e6e8f0; }
    .ts * { box-sizing:border-box; }
    .ts-head { margin-bottom:18px; }
    .ts-head h4 { font-size:20px; font-weight:750; color:var(--ts-ink); margin:0; display:flex; align-items:center; gap:9px; }
    .ts-head p { color:var(--ts-muted); font-size:13.5px; margin:6px 0 0; }

    /* tabs */
    .ts-tabs { display:flex; gap:4px; border-bottom:1px solid var(--ts-line); margin-bottom:22px; }
    .ts-tab { padding:10px 16px; font-size:14px; font-weight:600; color:var(--ts-muted); text-decoration:none;
              border-bottom:2px solid transparent; margin-bottom:-1px; display:inline-flex; align-items:center; gap:7px; }
    .ts-tab:hover { color:var(--ts-ink); }
    .ts-tab.is-active { color:var(--ts-primary); border-bottom-color:var(--ts-primary); }
    .ts-tab .ts-badge { background:#ecfdf5; color:#065f46; font-size:11px; font-weight:700; border-radius:100px; padding:1px 8px; }

    .ts-alert { border-radius:10px; padding:11px 14px; font-size:13.5px; margin-bottom:16px; }
    .ts-alert.ok { background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; }
    .ts-alert.err { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; }
    .ts-alert.err ul { margin:0; padding-left:18px; }

    .ts-card { background:#fff; border:1px solid var(--ts-line); border-radius:14px; box-shadow:0 1px 2px rgba(16,24,40,.04); }
    .ts-card__b { padding:20px 22px; }
    .ts-sec { font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; margin:0 0 14px; }
    .ts-sec.mt { margin-top:26px; padding-top:22px; border-top:1px solid #f1f3f9; }

    .ts-enable { display:flex; align-items:center; gap:14px; background:linear-gradient(180deg,#f5f7ff,#fbfcff);
                 border:1px solid #e0e5ff; border-radius:12px; padding:14px 16px; margin-bottom:24px; }
    .ts-enable__txt b { display:block; font-size:14.5px; color:var(--ts-ink); font-weight:700; }
    .ts-enable__txt span { font-size:12.5px; color:var(--ts-muted); }

    .ts-field { margin-bottom:16px; }
    .ts-field > label { display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px; }
    .ts-input { width:100%; border:1px solid #e2e8f0; border-radius:10px; padding:10px 13px; font-size:14px; color:#0f172a;
                background:#fff; font-family:inherit; transition:border-color .12s, box-shadow .12s; }
    .ts-input:focus { outline:none; border-color:var(--ts-primary); box-shadow:0 0 0 3px rgba(16, 185, 129,.12); }
    textarea.ts-input { resize:vertical; min-height:64px; }
    .ts-help { font-size:12px; color:#94a3b8; margin-top:6px; display:block; }
    .ts-row { display:flex; gap:14px; }
    .ts-row > * { flex:1; }
    .ts-row .ts-w-sym { flex:0 0 96px; }

    .ts-check { display:flex; align-items:center; gap:11px; font-size:13.5px; color:#334155; margin-bottom:18px; font-weight:500; }

    /* switch */
    .ts-switch { position:relative; display:inline-flex; width:42px; height:24px; flex:0 0 auto; cursor:pointer; }
    .ts-switch input { position:absolute; opacity:0; width:100%; height:100%; margin:0; cursor:pointer; }
    .ts-switch span { position:absolute; inset:0; background:#cbd5e1; border-radius:100px; transition:background .15s; }
    .ts-switch span::after { content:""; position:absolute; top:3px; left:3px; width:18px; height:18px; border-radius:50%;
                             background:#fff; transition:transform .15s; box-shadow:0 1px 2px rgba(0,0,0,.2); }
    .ts-switch input:checked + span { background:#22c55e; }
    .ts-switch input:checked + span::after { transform:translateX(18px); }
    .ts-switch.sm { width:36px; height:20px; }
    .ts-switch.sm span::after { width:15px; height:15px; }
    .ts-switch.sm input:checked + span::after { transform:translateX(15px); }

    .ts-save { background:var(--ts-primary); color:#fff; border:none; border-radius:10px; padding:11px 22px;
               font-weight:650; font-size:14px; cursor:pointer; transition:opacity .12s; }
    .ts-save:hover { opacity:.92; }

    /* slots */
    .ts-slot-add { display:flex; gap:8px; margin-bottom:16px; }
    .ts-slot-add .ts-input { flex:1; }
    .ts-add-btn { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; border-radius:10px; padding:0 18px;
                  font-weight:650; font-size:13.5px; cursor:pointer; white-space:nowrap; }
    .ts-add-btn:hover { background:#e0e7ff; }

    .ts-slot { display:flex; align-items:center; gap:8px; padding:7px 8px 7px 10px; border:1px solid var(--ts-line);
               border-radius:11px; margin-bottom:8px; background:#fff; }
    .ts-slot:hover { border-color:#d3d8e8; }
    .ts-slot__edit { display:flex; align-items:center; gap:10px; flex:1; min-width:0; margin:0; }
    .ts-slot__edit .ts-input { flex:1; min-width:0; padding:8px 11px; font-size:13.5px; }
    .ts-slot__handle { color:#cbd5e1; font-size:14px; cursor:default; flex:0 0 auto; }
    .ts-slot__actions { display:flex; align-items:center; gap:6px; margin:0; }
    .ts-ic { width:34px; height:34px; border-radius:9px; border:1px solid #e2e8f0; background:#fff; display:grid;
             place-items:center; cursor:pointer; color:#64748b; font-size:13px; transition:background .12s; }
    .ts-ic:hover { background:#f8fafc; }
    .ts-ic.save { color:#16a34a; border-color:#bbf7d0; background:#f0fdf4; }
    .ts-ic.save:hover { background:#dcfce7; }
    .ts-ic.del { color:#dc2626; border-color:#fecaca; background:#fef2f2; }
    .ts-ic.del:hover { background:#fee2e2; }
    .ts-empty { font-size:13px; color:#94a3b8; text-align:center; padding:22px 0; border:1px dashed var(--ts-line); border-radius:11px; }

    @media (max-width:575px){ .ts-slot__edit .ts-switch { display:none; } }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] .ts {
    --ts-ink:#e2e8f0;
    --ts-muted:#94a3b8;
    --ts-line:#2a3a55;
}
html[data-theme="dark"] .ts-card { background:#1e293b; box-shadow:none; }
html[data-theme="dark"] .ts-input { background:#1e293b; color:#e2e8f0; border-color:#2a3a55; }
html[data-theme="dark"] .ts-slot { background:#1e293b; }
html[data-theme="dark"] .ts-ic { background:#1e293b; color:#94a3b8; border-color:#2a3a55; }
html[data-theme="dark"] .ts-ic:hover { background:#17233a; }
</style>

<div class="ts">
    <div class="ts-head">
        <h4><i class="fas fa-calendar-check"></i> {{ __('Trial Sessions') }}</h4>
        <p>{{ __('Configure the “Book Your Trial Session” popup on your website, manage time slots, and track enquiries & payments.') }}</p>
    </div>

    <nav class="ts-tabs">
        <a href="{{ route('instructor.trial-sessions.index') }}" class="ts-tab is-active"><i class="fas fa-sliders-h"></i> {{ __('Settings') }}</a>
        <a href="{{ route('instructor.trial-sessions.enquiries.index') }}" class="ts-tab">{{ __('Enquiries') }} <span class="ts-badge">{{ $counts['enquiries'] }}</span></a>
        <a href="{{ route('instructor.trial-sessions.payments.index') }}" class="ts-tab">{{ __('Payments') }} <span class="ts-badge">{{ $counts['paid'] }}</span></a>
    </nav>

    @if(session('messege'))
        <div class="ts-alert ok">{{ session('messege') }}</div>
    @endif
    @if($errors->any())
        <div class="ts-alert err"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        {{-- ───────────── Settings ───────────── --}}
        <div class="col-lg-7 mb-3">
            <div class="ts-card">
                <div class="ts-card__b">
                    <form method="POST" action="{{ route('instructor.trial-sessions.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="ts-enable">
                            <label class="ts-switch">
                                <input type="checkbox" name="is_enabled" value="1" {{ $settings->is_enabled ? 'checked' : '' }}>
                                <span></span>
                            </label>
                            <div class="ts-enable__txt">
                                <b>{{ __('Show the trial popup on my website') }}</b>
                                <span>{{ __('When off, the popup never appears to visitors.') }}</span>
                            </div>
                        </div>

                        <div class="ts-sec">{{ __('Popup content') }}</div>

                        <div class="ts-field">
                            <label>{{ __('Popup Title') }}</label>
                            <input type="text" name="title" class="ts-input" maxlength="150"
                                   value="{{ old('title', $settings->title) }}" placeholder="{{ \App\Models\CoachTrialSetting::DEFAULT_TITLE }}">
                        </div>
                        <div class="ts-field">
                            <label>{{ __('Popup Subtitle') }}</label>
                            <input type="text" name="subtitle" class="ts-input" maxlength="255"
                                   value="{{ old('subtitle', $settings->subtitle) }}" placeholder="{{ \App\Models\CoachTrialSetting::DEFAULT_SUBTITLE }}">
                        </div>
                        <div class="ts-field">
                            <label>{{ __('Success Message') }}</label>
                            <textarea name="success_message" class="ts-input" rows="2" maxlength="2000"
                                      placeholder="{{ __('Thank you! Your trial session is booked. We will contact you shortly.') }}">{{ old('success_message', $settings->success_message) }}</textarea>
                        </div>

                        <div class="ts-sec mt">{{ __('Pricing & payment') }}</div>

                        <div class="ts-row ts-field">
                            <div class="ts-w-sym">
                                <label>{{ __('Symbol') }}</label>
                                <input type="text" name="currency_icon" class="ts-input" maxlength="8"
                                       value="{{ old('currency_icon', $settings->currency_icon ?: '₹') }}">
                            </div>
                            <div>
                                <label>{{ __('Trial Session Price') }}</label>
                                <input type="number" step="0.01" min="0" name="price" class="ts-input"
                                       value="{{ old('price', (float) $settings->price) }}">
                            </div>
                        </div>
                        <span class="ts-help" style="margin-top:-8px;margin-bottom:14px;">{{ __('Set 0 (or turn off “require payment”) to collect trial enquiries for free.') }}</span>

                        <label class="ts-check">
                            <span class="ts-switch sm"><input type="checkbox" name="require_payment" value="1" {{ $settings->require_payment ? 'checked' : '' }}><span></span></span>
                            {{ __('Require payment before confirming the trial') }}
                        </label>

                        <div class="ts-field">
                            <label>{{ __('Payment Gateway') }}</label>
                            <select name="payment_gateway" class="ts-input">
                                <option value="razorpay" {{ old('payment_gateway', $settings->payment_gateway) === 'razorpay' ? 'selected' : '' }}>Razorpay</option>
                            </select>
                            <span class="ts-help">{{ __('Uses your configured gateway credentials (Enterprise) or the platform default.') }}</span>
                        </div>

                        <div class="ts-sec mt">{{ __('Auto-show behaviour') }}</div>

                        <label class="ts-check">
                            <span class="ts-switch sm"><input type="checkbox" name="auto_show" value="1" {{ $settings->auto_show ? 'checked' : '' }}><span></span></span>
                            {{ __('Automatically open the popup when a visitor lands on my site') }}
                        </label>

                        <div class="ts-row">
                            <div class="ts-field">
                                <label>{{ __('Show after (seconds)') }}</label>
                                <input type="number" min="0" max="60" name="show_delay_seconds" class="ts-input"
                                       value="{{ old('show_delay_seconds', (int) $settings->show_delay_seconds) }}">
                            </div>
                            <div class="ts-field">
                                <label>{{ __('Show frequency') }}</label>
                                <select name="show_frequency" class="ts-input">
                                    @foreach(['session'=>'Once per visit','daily'=>'Once per day','once_30d'=>'Once per 30 days','always'=>'Every page load'] as $k=>$v)
                                        <option value="{{ $k }}" {{ old('show_frequency', $settings->show_frequency) === $k ? 'selected' : '' }}>{{ __($v) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="ts-save" style="margin-top:8px;">{{ __('Save settings') }}</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ───────────── Time slots ───────────── --}}
        <div class="col-lg-5 mb-3">
            <div class="ts-card">
                <div class="ts-card__b">
                    <div class="ts-sec" style="margin-bottom:4px;">{{ __('Time Slots') }}</div>
                    <p class="ts-help" style="margin-bottom:16px;">{{ __('These appear in the popup’s Time Slot dropdown, e.g. “05:00 AM - Mansi Rawat”.') }}</p>

                    <form method="POST" action="{{ route('instructor.trial-sessions.slots.store') }}" class="ts-slot-add">
                        @csrf
                        <input type="text" name="label" required maxlength="190" class="ts-input" placeholder="{{ __('e.g. 06:00 AM - Rajesh Chauhan') }}">
                        <button class="ts-add-btn"><i class="fas fa-plus"></i> {{ __('Add') }}</button>
                    </form>

                    @forelse($slots as $slot)
                        <div class="ts-slot">
                            <span class="ts-slot__handle"><i class="fas fa-grip-vertical"></i></span>
                            <form method="POST" action="{{ route('instructor.trial-sessions.slots.update', $slot->id) }}" class="ts-slot__edit">
                                @csrf @method('PUT')
                                <input type="text" name="label" value="{{ $slot->label }}" maxlength="190" class="ts-input">
                                <label class="ts-switch sm" title="{{ __('Active') }}">
                                    <input type="checkbox" name="is_active" value="1" {{ $slot->is_active ? 'checked' : '' }}><span></span>
                                </label>
                                <button class="ts-ic save" title="{{ __('Save') }}"><i class="fas fa-check"></i></button>
                            </form>
                            <form method="POST" action="{{ route('instructor.trial-sessions.slots.destroy', $slot->id) }}"
                                  class="ts-slot__actions" onsubmit="return confirm('{{ __('Delete this slot?') }}');">
                                @csrf @method('DELETE')
                                <button class="ts-ic del" title="{{ __('Delete') }}"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        </div>
                    @empty
                        <div class="ts-empty">{{ __('No time slots yet. Add your first above.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
