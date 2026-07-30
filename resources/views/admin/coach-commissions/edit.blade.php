@extends('admin.master_layout')
@section('title')<title>{{ __('Set Commission — :name', ['name' => $coach->name]) }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-percentage"></i> {{ __('Commission — :name', ['name' => $coach->name]) }}</h1>
            <div class="section-header-breadcrumb">
                <a href="{{ route('admin.coach-commissions.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><h4 class="mb-0">{{ __('Commission settings') }}</h4></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.coach-commissions.update', $coach->id) }}">
                            @csrf
                            @method('PUT')

                            <div class="form-group">
                                <label>{{ __('Mode') }}</label>
                                <div class="row">
                                    @php $currentMode = old('mode', $coach->commission_mode ?? 'default'); @endphp
                                    <div class="col-md-6 col-sm-12 mb-2">
                                        <label style="display:flex; align-items:center; gap:8px; padding:10px 14px; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer;">
                                            <input type="radio" name="mode" value="default" {{ $currentMode === 'default' ? 'checked' : '' }}>
                                            <span><strong>{{ __('Use global default') }}</strong><br><small class="text-muted">{{ __('Currently :rate%', ['rate' => $globalRate]) }}</small></span>
                                        </label>
                                    </div>
                                    <div class="col-md-6 col-sm-12 mb-2">
                                        <label style="display:flex; align-items:center; gap:8px; padding:10px 14px; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer;">
                                            <input type="radio" name="mode" value="temporary" {{ $currentMode === 'temporary' ? 'checked' : '' }}>
                                            <span><strong>{{ __('Temporary override') }}</strong><br><small class="text-muted">{{ __('Time-limited concession') }}</small></span>
                                        </label>
                                    </div>
                                    <div class="col-md-6 col-sm-12 mb-2">
                                        <label style="display:flex; align-items:center; gap:8px; padding:10px 14px; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer;">
                                            <input type="radio" name="mode" value="permanent" {{ $currentMode === 'permanent' ? 'checked' : '' }}>
                                            <span><strong>{{ __('Permanent override') }}</strong><br><small class="text-muted">{{ __('Ongoing change') }}</small></span>
                                        </label>
                                    </div>
                                    <div class="col-md-6 col-sm-12 mb-2">
                                        <label style="display:flex; align-items:center; gap:8px; padding:10px 14px; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer; background:#fef9c3;">
                                            <input type="radio" name="mode" value="free" {{ $currentMode === 'free' ? 'checked' : '' }}>
                                            <span><strong>{{ __('Free (0% commission)') }}</strong><br><small class="text-muted">{{ __('Coach keeps 100% of revenue') }}</small></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" id="rate-group">
                                <label for="commission_rate">{{ __('Commission rate (%)') }}</label>
                                <div class="input-group" style="max-width:240px;">
                                    <input type="number" name="commission_rate" id="commission_rate"
                                           class="form-control"
                                           min="0" max="100" step="0.01"
                                           value="{{ old('commission_rate', $coach->commission_rate) }}"
                                           placeholder="{{ __('e.g. 15') }}">
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                                @error('commission_rate')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                                <small class="text-muted">{{ __('Required when mode is temporary or permanent. Ignored for default/free.') }}</small>
                            </div>

                            <div class="form-group">
                                <label for="commission_note">{{ __('Admin note (optional)') }}</label>
                                <input type="text" name="commission_note" id="commission_note"
                                       class="form-control" maxlength="255"
                                       value="{{ old('commission_note', $coach->commission_note) }}"
                                       placeholder="{{ __('e.g. Promo through Q1 2026') }}">
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ __('Save') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><h4 class="mb-0">{{ __('Coach summary') }}</h4></div>
                    <div class="card-body">
                        <p><strong>{{ $coach->name }}</strong><br>
                           <small class="text-muted">{{ $coach->email }}</small></p>
                        <hr>
                        <table class="table table-sm">
                            <tr>
                                <th>{{ __('Wallet balance') }}</th>
                                {{-- AUD-028 — formatMoney (session-rate independent), not currency() --}}
                                <td>{{ formatMoney($coach->wallet_balance ?? 0, $primaryCurrency ?? null) }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Orders (last 30d)') }}</th>
                                <td>{{ $recentEarnings->orders_30d ?? 0 }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Gross (last 30d)') }}</th>
                                {{-- AUD-028 — per-currency, never a cross-currency sum --}}
                                <td>
                                    @php $rbc = !empty($recentByCurrency) ? $recentByCurrency : [['currency'=>$primaryCurrency ?? null,'gross_30d'=>$recentEarnings->gross_30d ?? 0,'is_exception'=>false]]; @endphp
                                    @foreach($rbc as $g)
                                        <div @if($g['is_exception']) class="text-danger" @endif>{{ formatMoneyCur($g['gross_30d'], $g['currency']) }}</div>
                                    @endforeach
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('Current effective rate') }}</th>
                                <td>
                                    <strong>{{ number_format($coach->commission_rate !== null ? (float) $coach->commission_rate : $globalRate, 2) }}%</strong>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
// Hide rate input when mode = default OR free (rate is implicit)
(function () {
    function toggle() {
        const mode = document.querySelector('input[name="mode"]:checked')?.value;
        const rateGroup = document.getElementById('rate-group');
        if (!rateGroup) return;
        rateGroup.style.display = (mode === 'default' || mode === 'free') ? 'none' : '';
    }
    document.querySelectorAll('input[name="mode"]').forEach(r => r.addEventListener('change', toggle));
    toggle();
})();
</script>
@endsection
