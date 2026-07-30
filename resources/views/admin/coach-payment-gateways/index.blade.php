@extends('admin.master_layout')

@section('title', __('Coach Payment Gateway'))

@section('admin-content')
@php
    use App\Services\Payment\GatewayFieldRegistry;
    $icons = ['razorpay' => 'fas fa-bolt', 'stripe' => 'fab fa-stripe-s', 'paypal' => 'fab fa-paypal'];
@endphp

<div class="main-content">
    <section class="section">
        <div class="section-header justify-content-between">
            <h1>{{ __('Payment Gateways') }}</h1>
            <div class="section-header-breadcrumb">
                <a href="{{ route('admin.customer-show', $coach->id) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to coach') }}
                </a>
            </div>
        </div>

        <div class="section-body">

            <div class="card card-primary">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center flex-wrap" style="gap:14px;">
                        <div class="avatar-corp">{{ strtoupper(mb_substr($coach->name, 0, 1)) }}</div>
                        <div class="mr-auto">
                            <div style="font-weight:600;font-size:15px;">{{ $coach->name }}</div>
                            <div class="text-muted" style="font-size:13px;">{{ $coach->email }}</div>
                        </div>
                        <div class="cpg-priority">
                            <span class="text-muted" style="font-size:12px;">{{ __('Resolution order') }}</span>
                            <div class="cpg-chain">
                                <span class="cpg-chip cpg-chip--muted">{{ __('Coach self-managed') }}</span>
                                <i class="fas fa-angle-right text-muted"></i>
                                <span class="cpg-chip cpg-chip--active">{{ __('Super admin for coach') }}</span>
                                <i class="fas fa-angle-right text-muted"></i>
                                <span class="cpg-chip cpg-chip--muted">{{ __('Platform default') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted mt-2" style="font-size:12.5px;">
                        <i class="fas fa-shield-alt text-success"></i>
                        {{ __('Secrets are encrypted at rest and shown masked. Leave a secret field blank to keep the saved value. If the coach is on Enterprise and configures their own gateway, theirs takes precedence over what you set here.') }}
                    </div>
                </div>
            </div>

            @foreach ($gateways as $gateway)
                @php
                    $cfg    = $configs[$gateway] ?? null;
                    $self   = $selfConfigs[$gateway] ?? null;
                    $spec   = GatewayFieldRegistry::spec($gateway);
                    $masked = $cfg ? $cfg->maskedCredentials() : [];
                    $isOn   = $cfg && $cfg->status === 'active';
                @endphp

                <form action="{{ route('admin.coach-payment-gateways.update', [$coach->id, $gateway]) }}"
                      method="POST" enctype="multipart/form-data" class="cpg-card card">
                    @csrf
                    @method('PUT')

                    <div class="card-header">
                        <h4 class="d-flex align-items-center mb-0" style="gap:9px;">
                            <span class="cpg-gw-icon cpg-gw-{{ $gateway }}"><i class="{{ $icons[$gateway] ?? 'fas fa-credit-card' }}"></i></span>
                            {{ GatewayFieldRegistry::label($gateway) }}
                            @if ($cfg)
                                <span class="badge {{ $isOn ? 'badge-success' : 'badge-secondary' }}" style="font-size:11px;">{{ $isOn ? __('Active') : __('Inactive') }}</span>
                            @endif
                            @if ($self)
                                <span class="badge badge-info" style="font-size:11px;" title="{{ __('This coach also self-manages this gateway') }}">
                                    <i class="fas fa-user-shield"></i> {{ __('coach self-managed') }} · {{ $self->status }}
                                </span>
                            @endif
                        </h4>
                        <div class="card-header-action">
                            <label class="custom-switch mt-2 mb-0">
                                <input type="hidden" name="status" value="inactive">
                                <input type="checkbox" name="status" value="active" class="custom-switch-input" {{ $isOn ? 'checked' : '' }}>
                                <span class="custom-switch-indicator"></span>
                                <span class="custom-switch-description">{{ __('Enabled') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            @foreach ($spec['fields'] as $field => $meta)
                                @php
                                    $isSecret = ! empty($meta['secret']);
                                    $stored   = $cfg ? ($cfg->credentials[$field] ?? '') : '';
                                    $hasStored = trim((string) $stored) !== '';
                                    $type     = $meta['type'] ?? 'text';
                                @endphp
                                <div class="form-group col-md-6">
                                    <label class="d-flex align-items-center" style="gap:6px;">
                                        <span>{{ $meta['label'] }}@if (! empty($meta['required']))<span class="text-danger">*</span>@endif</span>
                                        @if ($isSecret)<i class="fas fa-lock text-muted" style="font-size:11px;"></i>@endif
                                        @if ($isSecret && $hasStored)<span class="badge badge-light border" style="font-size:10px;">{{ __('saved') }}</span>@endif
                                    </label>

                                    @if ($type === 'select')
                                        <select name="{{ $field }}" class="form-control">
                                            @foreach (($meta['options'] ?? []) as $opt)
                                                <option value="{{ $opt }}" @selected($stored === $opt)>{{ ucfirst($opt) }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($type === 'color')
                                        <input type="color" class="form-control" name="{{ $field }}" value="{{ old($field, $stored ?: '#6d0ce4') }}" style="max-width:90px;height:38px;padding:4px;">
                                    @elseif ($isSecret)
                                        <input type="text" class="form-control" name="{{ $field }}" autocomplete="off"
                                               value="" placeholder="{{ $hasStored ? $masked[$field] : __('Enter value') }}">
                                    @else
                                        <input type="text" class="form-control" name="{{ $field }}" value="{{ old($field, $stored) }}">
                                    @endif

                                    @error($field)<small class="text-danger d-block mt-1">{{ $message }}</small>@enderror
                                </div>
                            @endforeach

                            <div class="form-group col-md-6">
                                <label>{{ __('Gateway charge (%)') }}</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control"
                                       name="charge" value="{{ old('charge', $cfg->charge ?? 0) }}">
                            </div>

                            <div class="form-group col-md-6">
                                <label>{{ __('Gateway logo') }} <small class="text-muted">({{ __('optional') }})</small></label>
                                <input type="file" name="image" class="form-control-file" accept="image/*">
                                @if ($cfg && $cfg->image)
                                    <img src="{{ asset($cfg->image) }}" alt="" style="height:26px;margin-top:6px;">
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="card-footer text-right">
                        <button class="btn btn-primary">
                            <i class="fas fa-save"></i> {{ __('Save :gateway', ['gateway' => GatewayFieldRegistry::label($gateway)]) }}
                        </button>
                    </div>
                </form>
            @endforeach

        </div>
    </section>
</div>

<style>
    .avatar-corp{width:44px;height:44px;border-radius:50%;background:#eef0ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:16px;flex:0 0 auto;}
    .cpg-priority{margin-left:auto;text-align:right;}
    .cpg-chain{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:3px;}
    .cpg-chip{font-size:11px;padding:3px 9px;border-radius:20px;white-space:nowrap;}
    .cpg-chip--muted{background:#f1f1f4;color:#6b7280;}
    .cpg-chip--active{background:#e6f4ea;color:#1e7e34;font-weight:600;}
    .cpg-card{border:1px solid #ececf1;}
    .cpg-card .card-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
    .cpg-gw-icon{width:34px;height:34px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex:0 0 auto;}
    .cpg-gw-razorpay{background:#0c2451;}
    .cpg-gw-stripe{background:#635bff;}
    .cpg-gw-paypal{background:#003087;}
    @media (max-width:768px){ .cpg-priority{margin-left:0;text-align:left;width:100%;} }
</style>
@endsection
