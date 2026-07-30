{{--
    Profile completion progress.

    Computes a percentage from which user fields have been filled in.

    Two render variants:
      - 'card' (default): a full-width progress card. Used on the student
        dashboard, unchanged.
      - 'chip': a compact header pill with a dropdown listing the missing
        steps, a "Complete now" CTA, and dismiss / remind-me-later controls
        (persisted client-side per user via localStorage). Auto-hides at 100%.
        Introduced 2026-07-18 (Dashboard Nav Enhancement #8) so the coach
        dashboard no longer carries a permanent full-width widget.

    Usage (include this partial from a parent view):
      - default card ....... include with no extra data
      - header chip ........ pass ['variant' => 'chip']
      - custom action url .. pass ['ctaUrl' => route('student.setting.index')]

    NOTE: do NOT place a nested {{ "--" }} comment inside this docblock, and do
    NOT write a live include of this same partial here — a self-include causes
    infinite render recursion (fixed 2026-07-18).
--}}
@auth('web')
@php
    $u = Auth::guard('web')->user();
    $isCoach = $u->role === 'instructor';
    $variant = ($variant ?? 'card') === 'chip' ? 'chip' : 'card';

    $checks = [
        ['label' => __('Add a profile photo'),     'done' => !empty($u->image) && !str_contains($u->image, 'frontend-avatar')],
        ['label' => __('Add a short bio'),         'done' => !empty($u->short_bio) || !empty($u->bio)],
        ['label' => __('Add your phone number'),   'done' => !empty($u->phone)],
        ['label' => __('Add your address'),        'done' => !empty($u->address)],
        ['label' => __('Choose your gender'),      'done' => !empty($u->gender)],
        ['label' => __('Set a job title'),         'done' => !empty($u->job_title)],
    ];

    if ($isCoach) {
        $hasInstructorReq = false;
        try {
            $hasInstructorReq = \Modules\InstructorRequest\app\Models\InstructorRequest::where('user_id', $u->id)
                ->whereNotNull('payout_account')->exists();
        } catch (\Throwable $e) { /* ignore */ }
        $checks[] = ['label' => __('Configure payout method'), 'done' => $hasInstructorReq];
    }

    $total = count($checks);
    $done = count(array_filter($checks, fn($c) => $c['done']));
    $percent = $total > 0 ? (int) round(($done / $total) * 100) : 100;
    $ctaUrl = $ctaUrl ?? ($isCoach ? route('instructor.setting.index') : route('student.setting.index'));
@endphp

@if ($percent < 100)
    @if ($variant === 'chip')
        {{-- ── Compact header chip + dropdown ─────────────────────────────
             Snooze / dismiss are client-side only (localStorage, keyed per
             user) so there is no new route, table, or server state. The chip
             re-appears automatically once the percentage changes (the key
             encodes the current percent) or after the snooze window lapses. --}}
        @php
            $pcAccent = $percent >= 66 ? '#10b981' : ($percent >= 33 ? '#f59e0b' : '#ef4444');
            $pcKey    = 'mbsProfilePc:' . $u->id;
        @endphp
        <div class="mbs-pc-chipwrap" data-pc-key="{{ $pcKey }}" data-pc-percent="{{ $percent }}" style="position:relative; display:none;">
            <button type="button" class="mbs-pc-chip" aria-haspopup="true" aria-expanded="false"
                    title="{{ __('Complete your profile') }}">
                <span class="mbs-pc-ring" style="--pc:{{ $percent }}; --pc-accent:{{ $pcAccent }};">
                    <span class="mbs-pc-ring__num">{{ $percent }}%</span>
                </span>
                <span class="mbs-pc-chip__label">{{ __('Profile') }} {{ $percent }}% {{ __('Complete') }}</span>
                <i class="fas fa-chevron-down mbs-pc-chip__chev"></i>
            </button>

            <div class="mbs-pc-pop" role="menu" hidden>
                <div class="mbs-pc-pop__head">
                    <div class="mbs-pc-pop__title">
                        <i class="fas fa-user-check" style="color:{{ $pcAccent }};"></i>
                        {{ __('Complete your profile') }}
                    </div>
                    <div class="mbs-pc-pop__sub">{{ $done }} {{ __('of') }} {{ $total }} {{ __('steps complete') }}</div>
                </div>
                <div class="mbs-pc-pop__bar"><span style="width:{{ $percent }}%; background:{{ $pcAccent }};"></span></div>
                <ul class="mbs-pc-pop__list">
                    @foreach ($checks as $c)
                        @if (! $c['done'])
                            <li><i class="far fa-circle"></i><span>{{ $c['label'] }}</span></li>
                        @endif
                    @endforeach
                </ul>
                <div class="mbs-pc-pop__actions">
                    <a href="{{ $ctaUrl }}" class="mbs-pc-pop__cta">
                        {{ __('Complete now') }} <i class="fas fa-arrow-right" style="font-size:10px;"></i>
                    </a>
                    <button type="button" class="mbs-pc-pop__snooze" data-snooze="7">{{ __('Remind me later') }}</button>
                    <button type="button" class="mbs-pc-pop__dismiss" title="{{ __('Dismiss') }}" aria-label="{{ __('Dismiss') }}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>

        <style>
            .mbs-pc-chipwrap{position:relative;}
            /* When the dropdown is open, lift the whole chip into a high stacking
               context so the popover always paints above the dashboard cards
               (trial / onboarding / membership) that sit directly below it. */
            .mbs-pc-chipwrap.is-open{z-index:4000;}
            .mbs-pc-chip{display:inline-flex;align-items:center;gap:8px;padding:5px 12px 5px 6px;border:1px solid #e5e7eb;
                background:#fff;border-radius:999px;cursor:pointer;font-size:12.5px;font-weight:600;color:#1c1a4a;
                box-shadow:0 1px 2px rgba(0,0,0,.05);white-space:nowrap;line-height:1;}
            .mbs-pc-chip:hover{border-color:#d1d5db;box-shadow:0 2px 6px rgba(0,0,0,.08);}
            .mbs-pc-chip__chev{font-size:9px;color:#9ca3af;transition:transform .2s;}
            .mbs-pc-chipwrap.is-open .mbs-pc-chip__chev{transform:rotate(180deg);}
            .mbs-pc-ring{position:relative;width:26px;height:26px;border-radius:50%;flex:0 0 auto;display:inline-flex;
                align-items:center;justify-content:center;
                background:conic-gradient(var(--pc-accent) calc(var(--pc)*1%), #eef0f4 0);}
            .mbs-pc-ring::after{content:"";position:absolute;inset:3px;border-radius:50%;background:#fff;}
            .mbs-pc-ring__num{position:relative;z-index:1;font-size:8px;font-weight:700;color:var(--pc-accent);}
            .mbs-pc-pop{position:absolute;top:calc(100% + 8px);right:0;width:260px;background:#fff;border:1px solid #e5e7eb;
                border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,.14);padding:14px;z-index:4001;isolation:isolate;}
            .mbs-pc-pop__title{font-size:13px;font-weight:700;color:#1c1a4a;display:flex;align-items:center;gap:6px;}
            .mbs-pc-pop__sub{font-size:11px;color:#6b7280;margin-top:2px;}
            .mbs-pc-pop__bar{height:6px;background:#f1f2f6;border-radius:4px;overflow:hidden;margin:10px 0;}
            .mbs-pc-pop__bar span{display:block;height:100%;transition:width .4s;}
            .mbs-pc-pop__list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;max-height:160px;overflow:auto;}
            .mbs-pc-pop__list li{display:flex;align-items:center;gap:8px;font-size:12px;color:#4b5563;}
            .mbs-pc-pop__list li i{font-size:9px;color:#9ca3af;}
            .mbs-pc-pop__actions{display:flex;align-items:center;gap:8px;margin-top:12px;}
            .mbs-pc-pop__cta{flex:1;text-align:center;padding:7px 10px;background:#10b981;color:#fff;border-radius:8px;
                text-decoration:none;font-size:12px;font-weight:600;white-space:nowrap;}
            .mbs-pc-pop__cta:hover{background:#0ea472;color:#fff;}
            .mbs-pc-pop__snooze{background:none;border:none;color:#6b7280;font-size:11px;cursor:pointer;padding:4px;white-space:nowrap;}
            .mbs-pc-pop__snooze:hover{color:#111827;text-decoration:underline;}
            .mbs-pc-pop__dismiss{background:#f3f4f6;border:none;width:26px;height:26px;border-radius:7px;color:#6b7280;cursor:pointer;flex:0 0 auto;}
            .mbs-pc-pop__dismiss:hover{background:#e5e7eb;color:#111827;}
            html[data-theme="dark"] .mbs-pc-chip{background:#1e293b;border-color:#2a3a55;color:#e2e8f0;}
            html[data-theme="dark"] .mbs-pc-ring::after{background:#1e293b;}
            html[data-theme="dark"] .mbs-pc-pop{background:#1e293b;border-color:#2a3a55;}
            html[data-theme="dark"] .mbs-pc-pop__title{color:#e2e8f0;}
            html[data-theme="dark"] .mbs-pc-pop__list li{color:#cbd5e1;}
            html[data-theme="dark"] .mbs-pc-pop__dismiss{background:#0f1e33;color:#94a3b8;}
            @media (max-width:767px){.mbs-pc-pop{width:240px;}}
        </style>

        <script>
        (function () {
            var wrap = document.currentScript.previousElementSibling;
            while (wrap && !wrap.classList.contains('mbs-pc-chipwrap')) { wrap = wrap.previousElementSibling; }
            if (!wrap) return;
            var key = wrap.getAttribute('data-pc-key');
            var pct = wrap.getAttribute('data-pc-percent');
            var storeKey = key + ':' + pct; // snooze is tied to the CURRENT percentage
            // Respect an active snooze/dismiss for this exact percentage.
            try {
                var until = parseInt(localStorage.getItem(storeKey) || '0', 10);
                if (until === -1) return;               // dismissed for this percent
                if (until && Date.now() < until) return; // snoozed and window not elapsed
            } catch (e) {}
            wrap.style.display = 'inline-flex';

            var chip = wrap.querySelector('.mbs-pc-chip');
            var pop  = wrap.querySelector('.mbs-pc-pop');
            function open(o) {
                wrap.classList.toggle('is-open', o);
                chip.setAttribute('aria-expanded', o ? 'true' : 'false');
                if (o) { pop.removeAttribute('hidden'); } else { pop.setAttribute('hidden', ''); }
            }
            chip.addEventListener('click', function (e) { e.stopPropagation(); open(pop.hasAttribute('hidden')); });
            document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) open(false); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') open(false); });

            var snooze = wrap.querySelector('.mbs-pc-pop__snooze');
            if (snooze) snooze.addEventListener('click', function () {
                var days = parseInt(snooze.getAttribute('data-snooze') || '7', 10);
                try { localStorage.setItem(storeKey, String(Date.now() + days * 864e5)); } catch (e) {}
                wrap.style.display = 'none';
            });
            var dismiss = wrap.querySelector('.mbs-pc-pop__dismiss');
            if (dismiss) dismiss.addEventListener('click', function () {
                try { localStorage.setItem(storeKey, '-1'); } catch (e) {}
                wrap.style.display = 'none';
            });
        })();
        </script>
    @else
        {{-- ── Full-width card (default; student dashboard) ── --}}
        <div class="mbs-profile-completion" style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; margin-bottom:20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:12px;">
                <div>
                    <div style="font-size:14px; font-weight:600; color:#1c1a4a;">
                        <i class="fas fa-user-check" style="color:#10b981; margin-right:6px;"></i>
                        {{ __('Complete your profile') }}
                    </div>
                    <div style="font-size:12px; color:#6b7280; margin-top:2px;">
                        {{ $done }} {{ __('of') }} {{ $total }} {{ __('steps complete') }} — {{ __('boost your visibility') }}
                    </div>
                </div>
                <a href="{{ $ctaUrl }}"
                   style="padding:8px 16px; background:#10b981; color:#fff; border-radius:8px; text-decoration:none; font-size:12px; font-weight:600; white-space:nowrap;">
                    {{ __('Complete now') }} <i class="fas fa-arrow-right" style="font-size:10px; margin-left:4px;"></i>
                </a>
            </div>
            <div style="height:8px; background:#f3f4f6; border-radius:4px; overflow:hidden; margin-bottom:14px;">
                <div style="height:100%; width:{{ $percent }}%; background:linear-gradient(90deg, #10b981, #7c3aed); transition: width 0.4s;"></div>
            </div>
            <ul style="list-style:none; padding:0; margin:0; display:flex; flex-wrap:wrap; gap:8px;">
                @foreach ($checks as $c)
                    <li style="display:flex; align-items:center; gap:6px; padding:4px 10px; border-radius: 12px; font-size:11.5px; {{ $c['done'] ? 'background:#dcfce7; color:#166534;' : 'background:#f3f4f6; color:#6b7280;' }}">
                        <i class="fas {{ $c['done'] ? 'fa-check-circle' : 'fa-circle' }}" style="font-size:10px;"></i>
                        <span>{{ $c['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endif
@endauth
