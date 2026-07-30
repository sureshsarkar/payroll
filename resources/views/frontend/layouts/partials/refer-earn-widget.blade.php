{{--
    Compact "Refer & Earn" promo widget — value-positive, surfaces the referral
    feature without doom-pricing the user. Shows the code prominently, a copy
    button, the wallet balance if any, and a link to the full referral panel.

    Designed for the student dashboard but renders for any authed web user.
--}}
@auth('web')
@php
    $u = Auth::guard('web')->user();
    $walletBal = (float) ($u->referral_wallet_balance ?? 0);
    $code = $u->referral_code; // accessor lazily generates one if missing
    // 2026-07-13 — point the share link straight at registration so a referred
    // friend lands on the sign-up form (was '/?ref=' which hit the home page).
    // Matches ReferralController's /register?ref= link.
    $link = rtrim(config('app.url'), '/') . '/register?ref=' . $code;

    // Only fetch the count when there's likely something to show — keeps the
    // widget lightweight on first render. The full counts live on /referral.
    $totalReferrals = \App\Models\Referral::where('referrer_user_id', $u->id)->count();
@endphp

<div class="mbs-refer-earn-widget" style="background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); border:1px solid #ddd6fe; border-radius:14px; padding:18px 20px; margin-bottom:18px; display:flex; align-items:center; gap:18px; flex-wrap:wrap;">
    <div style="width:54px; height:54px; flex-shrink:0; border-radius:14px; background:linear-gradient(135deg,#10b981,#7c3aed); color:#fff; display:flex; align-items:center; justify-content:center; font-size:22px; box-shadow: 0 4px 12px rgba(16, 185, 129,0.3);">
        <i class="fas fa-gift"></i>
    </div>

    <div style="flex:1; min-width:200px;">
        <div style="font-size:14px; font-weight:700; color:#1c1a4a; line-height:1.3;">
            {{ __('Refer friends, earn credit') }}
        </div>
        <div style="font-size:12px; color:#5b21b6; margin-top:2px;">
            @if ($walletBal > 0)
                {{ __('You have :bal in referral credit ready for membership.', ['bal' => currency($walletBal)]) }}
            @elseif ($totalReferrals > 0)
                {{ __('You have :n referral(s). Keep sharing to earn more credit.', ['n' => $totalReferrals]) }}
            @else
                {{ __('Share your code — earn credit when friends activate their membership.') }}
            @endif
        </div>
    </div>

    <div style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #e9d5ff; border-radius:10px; padding:6px 8px 6px 14px;">
        <span style="font-family:monospace; font-size:14px; font-weight:700; color:#10b981; letter-spacing:1px;">{{ $code }}</span>
        <button type="button" class="mbs-refer-copy" data-copy="{{ $link }}"
                style="background:#10b981; color:#fff; border:none; padding:7px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:5px;">
            <i class="fas fa-copy"></i> {{ __('Copy link') }}
        </button>
    </div>

    <a href="{{ route('referral.index') }}"
       style="font-size:13px; color:#10b981; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:4px;">
        {{ __('View details') }} <i class="fas fa-arrow-right" style="font-size:10px;"></i>
    </a>
</div>

<script>
(function () {
    // 2026-05-26 (bug-doc S2) — navigator.clipboard.writeText() only works
    // in secure contexts (HTTPS) on most browsers. demo.mbsguru.com is
    // served over HTTP so the promise rejected and the success handler
    // never ran. Fall back to the legacy execCommand('copy') path which
    // works on HTTP too. Both paths show the "Copied!" feedback.
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
                ta.focus();
                ta.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);
                ok ? resolve() : reject(new Error('execCommand returned false'));
            } catch (e) { reject(e); }
        });
    }
    document.querySelectorAll('.mbs-refer-copy').forEach(btn => {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => {
            copyToClipboard(btn.dataset.copy).then(() => {
                const old = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> {{ __('Copied!') }}';
                setTimeout(() => btn.innerHTML = old, 1500);
            }).catch(() => {
                const old = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-times"></i> {{ __('Press Ctrl+C') }}';
                setTimeout(() => btn.innerHTML = old, 2000);
            });
        });
    });
})();
</script>
@endauth
