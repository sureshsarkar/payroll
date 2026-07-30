@php
    $u = Auth::guard('web')->user();
    $isCoach = $u && $u->role === 'instructor';
    $dashLayout = $isCoach
        ? 'frontend.instructor-dashboard.layouts.master'
        : 'frontend.student-dashboard.layouts.master';

    // Enterprise is sales-led: the "Contact us" CTA opens a WhatsApp chat with
    // the MBSGuru platform sales number (admin-managed contact section — not a
    // coach number, never hardcoded). Falls back to the contact page if no
    // number is configured so the button can never dead-end.
    $entWaRaw    = optional(\Modules\Frontend\app\Models\ContactSection::first())->phone_one;
    $entWaDigits = $entWaRaw ? preg_replace('/\D+/', '', $entWaRaw) : '';
    $entWaMsg    = rawurlencode("Hi MBSGuru team, I'm interested in the Enterprise plan for my institute. Please share pricing and a demo.");
    $entWaLink   = $entWaDigits ? "https://wa.me/{$entWaDigits}?text={$entWaMsg}" : route('contact.index');
@endphp
@extends($dashLayout)

@section('dashboard-contents')
{{-- Corp partial only included when extending the instructor layout —
     the student dashboard ships its own variant. Both layouts already
     load Bootstrap so the underlying primitives still apply. --}}
@if ($isCoach)
    @include('frontend.instructor-dashboard.settings.partials._corporate')
@endif

