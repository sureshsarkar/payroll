@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@php
    use App\Services\Payment\GatewayFieldRegistry;
    $brand = ['razorpay' => '#0c2451', 'stripe' => '#635bff', 'paypal' => '#003087'];
@endphp

<div class="cpg-wrap">
    <div class="cpg-head">
        <div>
            <div class="cpg-title">
                <h4>{{ __('Payment Gateway') }}</h4>
                <span class="cpg-ent"><i class="bi bi-patch-check-fill"></i> {{ __('Enterprise') }}</span>
            </div>
            <p class="cpg-sub">{{ __('Payments for your courses settle into your own merchant account. Secrets are encrypted and shown masked — leave a secret field blank to keep the saved value.') }}</p>
        </div>
    </div>

    <div class="cpg-priority-bar">
        <i class="bi bi-shield-check"></i>
        <span>{{ __('When a student buys your course, your active gateway here is used. If left unconfigured, the platform default is used automatically.') }}</span>
    </div>

    @foreach ($gateways as $gateway)
        @php
            $cfg    = $configs[$gateway] ?? null;
            $spec   = GatewayFieldRegistry::spec($gateway);
            $masked = $cfg ? $cfg->maskedCredentials() : [];
            $isOn   = $cfg && $cfg->status === 'active';
        @endphp

        <form method="POST" action="{{ route('instructor.payment-gateways.update', $gateway) }}"
              enctype="multipart/form-data" class="cpg-card">
            @csrf
            @method('PUT')

            <div class="cpg-card-head">
                <div class="cpg-gw">
                    <span class="cpg-gw-icon" style="background:{{ $brand[$gateway] ?? '#374151' }};"><i class="bi bi-credit-card-2-front-fill"></i></span>
                    <strong>{{ GatewayFieldRegistry::label($gateway) }}</strong>
                    @if ($cfg)
                        <span class="cpg-badge {{ $isOn ? 'cpg-badge--on' : 'cpg-badge--off' }}">{{ $isOn ? __('Active') : __('Inactive') }}</span>
                    @endif
                </div>
                <select name="status" class="cpg-status">
                    <option value="active" @selected($isOn)>{{ __('Active') }}</option>
                    <option value="inactive" @selected(! $isOn)>{{ __('Inactive') }}</option>
                </select>
            </div>

            <div class="cpg-grid">
                @foreach ($spec['fields'] as $field => $meta)
                    @php
                        $isSecret  = ! empty($meta['secret']);
                        $stored    = $cfg ? ($cfg->credentials[$field] ?? '') : '';
                        $hasStored = trim((string) $stored) !== '';
                        $type      = $meta['type'] ?? 'text';
                    @endphp
                    <div class="cpg-field">
                        <label>
                            {{ $meta['label'] }}@if (! empty($meta['required']))<span class="cpg-req">*</span>@endif
                            @if ($isSecret)<i class="bi bi-lock-fill cpg-lock"></i>@endif
                            @if ($isSecret && $hasStored)<span class="cpg-saved">{{ __('saved') }}</span>@endif
                        </label>

                        @if ($type === 'select')
                            <select name="{{ $field }}">
                                @foreach (($meta['options'] ?? []) as $opt)
                                    <option value="{{ $opt }}" @selected($stored === $opt)>{{ ucfirst($opt) }}</option>
                                @endforeach
                            </select>
                        @elseif ($type === 'color')
                            <input type="color" name="{{ $field }}" value="{{ old($field, $stored ?: '#6d0ce4') }}" class="cpg-color">
                        @elseif ($isSecret)
                            <input type="text" name="{{ $field }}" autocomplete="off" value=""
                                   placeholder="{{ $hasStored ? $masked[$field] : __('Enter value') }}" class="cpg-mono">
                        @else
                            <input type="text" name="{{ $field }}" value="{{ old($field, $stored) }}">
                        @endif

                        @error($field)<small class="cpg-err">{{ $message }}</small>@enderror
                    </div>
                @endforeach

                <div class="cpg-field">
                    <label>{{ __('Gateway charge (%)') }}</label>
                    <input type="number" step="0.01" min="0" max="100" name="charge" value="{{ old('charge', $cfg->charge ?? 0) }}">
                </div>

                <div class="cpg-field">
                    <label>{{ __('Gateway logo') }} <span class="cpg-opt">({{ __('optional') }})</span></label>
                    <input type="file" name="image" accept="image/*">
                    @if ($cfg && $cfg->image)<img src="{{ asset($cfg->image) }}" alt="" class="cpg-logo">@endif
                </div>
            </div>

            <div class="cpg-actions">
                <button type="submit"><i class="bi bi-save"></i> {{ __('Save :gateway', ['gateway' => GatewayFieldRegistry::label($gateway)]) }}</button>
            </div>
        </form>
    @endforeach
</div>

<style>
    .cpg-wrap{max-width:940px;}
    .cpg-head{margin-bottom:14px;}
    .cpg-title{display:flex;align-items:center;gap:10px;}
    .cpg-title h4{margin:0;font-weight:600;}
    .cpg-ent{font-size:11px;padding:3px 10px;border-radius:20px;background:rgba(99,91,255,.12);color:#4f46e5;font-weight:600;}
    .cpg-sub{color:#6b7280;font-size:13px;margin:6px 0 0;}
    .cpg-priority-bar{display:flex;align-items:center;gap:8px;background:#f0f7ff;border:1px solid #d8e7fb;color:#1e4e8c;border-radius:10px;padding:10px 14px;font-size:12.5px;margin-bottom:18px;}
    .cpg-card{background:#fff;border:1px solid #ececf1;border-radius:14px;padding:0;margin-bottom:16px;overflow:hidden;}
    .cpg-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;border-bottom:1px solid #f0f0f4;flex-wrap:wrap;}
    .cpg-gw{display:flex;align-items:center;gap:10px;}
    .cpg-gw strong{font-size:15px;}
    .cpg-gw-icon{width:34px;height:34px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:15px;}
    .cpg-badge{font-size:10px;padding:2px 9px;border-radius:20px;font-weight:600;}
    .cpg-badge--on{background:#e6f4ea;color:#1e7e34;}
    .cpg-badge--off{background:#f1f1f4;color:#6b7280;}
    .cpg-status{max-width:140px;border:1px solid #d1d5db;border-radius:8px;padding:6px 10px;}
    .cpg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;padding:18px;}
    .cpg-field label{display:flex;align-items:center;gap:6px;font-size:12px;color:#6b7280;margin-bottom:5px;}
    .cpg-field input,.cpg-field select{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;}
    .cpg-field input[type=file]{padding:6px;}
    .cpg-mono{font-family:monospace;}
    .cpg-color{max-width:80px;height:38px;padding:4px;}
    .cpg-req{color:#dc2626;}
    .cpg-lock{font-size:10px;color:#9ca3af;}
    .cpg-saved{font-size:9px;padding:1px 7px;border-radius:10px;background:#eef2ff;color:#4f46e5;border:1px solid #e0e3f5;}
    .cpg-opt{color:#9ca3af;font-size:11px;}
    .cpg-err{color:#dc2626;font-size:11px;}
    .cpg-logo{height:26px;margin-top:6px;display:block;}
    .cpg-actions{padding:0 18px 16px;display:flex;justify-content:flex-end;}
    .cpg-actions button{background:#2563eb;color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
    .cpg-actions button:hover{background:#1d4ed8;}
</style>
@endsection
