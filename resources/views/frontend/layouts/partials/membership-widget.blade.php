{{--
    Membership status widget — shown on the student + coach dashboards.

    Computes its own data from the auth user so the host page doesn't have to
    pass anything in. Renders one of four states:
      • no membership: prompt to pick a plan
      • active + plenty of time: green "you're good"
      • active + expiring within 7 days: yellow renewal nudge
      • expired: red reactivate prompt

    Anchors include the user's referral wallet balance — so the renewal nudge
    surfaces "you have credit, use it" when there's something to apply.
--}}
@auth('web')
@php
    $u = Auth::guard('web')->user();
    $membership = $u->activeMembership;
    if (!$membership) {
        // Look at the most recent expired/cancelled row so we can show a tailored CTA.
        $latest = \App\Models\UserMembership::where('user_id', $u->id)
            ->orderByDesc('id')
            ->first();
    } else {
        $latest = $membership;
    }
    $walletBal = (float) ($u->referral_wallet_balance ?? 0);

    // 2026-06-25 — detect trial by payment_method OR the trial plan itself, so a
    // Coach-Free-Trial membership shows "Free trial active" consistently no
    // matter how it was granted (auto / admin-assign / legacy).
    $isTrial = $membership && ($membership->payment_method === 'trial' || optional($membership->plan)->slug === 'coach-free-trial');

    [$state, $tone, $title, $body] = (function () use ($membership, $latest, $isTrial) {
        if (!$membership) {
            if ($latest && $latest->status === 'expired') {
                $wasTrial = $latest->payment_method === 'trial' || optional($latest->plan)->slug === 'coach-free-trial';
                return ['expired', 'red',
                    $wasTrial ? __('Your free trial ended') : __('Your membership expired'),
                    __('Pick a plan to keep using coach features.'),
                ];
            }
            return ['none', 'blue',
                __('No active membership'),
                __('Pick a plan to unlock platform features.'),
            ];
        }

        if ($isTrial) {
            $daysLeft = $membership->expires_at ? max(0, (int) round(now()->diffInDays($membership->expires_at, false))) : null;

            // Trial in last 3 days — urgent yellow
            if ($daysLeft !== null && $daysLeft <= 3) {
                return ['trial-ending', 'yellow',
                    $daysLeft === 0 ? __('Trial ends today') : __('Trial ends in :n day(s)', ['n' => $daysLeft]),
                    __('Upgrade to a paid plan before the trial expires.'),
                ];
            }
            // Trial healthy — purple "trial active"
            return ['trial', 'purple',
                __('Free trial active'),
                $daysLeft !== null
                    ? __(':n day(s) left · expires :date', ['n' => $daysLeft, 'date' => $membership->expires_at->format('M d, Y')])
                    : __('Enjoy full coach features.'),
            ];
        }

        if ($membership->expires_at && $membership->expires_at->lessThanOrEqualTo(now()->addDays(7))) {
            return ['expiring', 'yellow',
                __('Your membership expires soon'),
                __('Expires :when', ['when' => $membership->expires_at->diffForHumans()]),
            ];
        }
        if ($membership->expires_at) {
            return ['active', 'green',
                __('Membership active'),
                __('Active until :date', ['date' => $membership->expires_at->format('M d, Y')]),
            ];
        }
        return ['active', 'green',
            __('Lifetime membership'),
            __('You have unlimited access — enjoy.'),
        ];
    })();

    $tones = [
        'green'  => ['bg' => '#dcfce7', 'text' => '#166534', 'icon' => 'fa-check-circle', 'iconBg' => '#10b981'],
        'yellow' => ['bg' => '#fef3c7', 'text' => '#92400e', 'icon' => 'fa-clock',       'iconBg' => '#f59e0b'],
        'red'    => ['bg' => '#fee2e2', 'text' => '#991b1b', 'icon' => 'fa-exclamation-circle', 'iconBg' => '#ef4444'],
        'blue'   => ['bg' => '#dbeafe', 'text' => '#1e40af', 'icon' => 'fa-shield-alt',  'iconBg' => '#3b82f6'],
        'purple' => ['bg' => '#ede9fe', 'text' => '#5b21b6', 'icon' => 'fa-flask',       'iconBg' => '#7c3aed'],
    ];
    $t = $tones[$tone];
@endphp

<div class="mbs-mem-widget" style="background:#fff; border:1px solid #eef0f3; border-radius:14px; padding:16px 18px; margin-bottom:18px; display:flex; align-items:center; gap:14px;">
    <div style="width:44px; height:44px; flex-shrink:0; border-radius:12px; background:{{ $t['iconBg'] }}; color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px;">
        <i class="fas {{ $t['icon'] }}"></i>
    </div>
    <div style="flex:1; min-width:0;">
        <div style="font-size:14px; font-weight:700; color:#1c1a4a; line-height:1.3;">
            {{ $title }}
            @if ($membership)
                <span style="font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px; background:{{ $t['bg'] }}; color:{{ $t['text'] }}; margin-left:6px;">{{ $membership->plan?->name ?? '—' }}</span>
            @endif
        </div>
        <div style="font-size:12px; color:#6b7280; margin-top:2px;">{{ $body }}</div>
        @if ($walletBal > 0)
            <div style="font-size:11px; color:#10b981; margin-top:4px; font-weight:600;">
                <i class="fas fa-gift"></i> {{ __('Wallet credit available') }}: {{ currency($walletBal) }}
            </div>
        @endif
    </div>
    <a href="{{ route('membership.index') }}"
       style="padding:9px 16px; border-radius:10px; text-decoration:none; font-weight:600; font-size:13px;
              background:{{ $tone === 'green' ? '#f3f4f6' : 'linear-gradient(135deg,#10b981,#7a73ff)' }};
              color:{{ $tone === 'green' ? '#374151' : '#fff' }};">
        @switch($state)
            @case('none')          {{ __('Choose plan') }}    @break
            @case('expired')       {{ __('Pick a plan') }}    @break
            @case('expiring')      {{ __('Renew now') }}      @break
            @case('trial')         {{ __('Upgrade plan') }}   @break
            @case('trial-ending')  {{ __('Upgrade now') }}    @break
            @default               {{ __('Manage') }}
        @endswitch
        <i class="fas fa-arrow-right" style="font-size:10px; margin-left:4px;"></i>
    </a>
</div>
@endauth
