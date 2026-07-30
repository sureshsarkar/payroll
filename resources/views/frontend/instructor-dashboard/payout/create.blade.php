@extends('frontend.instructor-dashboard.layouts.master')


<style>
    /* ── Payout Card ─────────────────────────────────────────── */
.payout-card {
  background: #ffffff;
  border: 1px solid #e8e8e8;
  border-radius: 16px;
  overflow: hidden;
  font-family: 'DM Sans', sans-serif;
  max-width: 720px;
}

/* ── Header ─────────────────────────────────────────────── */
.payout-card__header {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 1.4rem 1.75rem;
  border-bottom: 1px solid #f0f0f0;
}

.payout-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px; 
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
      background: #ecfdf5;
          color: #10b981;
}

.payout-icon svg {
  width: 20px;
  height: 20px;
  stroke: #0F6E56;
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.payout-card__header h4 {
  font-size: 15px;
  font-weight: 600;
  color: #111;
  margin: 0 0 3px;
}

.payout-card__header p {
  font-size: 13px;
  color: #888;
  margin: 0;
}

/* ── Info Grid ───────────────────────────────────────────── */
.payout-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1px;
  background: #efefef;
}

.info-cell {
  background: #fff;
  padding: 1rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cell-label {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #aaa;
}

.info-cell strong {
  font-size: 14px;
  font-weight: 600;
  color: #111;
}

.info-cell code {
  font-family: 'DM Mono', monospace;
  font-size: 13px;
  background: #ecfdf5;
  color: #10b981;
  padding: 3px 9px;
  border-radius: 6px;
  letter-spacing: 0.04em;
  width: fit-content;
}

/* ── Badge ───────────────────────────────────────────────── */
.badge-success {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #ecfdf5;
  color: #10b981 !important;
  font-size: 12px;
  font-weight: 500;
  padding: 4px 11px;
  border-radius: 20px;
  width: fit-content;
}

.badge-success::before {
  content: '';
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #1D9E75;
  flex-shrink: 0;
}

/* ── Form ────────────────────────────────────────────────── */
.payout-form {
  padding: 1.5rem 1.75rem 0;
}

.payout-form .form-grp label {
  display: block;
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.04em;
  color: #666;
  margin-bottom: 8px;
}

.input-prefix-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.input-prefix-wrap .prefix {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  padding: 0 14px;
  font-size: 14px;
  font-weight: 500;
  color: #888;
  border-right: 1px solid #eee;
  pointer-events: none;
  z-index: 1;
}

.input-prefix-wrap input[type="number"] {
  width: 100%;
  height: 48px;
  padding: 0 14px 0 56px;
  font-family: 'DM Sans', sans-serif;
  font-size: 15px;
  font-weight: 500;
  color: #111;
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 10px;
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  -moz-appearance: textfield;
}

.input-prefix-wrap input[type="number"]::-webkit-inner-spin-button,
.input-prefix-wrap input[type="number"]::-webkit-outer-spin-button {
  -webkit-appearance: none;
}

.input-prefix-wrap input[type="number"]:focus {
  border-color: #1D9E75;
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.12);
}

.input-prefix-wrap input[type="number"]::placeholder {
  color: #bbb;
  font-weight: 400;
}

/* ── Footer ──────────────────────────────────────────────── */
.payout-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 1.25rem 0;
  margin-top: 1.25rem;
  border-top: 1px solid #f0f0f0;
}

.fee-note {
  font-size: 12px;
  color: #aaa;
}

.fee-note strong {
  color: #666;
  font-weight: 500;
}

/* ── Submit Button ───────────────────────────────────────── */
.btn-payout {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #0F6E56;
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 0 20px;
  height: 44px;
  font-family: 'DM Sans', sans-serif;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, transform 0.12s;
  white-space: nowrap;
}

.btn-payout:hover {
  background: #085041;
  transform: translateY(-1px);
}

