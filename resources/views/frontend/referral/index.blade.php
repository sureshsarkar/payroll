@php
    $u = Auth::guard('web')->user();
    $isCoach = $u && $u->role === 'instructor';
    $dashLayout = $isCoach
        ? 'frontend.instructor-dashboard.layouts.master'
        : 'frontend.student-dashboard.layouts.master';
@endphp
@extends($dashLayout)

@section('dashboard-contents')
<div class="mbs-ref-page py-3">

    {{-- ===== HERO ===== --}}
    <div class="mbs-ref-hero">
        <div>
            <h1><i class="fas fa-gift" style="color:#10b981;"></i> {{ __('Refer & Earn') }}</h1>
            <p>{{ __('Share your code or link. When a friend activates their membership, you earn referral credit you can spend on your own membership.') }}</p>
        </div>
        <div class="mbs-ref-balance">
            <div class="mbs-ref-balance__lbl">{{ __('Your wallet') }}</div>
            <div class="mbs-ref-balance__num">{{ currency($wallet['balance']) }}</div>
            <div class="mbs-ref-balance__note">{{ __('Membership credit only · non-withdrawable') }}</div>
        </div>
    </div>

    {{-- ===== CODE + LINK ===== --}}
    <div class="mbs-ref-share">
        <div class="mbs-ref-share__col">
            <div class="mbs-ref-share__lbl">{{ __('Your referral code') }}</div>
            <div class="mbs-ref-share__row">
                <input type="text" readonly value="{{ $code }}" id="mbs-ref-code">
                <button type="button" class="mbs-ref-copy" data-copy="{{ $code }}"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>
            </div>
        </div>
        <div class="mbs-ref-share__col">
            <div class="mbs-ref-share__lbl">{{ __('Your referral link') }}</div>
            <div class="mbs-ref-share__row">
                <input type="text" readonly value="{{ $link }}" id="mbs-ref-link">
                <button type="button" class="mbs-ref-copy" data-copy="{{ $link }}"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>
            </div>
            <div class="mbs-ref-share__social">
                <a href="https://twitter.com/intent/tweet?text={{ urlencode(__('Join me on the platform!')) }}&url={{ urlencode($link) }}" target="_blank" class="mbs-ref-social mbs-ref-social--tw"><i class="fab fa-twitter"></i></a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($link) }}" target="_blank" class="mbs-ref-social mbs-ref-social--fb"><i class="fab fa-facebook-f"></i></a>
                <a href="https://api.whatsapp.com/send?text={{ urlencode($link) }}" target="_blank" class="mbs-ref-social mbs-ref-social--wa"><i class="fab fa-whatsapp"></i></a>
                <a href="mailto:?subject={{ urlencode(__('Join me on the platform')) }}&body={{ urlencode($link) }}" class="mbs-ref-social mbs-ref-social--em"><i class="fas fa-envelope"></i></a>
            </div>
        </div>
    </div>

    {{-- ===== STAT CARDS ===== --}}
    <div class="mbs-ref-stats">
        <div class="mbs-ref-stat">
            <div class="mbs-ref-stat__icon" style="background: linear-gradient(135deg,#10b981,#7a73ff);"><i class="fas fa-users"></i></div>
            <div><div class="mbs-ref-stat__num">{{ $stats['total'] }}</div><div class="mbs-ref-stat__lbl">{{ __('Total referrals') }}</div></div>
        </div>
        <div class="mbs-ref-stat">
            <div class="mbs-ref-stat__icon" style="background: linear-gradient(135deg,#f59e0b,#fbbf24);"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="mbs-ref-stat__num">{{ $stats['pending'] }}</div><div class="mbs-ref-stat__lbl">{{ __('Pending') }}</div></div>
        </div>
        <div class="mbs-ref-stat">
            <div class="mbs-ref-stat__icon" style="background: linear-gradient(135deg,#10b981,#34d399);"><i class="fas fa-check"></i></div>
            <div><div class="mbs-ref-stat__num">{{ $stats['rewarded'] }}</div><div class="mbs-ref-stat__lbl">{{ __('Rewarded') }}</div></div>
        </div>
        <div class="mbs-ref-stat">
            <div class="mbs-ref-stat__icon" style="background: linear-gradient(135deg,#3b82f6,#60a5fa);"><i class="fas fa-coins"></i></div>
            <div><div class="mbs-ref-stat__num">{{ currency($stats['lifetime_earned']) }}</div><div class="mbs-ref-stat__lbl">{{ __('Lifetime earned') }}</div></div>
        </div>
    </div>

    {{-- ===== TWO COLUMNS: REFERRALS + WALLET ===== --}}
    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="mbs-ref-card">
                <div class="mbs-ref-card__hdr">
                    <h3><i class="fas fa-user-friends"></i> {{ __('Recent referrals') }}</h3>
                </div>
                @if ($recentReferrals->count() === 0)
                    <div class="mbs-ref-empty">
                        <i class="fas fa-user-plus"></i>
                        <p>{{ __('No referrals yet. Share your link to invite friends.') }}</p>
                    </div>
                @else
                    <table class="mbs-ref-tbl">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Role') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reward') }}</th></tr></thead>
                        <tbody>
                        @foreach ($recentReferrals as $r)
                            <tr>
                                <td><strong>{{ $r->referred?->name ?? '—' }}</strong><div class="text-muted" style="font-size:11px;">{{ $r->created_at?->diffForHumans() }}</div></td>
                                <td><span class="mbs-ref-chip">{{ $r->referred_role }}</span></td>
                                <td>
                                    @switch($r->status)
                                        @case('rewarded')  <span class="mbs-ref-pill mbs-ref-pill--ok">{{ __('Rewarded') }}</span> @break
                                        @case('pending')   <span class="mbs-ref-pill mbs-ref-pill--warn">{{ __('Pending') }}</span> @break
                                        @case('rejected')  <span class="mbs-ref-pill mbs-ref-pill--bad">{{ __('Rejected') }}</span> @break
                                        @case('reversed')  <span class="mbs-ref-pill mbs-ref-pill--bad">{{ __('Reversed') }}</span> @break
                                    @endswitch
                                </td>
                                <td>{{ $r->reward_amount > 0 ? currency($r->reward_amount) : '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
        <div class="col-lg-5 mb-4">
            <div class="mbs-ref-card">
                <div class="mbs-ref-card__hdr"><h3><i class="fas fa-receipt"></i> {{ __('Wallet activity') }}</h3></div>
                @if ($wallet['transactions']->count() === 0)
                    <div class="mbs-ref-empty"><i class="fas fa-receipt"></i><p>{{ __('No wallet activity yet.') }}</p></div>
                @else
                    <ul class="mbs-ref-feed">
                        @foreach ($wallet['transactions'] as $t)
                            <li>
                                <div class="mbs-ref-feed__row">
                                    <span class="mbs-ref-feed__type">
                                        @switch($t->type)
                                            @case('referral_reward')      <i class="fas fa-arrow-up text-success"></i> {{ __('Reward') }} @break
                                            @case('membership_checkout')  <i class="fas fa-arrow-down text-primary"></i> {{ __('Membership') }} @break
                                            @case('admin_adjustment')     <i class="fas fa-cog text-muted"></i> {{ __('Adjustment') }} @break
                                            @case('reversal')             <i class="fas fa-undo text-danger"></i> {{ __('Reversal') }} @break
                                        @endswitch
                                    </span>
                                    <span class="mbs-ref-feed__amt {{ $t->amount > 0 ? 'positive' : 'negative' }}">
                                        {{ $t->amount > 0 ? '+' : '' }}{{ currency($t->amount) }}
                                    </span>
                                </div>
                                <div class="mbs-ref-feed__desc">{{ $t->description }}</div>
                                <div class="mbs-ref-feed__meta">{{ $t->created_at?->diffForHumans() }} · {{ __('Balance') }}: {{ currency($t->balance_after) }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- ===== MEMBERSHIP CREDIT USAGE ===== --}}
    @if ($membershipUsage->count() > 0)
        <div class="mbs-ref-card mb-4">
            <div class="mbs-ref-card__hdr"><h3><i class="fas fa-shield-alt"></i> {{ __('Membership credit usage') }}</h3></div>
            <table class="mbs-ref-tbl">
                <thead><tr><th>{{ __('Plan') }}</th><th>{{ __('Cash paid') }}</th><th>{{ __('Wallet applied') }}</th><th>{{ __('Status') }}</th><th>{{ __('Date') }}</th></tr></thead>
                <tbody>
                @foreach ($membershipUsage as $m)
                    <tr>
                        <td>{{ $m->plan?->name ?? '—' }}</td>
                        <td>{{ currency($m->price_paid) }}</td>
                        <td><strong>{{ currency($m->wallet_credit_used) }}</strong></td>
                        <td><span class="mbs-ref-pill mbs-ref-pill--{{ $m->status === 'active' ? 'ok' : 'warn' }}">{{ ucfirst($m->status) }}</span></td>
                        <td>{{ $m->started_at?->format('M d, Y') ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<style>
    .mbs-ref-page { font-family: 'Plus Jakarta Sans', -apple-system, sans-serif; color:#1c1a4a; }
    .mbs-ref-hero { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;
        background:linear-gradient(135deg,#fff 0%, #f5f3ff 100%); border:1px solid #eef0f3; border-radius:16px;
        padding:22px 24px; margin-bottom:18px; }
    .mbs-ref-hero h1 { margin:0; font-size:22px; font-weight:700; display:flex; align-items:center; gap:10px; }
    .mbs-ref-hero p { margin:6px 0 0; color:#6b7280; font-size:13px; max-width:520px; }
    .mbs-ref-balance { background:#fff; border-radius:14px; padding:14px 18px; box-shadow:0 4px 12px rgba(16, 185, 129,0.12); text-align:right; min-width:200px; }
    .mbs-ref-balance__lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#9ca3af; font-weight:700; }
    .mbs-ref-balance__num { font-size:28px; font-weight:800; color:#10b981; line-height:1; margin:6px 0 4px; }
    .mbs-ref-balance__note { font-size:11px; color:#6b7280; }

    .mbs-ref-share { background:#fff; border:1px solid #eef0f3; border-radius:14px; padding:18px 20px; margin-bottom:18px;
        display:grid; grid-template-columns: 1fr 1.6fr; gap:24px; }
    @media (max-width:780px) { .mbs-ref-share { grid-template-columns:1fr; } }
    .mbs-ref-share__lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#9ca3af; font-weight:700; margin-bottom:6px; }
    .mbs-ref-share__row { display:flex; gap:8px; }
    .mbs-ref-share__row input { flex:1; padding:10px 12px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; outline:none; font-family:monospace; }
    .mbs-ref-copy { padding:10px 14px; background:#10b981; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
    .mbs-ref-copy:hover { background:#065f46; }
    .mbs-ref-share__social { display:flex; gap:8px; margin-top:10px; }
    .mbs-ref-social { width:36px; height:36px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; color:#fff; text-decoration:none; transition: opacity .15s; }
    .mbs-ref-social:hover { opacity:0.85; color:#fff; }
    .mbs-ref-social--tw { background:#1da1f2; }
    .mbs-ref-social--fb { background:#1877f2; }
    .mbs-ref-social--wa { background:#25d366; }
    .mbs-ref-social--em { background:#6b7280; }

    .mbs-ref-stats { display:grid; grid-template-columns: repeat(4, 1fr); gap:12px; margin-bottom:18px; }
    @media (max-width:900px) { .mbs-ref-stats { grid-template-columns: repeat(2, 1fr); } }
    .mbs-ref-stat { background:#fff; border:1px solid #eef0f3; border-radius:14px; padding:16px; display:flex; align-items:center; gap:14px; }
    .mbs-ref-stat__icon { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:16px; flex-shrink:0; }
    .mbs-ref-stat__num { font-size:22px; font-weight:700; line-height:1; }
    .mbs-ref-stat__lbl { font-size:12px; color:#6b7280; margin-top:4px; }

    .mbs-ref-card { background:#fff; border:1px solid #eef0f3; border-radius:14px; overflow:hidden; height:100%; }
    .mbs-ref-card__hdr { padding:14px 18px; border-bottom:1px solid #f3f4f6; }
    .mbs-ref-card__hdr h3 { margin:0; font-size:14px; font-weight:700; display:flex; align-items:center; gap:8px; }
    .mbs-ref-card__hdr h3 i { color:#10b981; }

    .mbs-ref-tbl { width:100%; }
    .mbs-ref-tbl th { padding:10px 18px; font-size:11px; text-transform:uppercase; color:#9ca3af; letter-spacing:0.4px; font-weight:600; text-align:left; background:#fafbfc; }
    .mbs-ref-tbl td { padding:12px 18px; font-size:13px; border-top:1px solid #f3f4f6; }
    .mbs-ref-chip { background:#ecfdf5; color:#10b981; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:600; text-transform:capitalize; }
    .mbs-ref-pill { padding:2px 10px; border-radius:999px; font-size:11px; font-weight:600; }
    .mbs-ref-pill--ok { background:#dcfce7; color:#166534; }
    .mbs-ref-pill--warn { background:#fef3c7; color:#92400e; }
    .mbs-ref-pill--bad { background:#fee2e2; color:#991b1b; }

    .mbs-ref-empty { padding:50px 20px; text-align:center; color:#9ca3af; }
    .mbs-ref-empty i { font-size:32px; display:block; margin-bottom:10px; opacity:0.4; }
    .mbs-ref-empty p { margin:0; font-size:13px; }

    .mbs-ref-feed { list-style:none; padding:0; margin:0; }
    .mbs-ref-feed li { padding:12px 18px; border-top:1px solid #f3f4f6; }
    .mbs-ref-feed__row { display:flex; justify-content:space-between; align-items:center; }
    .mbs-ref-feed__type { font-size:12px; color:#6b7280; font-weight:600; }
    .mbs-ref-feed__amt { font-size:14px; font-weight:700; }
    .mbs-ref-feed__amt.positive { color:#10b981; }
    .mbs-ref-feed__amt.negative { color:#ef4444; }
    .mbs-ref-feed__desc { font-size:12px; color:#1c1a4a; margin-top:2px; }
    .mbs-ref-feed__meta { font-size:11px; color:#9ca3af; margin-top:2px; }
</style>

<script>
(function () {
    // 2026-05-26 (bug-doc S2) — execCommand fallback for non-HTTPS hosts
    // (e.g. demo.mbsguru.com). See refer-earn-widget.blade.php for the
    // same pattern. Without this the copy button silently does nothing
    // when the page isn't served over HTTPS.
    function copyToClipboard(text) {
        if (window.isSecureContext && navigator.clipboard) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise((resolve, reject) => {
            try {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.opacity  = '0';
                document.body.appendChild(ta);
                ta.focus(); ta.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);
                ok ? resolve() : reject(new Error('execCommand returned false'));
            } catch (e) { reject(e); }
        });
    }
    document.querySelectorAll('.mbs-ref-copy').forEach(btn => {
        btn.addEventListener('click', () => {
            copyToClipboard(btn.dataset.copy).then(() => {
                const old = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> {{ __('Copied!') }}';
                setTimeout(() => { btn.innerHTML = old; }, 1500);
            }).catch(() => {
                const old = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-times"></i> {{ __('Press Ctrl+C') }}';
                setTimeout(() => { btn.innerHTML = old; }, 2000);
            });
        });
    });
})();
</script>
@endsection
