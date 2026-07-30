{{--
    Coach onboarding "Get started" checklist. Auto-hides once the coach has
    completed all 6 items. Pulls state from CoachOnboardingService.

    Two render variants:
      • 'card' (default) — the full-width checklist card.
      • 'chip' — a compact header pill + dropdown (matches the profile-completion
        chip). Introduced 2026-07-18 so the dashboard body no longer carries the
        large card; setup progress lives in the header next to the Profile chip.
        Dismiss is client-side (localStorage per user + percent). Auto-hides at 100%.
--}}
@auth('web')
@php
    $u = Auth::guard('web')->user();
    $cobVariant = ($variant ?? 'card') === 'chip' ? 'chip' : 'card';
@endphp
@if ($u && $u->role === 'instructor')
    @php
        $progress = app(\App\Services\CoachOnboardingService::class)->progress($u);
    @endphp

    @if ($progress['done'] < $progress['total'])
        @if ($cobVariant === 'chip')
            @php
                $cobPct = (int) $progress['percent'];
                $cobKey = 'mbsCob:' . $u->id;
                $cobFirst = collect($progress['items'])->firstWhere('done', false);
            @endphp
            <div class="mbs-cob-chipwrap" data-cob-key="{{ $cobKey }}" data-cob-pct="{{ $cobPct }}" style="position:relative; display:none;">
                <button type="button" class="mbs-cob-chip" aria-haspopup="true" aria-expanded="false" title="{{ __('Finish setting up') }}">
                    <span class="mbs-cob-ring" style="--pct:{{ $cobPct }};"><span class="mbs-cob-ring__n">{{ $cobPct }}</span></span>
                    <span class="mbs-cob-chip__lbl">{{ __('Setup') }} <b>{{ $progress['done'] }} {{ __('of') }} {{ $progress['total'] }}</b></span>
                    <i class="fas fa-chevron-down mbs-cob-chip__chev"></i>
                </button>

                <div class="mbs-cob-pop" role="menu" hidden>
                    <div class="mbs-cob-pop__head">
                        <div class="mbs-cob-pop__titlerow">
                            <span class="mbs-cob-pop__title">{{ __('Finish setting up') }}</span>
                            <span class="mbs-cob-pop__pct">{{ $cobPct }}%</span>
                        </div>
                        <div class="mbs-cob-pop__sub">{{ $progress['done'] }} {{ __('of') }} {{ $progress['total'] }} {{ __('complete') }}</div>
                        <div class="mbs-cob-pop__bar"><span style="width:{{ $cobPct }}%;"></span></div>
                    </div>
                    <div class="mbs-cob-pop__list">
                        @foreach ($progress['items'] as $item)
                            @if ($item['done'])
                                <div class="mbs-cob-row is-done"><i class="fas fa-check-circle"></i><span>{{ $item['label'] }}</span></div>
                            @else
                                <a href="{{ $item['cta_url'] }}" class="mbs-cob-row"><i class="far fa-circle"></i><span>{{ $item['label'] }}</span><i class="fas fa-chevron-right mbs-cob-row__go"></i></a>
                            @endif
                        @endforeach
                    </div>
                    <div class="mbs-cob-pop__foot">
                        @if ($cobFirst)
                            <a href="{{ $cobFirst['cta_url'] }}" class="mbs-cob-pop__cta">{{ __('Continue setup') }} <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
                        @endif
                        <button type="button" class="mbs-cob-pop__dismiss">{{ __('Dismiss') }}</button>
                    </div>
                </div>
            </div>

            <style>
                .mbs-cob-chip{display:inline-flex;align-items:center;gap:9px;padding:6px 13px 6px 7px;border:1px solid #cfeee3;
                    background:#f4fbf8;border-radius:10px;cursor:pointer;font-size:12.5px;font-weight:600;color:#0f172a;line-height:1;white-space:nowrap;}
                .mbs-cob-chip:hover{border-color:#a7ddca;}
                .mbs-cob-chip__lbl b{color:#64748b;font-weight:600;margin-left:2px;}
                .mbs-cob-chip__chev{font-size:11px;color:#059669;transition:transform .2s;}
                .mbs-cob-chipwrap.is-open .mbs-cob-chip__chev{transform:rotate(180deg);}
                .mbs-cob-ring{position:relative;width:24px;height:24px;border-radius:50%;flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;
                    background:conic-gradient(#059669 calc(var(--pct)*1%), #e3ebe8 0);}
                .mbs-cob-ring::after{content:"";position:absolute;inset:3px;border-radius:50%;background:#f4fbf8;}
                .mbs-cob-ring__n{position:relative;z-index:1;font-size:8px;font-weight:700;color:#047857;}
                .mbs-cob-pop{position:absolute;top:calc(100% + 8px);right:0;width:320px;background:#fff;border:1px solid #e9edf1;
                    border-radius:14px;box-shadow:0 18px 44px -16px rgba(15,23,42,.22);overflow:hidden;z-index:4001;isolation:isolate;}
                .mbs-cob-pop__head{padding:16px 18px 14px;border-bottom:1px solid #f2f4f7;}
                .mbs-cob-pop__titlerow{display:flex;align-items:center;justify-content:space-between;}
                .mbs-cob-pop__title{font-size:13.5px;font-weight:700;color:#0f172a;}
                .mbs-cob-pop__pct{font-size:12px;font-weight:700;color:#047857;}
                .mbs-cob-pop__sub{font-size:11.5px;color:#94a3b8;margin-top:2px;}
                .mbs-cob-pop__bar{height:6px;background:#eef1f4;border-radius:99px;overflow:hidden;margin-top:11px;}
                .mbs-cob-pop__bar span{display:block;height:100%;background:#059669;}
                .mbs-cob-pop__list{padding:6px 8px;max-height:280px;overflow:auto;}
                .mbs-cob-row{display:flex;align-items:center;gap:11px;padding:9px 10px;font-size:13px;border-radius:9px;text-decoration:none;}
                .mbs-cob-row i:first-child{font-size:16px;flex:0 0 auto;}
                .mbs-cob-row.is-done{color:#94a3b8;}
                .mbs-cob-row.is-done i:first-child{color:#059669;}
                .mbs-cob-row:not(.is-done){color:#1f2937;background:#f8fafc;}
                .mbs-cob-row:not(.is-done) i:first-child{color:#cbd5e1;}
                .mbs-cob-row:not(.is-done):hover{background:#eef6f2;}
                .mbs-cob-row span{flex:1;min-width:0;}
                .mbs-cob-row__go{margin-left:auto;font-size:13px;color:#94a3b8;}
                .mbs-cob-pop__foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-top:1px solid #f2f4f7;background:#fcfdfe;}
                .mbs-cob-pop__cta{font-size:12.5px;font-weight:600;color:#047857;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
                .mbs-cob-pop__dismiss{background:none;border:none;font-size:12px;color:#94a3b8;cursor:pointer;padding:2px;}
                .mbs-cob-pop__dismiss:hover{color:#475569;}
                html[data-theme="dark"] .mbs-cob-chip{background:#0e2b24;border-color:#1f5245;color:#e2e8f0;}
                html[data-theme="dark"] .mbs-cob-ring::after{background:#0e2b24;}
                html[data-theme="dark"] .mbs-cob-pop{background:#1e293b;border-color:#2a3a55;}
                html[data-theme="dark"] .mbs-cob-pop__title{color:#e2e8f0;}
                html[data-theme="dark"] .mbs-cob-pop__head,html[data-theme="dark"] .mbs-cob-pop__foot{border-color:#2a3a55;background:#1e293b;}
                html[data-theme="dark"] .mbs-cob-row:not(.is-done){color:#e2e8f0;background:#0f1e33;}
                @media (max-width:767px){.mbs-cob-pop{width:280px;}}
            </style>

            <script>
            (function () {
                var wrap = document.currentScript.previousElementSibling;
                while (wrap && !(wrap.classList && wrap.classList.contains('mbs-cob-chipwrap'))) { wrap = wrap.previousElementSibling; }
                if (!wrap) return;
                var storeKey = wrap.getAttribute('data-cob-key') + ':' + wrap.getAttribute('data-cob-pct');
                try { if (localStorage.getItem(storeKey) === '-1') return; } catch (e) {}
                wrap.style.display = 'inline-flex';

                var chip = wrap.querySelector('.mbs-cob-chip');
                var pop = wrap.querySelector('.mbs-cob-pop');
                function open(o) {
                    wrap.classList.toggle('is-open', o);
                    chip.setAttribute('aria-expanded', o ? 'true' : 'false');
                    if (o) pop.removeAttribute('hidden'); else pop.setAttribute('hidden', '');
                }
                chip.addEventListener('click', function (e) { e.stopPropagation(); open(pop.hasAttribute('hidden')); });
                document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) open(false); });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape') open(false); });
                var dismiss = wrap.querySelector('.mbs-cob-pop__dismiss');
                if (dismiss) dismiss.addEventListener('click', function () {
                    try { localStorage.setItem(storeKey, '-1'); } catch (e) {}
                    wrap.style.display = 'none';
                });
            })();
            </script>
        @else
            {{-- ── Full-width card (default) ── --}}
            <div class="mbs-cob" style="background:#fff; border:1px solid #eef0f3; border-radius:14px; padding:20px 22px; margin-bottom:18px;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:14px;">
                    <div>
                        <div style="font-size:15px; font-weight:700; color:#1c1a4a; display:flex; align-items:center; gap:8px;">
                            <span style="width:28px; height:28px; border-radius:8px; background:linear-gradient(135deg,#10b981,#7c3aed); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:13px;"><i class="fas fa-rocket"></i></span>
                            {{ __('Get started') }}
                        </div>
                        <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                            {{ __(':done of :total complete — finish setup to make the most of your trial', ['done' => $progress['done'], 'total' => $progress['total']]) }}
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:22px; font-weight:800; color:#10b981;">{{ $progress['percent'] }}%</div>
                        <div style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:0.4px; font-weight:600;">{{ __('done') }}</div>
                    </div>
                </div>

                <div style="height:6px; background:#f3f4f6; border-radius:3px; overflow:hidden; margin-bottom:14px;">
                    <div style="height:100%; width:{{ $progress['percent'] }}%; background:linear-gradient(90deg,#10b981,#7c3aed); transition:width 0.4s;"></div>
                </div>

                <div class="mbs-cob__items" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:10px;">
                    @foreach ($progress['items'] as $item)
                        @php $isDone = $item['done']; @endphp
                        <a href="{{ $item['cta_url'] }}"
                           style="display:flex; gap:12px; align-items:center; padding:11px 14px; border-radius:10px; text-decoration:none;
                                  background: {{ $isDone ? '#f0fdf4' : '#fafbfc' }};
                                  border: 1px solid {{ $isDone ? '#bbf7d0' : '#eef0f3' }};
                                  opacity: {{ $isDone ? '0.78' : '1' }};">
                            <div style="width:30px; height:30px; flex-shrink:0; border-radius:8px;
                                        background: {{ $isDone ? '#10b981' : '#ecfdf5' }};
                                        color: {{ $isDone ? '#fff' : '#10b981' }};
                                        display:flex; align-items:center; justify-content:center; font-size:12px;">
                                <i class="fas {{ $isDone ? 'fa-check' : $item['icon'] }}"></i>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:13px; font-weight:600; color:#1c1a4a; line-height:1.3; {{ $isDone ? 'text-decoration:line-through; color:#16a34a;' : '' }}">{{ $item['label'] }}</div>
                                <div style="font-size:11.5px; color:#6b7280; margin-top:2px; line-height:1.4;">{{ $item['description'] }}</div>
                            </div>
                            @if (!$isDone)
                                <i class="fas fa-arrow-right" style="color:#9ca3af; font-size:11px; flex-shrink:0;"></i>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
@endif
@endauth