.btn-payout:active {
  transform: translateY(0);
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 540px) {
  .payout-info-grid {
    grid-template-columns: 1fr;
  }

  .payout-footer {
    flex-direction: column;
    align-items: stretch;
  }

  .btn-payout {
    justify-content: center;
    width: 100%;
  }
}
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] .payout-card { background:#1e293b; border-color:#2a3a55; }
html[data-theme="dark"] .payout-card__header { border-bottom-color:#2a3a55; }
html[data-theme="dark"] .payout-card__header h4 { color:#e2e8f0; }
html[data-theme="dark"] .payout-card__header p { color:#94a3b8; }
html[data-theme="dark"] .payout-info-grid { background:#2a3a55; }
html[data-theme="dark"] .info-cell { background:#1e293b; }
html[data-theme="dark"] .cell-label { color:#94a3b8; }
html[data-theme="dark"] .info-cell strong { color:#e2e8f0; }
html[data-theme="dark"] .payout-form .form-grp label { color:#94a3b8; }
html[data-theme="dark"] .input-prefix-wrap .prefix { color:#94a3b8; border-right-color:#2a3a55; }
html[data-theme="dark"] .input-prefix-wrap input[type="number"] { color:#e2e8f0; background:#17233a; border-color:#2a3a55; }
html[data-theme="dark"] .input-prefix-wrap input[type="number"]::placeholder { color:#94a3b8; }
html[data-theme="dark"] .payout-footer { border-top-color:#2a3a55; }
html[data-theme="dark"] .fee-note { color:#94a3b8; }
html[data-theme="dark"] .fee-note strong { color:#94a3b8; }
</style>
@section('dashboard-contents')
    <div class="dashboard__content-wrap dashboard__content-wrap-two">

        <div class="col-12">
            <div class="alert alert-primary d-flex align-items-center" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                    class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16" role="img"
                    aria-label="Warning:">
                    <path
                        d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z" />
                </svg>
                <div>
                    {{ __('You can change your payment method informations from your profile settings.') }}
                </div>
            </div>
        </div>
        <div class="dashboard__content-title">
            <h4 class="title">{{ __('Earnings') }}</h4>
        </div>

        <div class="idash-grid mt-3">
            <a href="#" class="istat-card istat-card--green">
                <div class="istat-top">
                    <div class="istat-icon"><i class="bi bi-currency-rupee"></i></div>
                    <span class="istat-badge">Current Balance</span>
                </div>
                <div class="istat-num odometer-0" data-count="{{ currency(userAuth()->wallet_balance) }}">
                    {{ currency(userAuth()->wallet_balance) }}</div>
                <div class="istat-label">{{ __('Current Balance') }}</div>
            </a>

            <a href="#" class="istat-card istat-card--amber">
                <div class="istat-top">
                    <div class="istat-icon"><i class="flaticon-mortarboard"></i></div>
                    <span class="istat-badge">Courses Sold</span>
                </div>
                <div class="istat-num odometer" data-count="{{ $totalCourseSold }}">{{ $totalCourseSold }}</div>
                <div class="istat-label">{{ __('Courses Sold') }}</div>
            </a>

            <a href="#" class="istat-card istat-card--blue">
                <div class="istat-top">
                    <div class="istat-icon">
                        <i class="bi bi-bank"></i>
                    </div>
                    <span class="istat-badge">Total Payout</span>
                </div>
                <div class="istat-num" data-count="{{ $totalWithdraw }}">{{ currency($totalWithdraw) }}</div>
                <div class="istat-label">{{ __('Total Payout') }}</div>
            </a>
        </div>
    </div>

   @if (!empty($gateway))
<div class="dashboard__content-wrap mt-3">
  <div class="payout-card">

    <div class="payout-card__header">
      <div class="payout-icon">
        <i class="bi bi-currency-dollar"></i>
      </div>
      <div>
        <h4>{{ __('Create a Request') }}</h4>
        <p>{{ __('Withdraw your earnings to your linked account') }}</p>
      </div>
    </div>

    <div class="payout-info-grid">
      <div class="info-cell">
        <span class="cell-label">{{ __('Default Gateway') }}</span>
        <span class="badge badge-success">{{ $gateway->payout_account ?? '' }}</span>
      </div>
      <div class="info-cell">
        <span class="cell-label">{{ __('Gateway Information') }}</span>
        <code>{!! nl2br(clean($gateway->payout_information ?? '')) !!}</code>
      </div>
      <div class="info-cell">
        <span class="cell-label">{{ __('Minimum Payout') }}</span>
        <strong>{{ currency($withdrawMethod->min_amount ?? null) }}</strong>
      </div>
      <div class="info-cell">
        <span class="cell-label">{{ __('Maximum Payout') }}</span>
        <strong>{{ currency($withdrawMethod->max_amount ?? '') }}</strong>
      </div>
    </div>

    <form action="{{ route('instructor.payout.store') }}" method="POST" class="payout-form">
      @csrf
      @php($payoutCurrencyIcon = session('currency_icon') ?: '₹')
      <div class="form-grp">
        <label>{{ __('Withdraw Amount') }} ({{ __('in') }} {{ $payoutCurrencyIcon }})</label>
        <div class="input-prefix-wrap">
          <span class="prefix">{{ $payoutCurrencyIcon }}</span>
          <input type="number" name="amount" placeholder="0.00"
                 value="{{ old('amount') }}" step="0.01" min="0" required>
        </div>
      </div>
      <div class="payout-footer">
        <span class="fee-note">{{ __('Processing via') }} <strong>Paytm</strong> · {{ __('Instant transfer') }}</span>
        <button type="submit" class="btn btn-payout">
          {{ __('Request Payout') }}
        </button>
      </div>
    </form>

  </div>
</div>
@endif
@endsection