<style>
    /* Membership-specific extensions on top of the corporate primitives.
       Plan cards need a "featured" / "current" treatment that doesn't
       map to any existing corp- class; everything else (header, tables,
       pills, empty state) uses the shared system. */
    /* 2026-06-25 — premium/corporate pricing-card pass: featured "Most Popular"
       tier, refined elevation/typography, ghost CTA for sales-led Enterprise. */
    .mbs-plans {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 16px;
        margin-bottom: 22px;
        align-items: stretch;
    }
    .mbs-plan {
        position: relative;
        background: #fff;
        border: 1px solid #e8eaf0;
        border-radius: 16px;
        padding: 22px 22px 24px;
        transition: transform .16s cubic-bezier(.22,1,.36,1), box-shadow .16s, border-color .16s;
        display: flex;
        flex-direction: column;
        box-shadow: 0 1px 2px rgba(16,24,40,.04);
    }
    .mbs-plan:hover {
        transform: translateY(-4px);
        border-color: #c7d2fe;
        box-shadow: 0 18px 40px -12px rgba(99, 102, 241, .22);
    }
    .mbs-plan--featured {
        border: 2px solid #6366f1;
        box-shadow: 0 18px 44px -14px rgba(99, 102, 241, .28);
    }
    .mbs-plan--featured::before {
        content: '';
        position: absolute; top: 0; left: 24px; right: 24px; height: 3px;
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
        border-radius: 0 0 3px 3px;
    }
    .mbs-plan__badge {
        position: absolute; top: -11px; left: 50%; transform: translateX(-50%);
        background: linear-gradient(90deg, #6366f1, #8b5cf6); color: #fff;
        font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
        padding: 4px 14px; border-radius: 999px; white-space: nowrap;
        box-shadow: 0 4px 12px -2px rgba(99, 102, 241, .5);
    }
    .mbs-plan__hdr {
        border-bottom: 1px solid #f1f2f6;
        padding-bottom: 16px;
        margin-bottom: 16px;
    }
    .mbs-plan__name {
        font-size: 15px;
        font-weight: 700;
        color: #0b1220;
        margin: 0 0 10px;
        letter-spacing: -.01em;
    }
    .mbs-plan__price { display: flex; align-items: baseline; gap: 6px; }
    .mbs-plan__amt   { font-size: 28px; font-weight: 800; color: #4f46e5; letter-spacing: -.02em; }
    .mbs-plan__amt--contact { font-size: 23px; }
    .mbs-plan__per   { font-size: 12px; color: #9aa1b0; }
    .mbs-plan__feats {
        list-style: none;
        padding: 0;
        margin: 0 0 18px;
        flex: 1;
    }
    .mbs-plan__feats li {
        padding: 6px 0;
        font-size: 13px;
        color: #3d4453;
        display: flex;
        align-items: flex-start;
        gap: 9px;
        line-height: 1.45;
    }
    .mbs-plan__feats li i { color: #16a34a; font-size: 12px; margin-top: 3px; flex-shrink: 0; }
    .mbs-plan__cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 12px 16px;
        background: #6366f1;
        color: #fff;
        text-decoration: none;
        border: 1.5px solid #6366f1;
        border-radius: 10px;
        font-weight: 600;
        font-size: 13.5px;
        transition: background .14s, transform .14s, border-color .14s;
    }
    .mbs-plan__cta:hover { background: #4f46e5; border-color: #4f46e5; color: #fff; text-decoration: none; transform: translateY(-1px); }
    .mbs-plan--featured .mbs-plan__cta { background: linear-gradient(90deg, #6366f1, #7c3aed); border-color: transparent; }
    /* Ghost CTA — sales-led Enterprise "Contact us" */
    .mbs-plan__cta--ghost { background: #fff; color: #4f46e5; border: 1.5px solid #c7d2fe; }
    .mbs-plan__cta--ghost:hover { background: #eef1ff; color: #4f46e5; border-color: #6366f1; }

    /* 2026-06-26 — the coach's ACTIVE plan: green ring + "Your Current Plan"
       badge + non-clickable active CTA. */
    .mbs-plan--current { border-color: #16a34a; box-shadow: 0 0 0 1.5px #16a34a, 0 8px 28px -14px rgba(22,163,74,.45); }
    .mbs-plan__badge--current { background: #16a34a; color: #fff; }
    .mbs-plan__badge--current i { margin-right: 4px; }
    .mbs-plan__cta--current { background: #ecfdf5; color: #15803d; border: 1.5px solid #86efac; cursor: default; pointer-events: none; }
    .mbs-plan__cta--current i { margin-right: 6px; }
    .mbs-plan__current-exp { text-align: center; font-size: 11.5px; color: #6b7280; margin-top: 8px; }

    /* 2026-06-25 — corporate "Compare plans" matrix below the cards. */
    .mbs-compare-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .mbs-compare { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 520px; }
    .mbs-compare th, .mbs-compare td { padding: 12px 16px; text-align: center; border-bottom: 1px solid #f1f2f6; white-space: nowrap; }
    .mbs-compare thead th { font-size: 12px; font-weight: 700; color: #0b1220; border-bottom: 1.5px solid #e8eaf0; }
    .mbs-compare th:first-child, .mbs-compare td:first-child { text-align: left; }
    .mbs-compare__feat { color: #6b7280; font-weight: 500; }
    .mbs-compare td { color: #3d4453; }
    .mbs-compare .is-feat { background: #f5f3ff; color: #4f46e5; font-weight: 600; }
    .mbs-compare tbody tr:last-child td { border-bottom: 0; }

    /* 2026-06-25 — Monthly/Annual billing toggle */
    .mbs-billing-toggle { display: inline-flex; background: #eef0f5; border-radius: 999px; padding: 4px; margin-bottom: 16px; gap: 2px; }
    .mbs-billing-toggle__btn { border: 0; background: transparent; padding: 8px 18px; border-radius: 999px; font-size: 13px; font-weight: 600; color: #6b7280; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; transition: all .15s; }
    .mbs-billing-toggle__btn.is-active { background: #fff; color: #4f46e5; box-shadow: 0 1px 3px rgba(16, 24, 40, .12); }
    .mbs-billing-toggle__save { font-size: 10.5px; font-weight: 700; background: #dcfce7; color: #047857; padding: 2px 8px; border-radius: 999px; }
    /* price visibility driven by #mbsPlans.is-annual */
    .mbs-amt-annual, .mbs-per-annual, .mbs-plan__save { display: none; }
    #mbsPlans.is-annual .mbs-amt-monthly, #mbsPlans.is-annual .mbs-per-monthly { display: none; }
    #mbsPlans.is-annual .mbs-amt-annual, #mbsPlans.is-annual .mbs-per-annual { display: inline; }
    #mbsPlans.is-annual .mbs-plan__save { display: inline-block; }
    .mbs-plan__save { margin: -6px 0 12px; font-size: 11px; font-weight: 600; color: #047857; background: #ecfdf5; padding: 3px 10px; border-radius: 999px; }
    .mbs-plan__who { font-size: 12px; color: #6366f1; font-weight: 600; margin: -4px 0 10px; }

    /* Trust bar + social proof + FAQ (only verified claims) */
    .mbs-trustbar { display: flex; flex-wrap: wrap; justify-content: center; gap: 20px; padding: 13px 16px; background: #f6f7fb; border-radius: 12px; font-size: 12.5px; color: #4b5563; margin-bottom: 18px; }
    .mbs-trustbar span i { color: #6366f1; margin-right: 5px; }
    .mbs-social { border: 1px dashed #c7d2fe; border-radius: 12px; padding: 16px; text-align: center; background: #f8f9ff; margin-bottom: 18px; }
    .mbs-social__eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .06em; color: #6366f1; text-transform: uppercase; }
    .mbs-faq__q { display: flex; justify-content: space-between; align-items: center; padding: 13px 2px; cursor: pointer; font-size: 13.5px; font-weight: 600; color: #111827; border-bottom: 1px solid #f1f2f6; }
    .mbs-faq__q i { color: #9ca3af; transition: transform .15s; }
    .mbs-faq__q.is-open i { transform: rotate(180deg); }
    .mbs-faq__a { display: none; padding: 4px 2px 14px; font-size: 13px; color: #4b5563; line-height: 1.6; border-bottom: 1px solid #f1f2f6; }
    .mbs-faq__a.is-open { display: block; }

    /* Renewal banner colour variants */
    .mbs-renew {
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    .mbs-renew--warn  { background: #fffbeb; border: 1px solid #fde68a; }
    .mbs-renew--danger { background: #fef2f2; border: 1px solid #fecaca; }
    .mbs-renew__icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }
    .mbs-renew--warn .mbs-renew__icon  { background: #f59e0b; }
    .mbs-renew--danger .mbs-renew__icon { background: #ef4444; }
    .mbs-renew__body { flex: 1; min-width: 220px; }
    .mbs-renew__title { font-size: 14px; font-weight: 700; color: #111827; }
    .mbs-renew__sub   { font-size: 12.5px; color: #6b7280; margin-top: 3px; }
    .mbs-renew__credit {
        font-size: 12px; color: #6366f1; margin-top: 5px; font-weight: 600;
    }
    .mbs-renew__cta {
        padding: 10px 18px;
        background: #6366f1;
        color: #fff;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }
    .mbs-renew__cta:hover { background: #4f46e5; color: #fff; text-decoration: none; }

    /* "Current plan" indicator in the header --}}
    .mbs-current-pill {
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        border-radius: 10px;
        padding: 8px 14px;
        text-align: right;
    }
    .mbs-current-pill__lbl  { font-size: 10px; color: #4b5563; text-transform: uppercase; letter-spacing: .4px; font-weight: 700; }
    .mbs-current-pill__name { font-size: 14px; font-weight: 700; color: #4338ca; margin-top: 2px; }
    .mbs-current-pill__exp  { font-size: 11px; color: #6b7280; margin-top: 2px; }
</style>

<div class="corp-page" id="membership">

    {{-- 2026-06-25 — one-time free trial: explain why the trial is no longer offered. --}}
    @if (!empty($trialUsed))
        <div style="display:flex; align-items:center; gap:12px; background:#fff7ed; border:1px solid #fed7aa;
                    border-radius:12px; padding:14px 18px; margin-bottom:18px;">
            <i class="fas fa-circle-info" style="color:#c2630f; font-size:18px;"></i>
            <div style="font-size:13.5px; color:#9a3412; line-height:1.5;">
                <strong>{{ __('Your free trial has already been used.') }}</strong>
                {{ __('Please purchase a membership plan to continue using coach features.') }}
            </div>
        </div>
    @endif

    {{-- Renewal banner — first thing the coach sees if expired/expiring --}}
    @if ($shouldShowRenewal && $renewalPlan)
        @php
            $isExpired = $renewalState === 'expired';
            $tone = $isExpired ? 'mbs-renew--danger' : 'mbs-renew--warn';
            $title = $isExpired ? __('Your membership has expired') : __('Renew your membership');
            $walletBal = (float) (auth()->user()->referral_wallet_balance ?? 0);
            $previewPrice = (float) $renewalPlan->price;
            $walletApplied = min($walletBal, $previewPrice);
            $cashDue = max(0, $previewPrice - $walletApplied);
        @endphp
        <div class="mbs-renew {{ $tone }}">
            <div class="mbs-renew__icon"><i class="fas fa-redo"></i></div>
            <div class="mbs-renew__body">
                <div class="mbs-renew__title">{{ $title }}</div>
                <div class="mbs-renew__sub">
                    @if ($renewalState === 'expiring' && $current?->expires_at)
                        {{ __('Renew :plan to keep coach features without interruption — expires :when.', ['plan' => $renewalPlan->name, 'when' => $current->expires_at->diffForHumans()]) }}
                    @else
                        {{ __('Reactivate :plan in one click.', ['plan' => $renewalPlan->name]) }}
                    @endif
                </div>
                @if ($walletApplied > 0)
                    <div class="mbs-renew__credit">
                        <i class="fas fa-gift"></i>
                        {{ __(':bal of wallet credit will be applied · pay only :due', ['bal' => currency($walletApplied), 'due' => currency($cashDue)]) }}
                    </div>
                @endif
            </div>
            <a href="{{ route('membership.checkout', $renewalPlan->id) }}" class="mbs-renew__cta">
                <i class="fas fa-bolt"></i> {{ __('Renew :plan', ['plan' => $renewalPlan->name]) }}
            </a>
        </div>
    @endif

    {{-- Header ────────────────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>
                <i class="fas fa-shield-alt" style="color:var(--corp-brand);"></i>
                {{ __('Membership') }}
            </h4>
            <p>{{ __('Pick a plan to unlock platform features. Apply your referral wallet credit at checkout to reduce the price.') }}</p>
        </div>
        @if ($current && $current->status === 'active')
            <div class="corp-header__actions">
                <div class="mbs-current-pill">
                    <div class="mbs-current-pill__lbl">{{ __('Current plan') }}</div>
                    <div class="mbs-current-pill__name">{{ $current->plan?->name ?? '—' }}</div>
                    <div class="mbs-current-pill__exp">
                        @if ($current->expires_at)
                            {{ __('Expires') }} {{ $current->expires_at->format('M d, Y') }}
                        @else
                            {{ __('Lifetime') }}
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Plan cards ─────────────────────────────────────────────── --}}
    @if ($plans->count() === 0)
        <div class="corp-form-card">
            <div class="corp-form-card__body">
                <div class="corp-empty">
                    <div class="corp-empty__icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="corp-empty__title">{{ __('No plans available yet') }}</div>
                    <div class="corp-empty__hint">
                        {{ __('Plans for your role have not been set up yet. Please check back soon.') }}
                    </div>
                </div>
            </div>
        </div>
    @else
        @php
            // 2026-06-25 — defensive: annual billing helpers ship in the same
            // release as this view; guard with method_exists so an out-of-order
            // deploy (view updated, model not yet) degrades gracefully to
            // monthly-only instead of 500-ing.
            $annualOk  = fn ($p) => method_exists($p, 'hasAnnual') && $p->hasAnnual();
            $saveOf    = fn ($p) => method_exists($p, 'annualSavingsPct') ? (int) $p->annualSavingsPct() : 0;
            $anyAnnual = collect($plans)->contains(fn ($p) => $annualOk($p));
            $maxSave   = (int) collect($plans)->max(fn ($p) => $saveOf($p));
        @endphp
        {{-- 2026-06-25 — Annual is the DEFAULT (research: annual-default lifts annual
             uptake 25-35%); Monthly is one tap away. --}}
        @if ($anyAnnual)
            <div class="mbs-billing-toggle" role="group" aria-label="{{ __('Billing period') }}">
                <button type="button" class="mbs-billing-toggle__btn" data-billing="monthly">{{ __('Monthly') }}</button>
                <button type="button" class="mbs-billing-toggle__btn is-active" data-billing="annual">
                    {{ __('Annual') }}
                    @if ($maxSave > 0)<span class="mbs-billing-toggle__save">{{ __('save up to :x%', ['x' => $maxSave]) }}</span>@endif
                </button>
            </div>
        @endif
        <div class="mbs-plans {{ $anyAnnual ? 'is-annual' : '' }}" id="mbsPlans">
            @foreach ($plans as $plan)
                @php
                    // Featured tier = Medium (the "Most Popular" recommendation in the
                    // pricing spec). Tier-based, no hardcoded id; if no medium tier
                    // exists (custom white-label plans) nothing is featured.
                    $isFeatured = ($plan->tier ?? null) === 'medium';
                    $isEnt      = $plan->isEnterprise();
                    // Real "who it's for" descriptor lives in the plan's own features
                    // (e.g. "Ideal for individual coaches…"); surface it as the tier
                    // sub-label and drop it from the bullet list (no invented copy).
                    $feats    = collect($plan->features ?? []);
                    $whoFor   = $feats->first(fn ($f) => \Illuminate\Support\Str::startsWith($f, ['Ideal for', 'For ']));
                    $featList = $whoFor ? $feats->reject(fn ($f) => $f === $whoFor)->values() : $feats;
                    $pHasAnnual = $annualOk($plan);
                    $pSave      = $saveOf($plan);
                    // 2026-06-26 — mark the coach's ACTIVE plan. Detected from the
                    // real subscription ($current = active UserMembership), matched
                    // by plan id (not name/tier) so it's exact and white-label-safe.
                    $isCurrent  = ($current && $current->status === 'active'
                                   && (int) ($current->plan_id ?? 0) === (int) $plan->id);
                @endphp
                <div class="mbs-plan {{ $isFeatured ? 'mbs-plan--featured' : '' }} {{ $isEnt ? 'mbs-plan--enterprise' : '' }} {{ $isCurrent ? 'mbs-plan--current' : '' }}">
                    @if ($isCurrent)
                        <span class="mbs-plan__badge mbs-plan__badge--current"><i class="fas fa-circle-check"></i> {{ __('Your Current Plan') }}</span>
                    @elseif ($isFeatured)
                        <span class="mbs-plan__badge">{{ __('Most Popular') }}</span>
                    @endif
                    <div class="mbs-plan__hdr">
                        <div class="mbs-plan__name">{{ $plan->name }}</div>
                        @if ($whoFor)
                            <div class="mbs-plan__who">{{ $whoFor }}</div>
                        @endif
                        <div class="mbs-plan__price">
                            @if ($isEnt)
                                {{-- 2026-07-13 — Enterprise is quote-based: never show a
                                     figure, invite a conversation instead. The "Contact us"
                                     CTA below handles the action. Tier-based (isEnterprise),
                                     white-label safe — no hardcoded plan/coach. --}}
                                <span class="mbs-plan__amt mbs-plan__amt--contact">{{ __('Talk to Us') }}</span>
                            @else
                                <span class="mbs-plan__amt mbs-amt-monthly">{{ currency($plan->price) }}</span>
                                <span class="mbs-plan__per mbs-per-monthly">
                                    @if ($plan->duration_days > 0)
                                        / {{ $plan->duration_days }} {{ __('days') }}
                                    @else
                                        {{ __('lifetime') }}
                                    @endif
                                </span>
                                {{-- annual figures (shown when the Annual toggle is on) --}}
                                <span class="mbs-plan__amt mbs-amt-annual">{{ currency($pHasAnnual ? $plan->annual_price : $plan->price) }}</span>
                                <span class="mbs-plan__per mbs-per-annual">
                                    @if ($pHasAnnual)
                                        / {{ __('year') }}
                                    @elseif ($plan->duration_days > 0)
                                        / {{ $plan->duration_days }} {{ __('days') }}
                                    @else
                                        {{ __('lifetime') }}
                                    @endif
                                </span>
                            @endif
                        </div>
                        @if (! $isEnt && $pHasAnnual && $pSave > 0)
                            <div class="mbs-plan__save">{{ __('Save :x% vs monthly', ['x' => $pSave]) }}</div>
                        @endif
                    </div>
                    @if ($featList->isNotEmpty())
                        <ul class="mbs-plan__feats">
                            @foreach ($featList as $f)
                                <li><i class="fas fa-check"></i> {{ $f }}</li>
                            @endforeach
                        </ul>
                    @endif
                    {{-- 2026-06-25 — Enterprise is sales-led (custom quote / direct
                         settlement), so it shows "Contact us" → opens a WhatsApp
                         chat with the platform sales number (admin-managed contact
                         section; falls back to the contact page). Detected via the
                         plan tier (isEnterprise), never a hardcoded id. --}}
                    @if ($isCurrent)
                        {{-- Active subscription — non-clickable state + expiry. Other
                             plans keep their normal CTA so upgrade/switch still works. --}}
                        <span class="mbs-plan__cta mbs-plan__cta--current" aria-disabled="true">
                            <i class="fas fa-circle-check"></i> {{ __('Active plan') }}
                        </span>
                        @if ($current->expires_at)
                            <div class="mbs-plan__current-exp">{{ __('Renews / expires') }} {{ $current->expires_at->format('M d, Y') }}</div>
                        @endif
                    @elseif ($plan->isEnterprise())
                        <a href="{{ $entWaLink }}"
                           @if (\Illuminate\Support\Str::startsWith($entWaLink, 'https://wa.me/')) target="_blank" rel="noopener" @endif
                           class="mbs-plan__cta mbs-plan__cta--ghost">
                            <i class="fab fa-whatsapp"></i> {{ __('Contact us') }}
                        </a>
                    @else
                        <a href="{{ route('membership.checkout', $plan->id) }}"
                           data-base="{{ route('membership.checkout', $plan->id) }}"
                           data-annual="{{ $pHasAnnual ? '1' : '0' }}"
                           class="mbs-plan__cta js-plan-cta">
                            {{ $current ? __('Switch to this plan') : __('Choose plan') }} <i class="fas fa-arrow-right"></i>
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- 2026-06-25 — trust bar (only verified claims) --}}
        <div class="mbs-trustbar">
            <span><i class="fas fa-shield-alt"></i>{{ __('Secure payment') }}</span>
            <span><i class="fas fa-eye-slash"></i>{{ __('No hidden fees') }}</span>
            <span><i class="fas fa-wallet"></i>{{ __('Apply referral wallet credit') }}</span>
            <span><i class="fas fa-gift"></i>{{ __('14-day free trial included') }}</span>
        </div>

        {{-- Social proof — REPLACE with the client's real numbers / logos / testimonial. --}}
        <div class="mbs-social">
            <div class="mbs-social__eyebrow">{{ __('Social proof') }}</div>
            <div style="font-size:13.5px; color:#4b5563; margin-top:6px;">
                {{ __('Add your real coach/student count, client logos or a short testimonial here.') }}
            </div>
        </div>
    @endif

    {{-- 2026-06-25 — corporate "Compare plans" matrix (tier-driven, no hardcoded ids). --}}
    @if (isset($plans) && count($plans) >= 2)
        @php
            $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 2), '0'), '.') . '%';
            $compareRows = [
                [__('Monthly price'),       fn ($p) => currency($p->price)],
                [__('Student capacity'),    fn ($p) => $p->isUnlimitedStudents() ? __('Unlimited') : number_format((int) $p->student_capacity)],
                [__('One-time setup'),      fn ($p) => $p->setup_fee_custom ? __('Custom') : currency($p->setup_fee)],
                [__('Platform commission'), fn ($p) => ($p->commission_min_rate !== null && $p->commission_max_rate !== null)
                    ? (rtrim(rtrim(number_format($p->commission_min_rate, 2), '0'), '.') . '–' . rtrim(rtrim(number_format($p->commission_max_rate, 2), '0'), '.') . '%')
                    : $pct($p->platform_commission_rate)],
                [__('Settlement'),          fn ($p) => $p->direct_settlement ? __('Direct to bank') : __('Payout request')],
            ];
        @endphp
        <div class="corp-form-card" style="margin-bottom:18px;">
            <div class="corp-form-card__head">
                <h3 style="font-size:15px; font-weight:700; margin:0;"><i class="fas fa-table-list" style="color:#6366f1; margin-right:6px;"></i> {{ __('Compare plans') }}</h3>
            </div>
            <div class="mbs-compare-wrap">
                <table class="mbs-compare">
                    <thead>
                        <tr>
                            <th>{{ __('Feature') }}</th>
                            @foreach ($plans as $p)
                                <th class="{{ ($p->tier ?? '') === 'medium' ? 'is-feat' : '' }}">{{ $p->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($compareRows as [$label, $cell])
                            <tr>
                                <td class="mbs-compare__feat">{{ $label }}</td>
                                @foreach ($plans as $p)
                                    <td class="{{ ($p->tier ?? '') === 'medium' ? 'is-feat' : '' }}">{{ $cell($p) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- 2026-06-25 — FAQ (only verified, true answers) --}}
    @if (isset($plans) && count($plans) >= 1)
        <div class="corp-form-card" style="margin-bottom:18px;">
            <div class="corp-form-card__head">
                <h3 style="font-size:15px; font-weight:700; margin:0;">{{ __('Frequently asked questions') }}</h3>
            </div>
            <div style="padding:4px 16px 8px;">
                @php
                    $faqs = [
                        [__('What happens after my free trial?'), __('When the free trial ends you simply choose a paid plan to keep using premium coach features. Your courses, students and data stay safe.')],
                        [__('How do payouts work?'), __('On Starter and Medium you request a payout and it is settled to you; on Enterprise, student fees settle directly to your bank with no payout request.')],
                        [__('Is there a setup fee?'), __('Each plan has a one-time setup fee (shown on the plan). It is disclosed upfront — there are no other hidden charges.')],
                        [__('Can I use my referral wallet credit?'), __('Yes — any referral wallet balance is applied at checkout to reduce the price.')],
                        [__('Can I change my plan later?'), __('Yes, you can move to a different plan; contact us if you need help switching.')],
                    ];
                @endphp
                @foreach ($faqs as $i => [$q, $a])
                    <div class="mbs-faq__q {{ $i === 0 ? 'is-open' : '' }}" onclick="this.classList.toggle('is-open'); this.nextElementSibling.classList.toggle('is-open');">
                        <span>{{ $q }}</span> <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="mbs-faq__a {{ $i === 0 ? 'is-open' : '' }}">{{ $a }}</div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- History ────────────────────────────────────────────────── --}}
    @if ($history->count() > 0)
        <div class="corp-form-card">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title">
                    <i class="fas fa-history" style="color:var(--corp-brand);"></i>
                    {{ __('Membership History') }}
                </h6>
            </div>
            <div class="corp-table-wrap" style="border:none; border-radius:0;">
                <table class="corp-table">
                    <thead>
                        <tr>
                            <th>{{ __('Plan') }}</th>
                            <th>{{ __('Started') }}</th>
                            <th>{{ __('Expires') }}</th>
                            <th>{{ __('Cash paid') }}</th>
                            <th>{{ __('Wallet used') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $h)
                            <tr>
                                <td><strong>{{ $h->plan?->name ?? '—' }}</strong></td>
                                <td style="font-size:12.5px; color:var(--corp-muted);">{{ $h->started_at?->format('M d, Y') ?? '—' }}</td>
                                <td style="font-size:12.5px; color:var(--corp-muted);">{{ $h->expires_at?->format('M d, Y') ?? __('Lifetime') }}</td>
                                <td>{{ currency($h->price_paid) }}</td>
                                <td>{{ currency($h->wallet_credit_used) }}</td>
                                <td>
                                    @switch($h->status)
                                        @case('active')
                                            <span class="corp-pill corp-pill--success">{{ __('Active') }}</span>
                                            @break
                                        @case('pending')
                                            <span class="corp-pill corp-pill--warning">{{ __('Pending') }}</span>
                                            @break
                                        @case('expired')
                                            <span class="corp-pill corp-pill--muted">{{ __('Expired') }}</span>
                                            @break
                                        @case('cancelled')
                                            <span class="corp-pill corp-pill--danger">{{ __('Cancelled') }}</span>
                                            @break
                                    @endswitch
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- 2026-06-25 — Monthly/Annual toggle: flips price display + appends ?billing=annual
     to the checkout CTAs (only for plans that actually offer an annual price). --}}
<script>
    (function () {
        var grid = document.getElementById('mbsPlans');
        if (!grid) return;
        var btns = document.querySelectorAll('.mbs-billing-toggle__btn');
        function apply(billing) {
            grid.classList.toggle('is-annual', billing === 'annual');
            btns.forEach(function (b) { b.classList.toggle('is-active', b.dataset.billing === billing); });
            document.querySelectorAll('.js-plan-cta').forEach(function (a) {
                var base = a.dataset.base || a.getAttribute('href');
                a.setAttribute('href', (billing === 'annual' && a.dataset.annual === '1') ? (base + '?billing=annual') : base);
            });
        }
        btns.forEach(function (b) { b.addEventListener('click', function () { apply(b.dataset.billing); }); });
        // Annual is the default — wire the CTAs to it on load.
        if (grid.classList.contains('is-annual')) { apply('annual'); }
    })();
</script>
@endsection
