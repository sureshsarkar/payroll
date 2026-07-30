@extends('frontend.instructor-dashboard.layouts.master')

{{--
    Coach (instructor) dashboard — corporate redesign (2026-05).

    Built on the platform-wide corp design system (Inter font,
    brand-aware tokens, multi-layer shadows, hairline borders) via
    @include('frontend.instructor-dashboard.settings.partials._corporate').

    This rebuild keeps all data shapes and routes from the previous
    build (audit phases 2-4 — My Content, Lifetime KPIs; the day-scoped
    "Today's Pulse" strip was retired 2026-07-18, Nav Enhancement #2)
    and adds:
      * Brand-aware accents — every KPI / pill / link retints to the
        coach's $brand->primaryColor on a white-labelled subdomain.
      * Reusable section-head class instead of inline-styled divs.
      * Action-card grid for Quick Actions instead of crammed pills.
      * Subscription modal rebuilt — Inter font, no hardcoded ₹,
        clean corp-card plan tiles, brand-coloured selection.
      * Wallet tile becomes a link to payout history for consistency.
      * Date pill separated from the subtitle.

    Teacher (CoachStaff) branch untouched — falls through to its own
    partial. Coach branch is what's rebuilt below.
--}}

{{-- ============================================================
     SUBSCRIPTION LOGIC (preserved from legacy)
     ============================================================ --}}
@php
    $userId       = auth()->id();
    $userrole     = auth('web')->user()->role;
    $subscription = null;

    if ($userId) {
        $subscription = Modules\Subscription\app\Models\SubscriptionHistory::where('user_id', $userId)
            ->latest()
            ->first();
    }

    // 2026-06-25 — Configurable trial system. Gate visibility now derives from
    // CoachTrialService ($trialStatus, passed by the controller), NOT the legacy
    // subscription_histories table or the dismantled Subscription model (which
    // returned an empty plan list → the empty paywall modal). A head coach is
    // auto-granted the trial on dashboard load, so trial/grace/paid coaches never
    // see this. It only fires once the trial AND its grace window have lapsed,
    // and only when the admin after-policy actually gates ('soft'/'hard').
    // $plans is the real MembershipPlan list passed by the controller; the CTA
    // routes to the working membership picker.
    $ts = $trialStatus ?? ['state' => 'none', 'after' => 'soft'];
    // 2026-06-25 — Dashboard subscription popup REMOVED on request. Forcing
    // $showGate=false means neither the modal CSS nor its markup renders at all
    // (no flash on hard refresh). Coaches reach plans via the "Choose plan"
    // button + the Membership page; gating of premium actions still happens in
    // the RequiresMembership middleware. To restore the popup, replace the line
    // below with the original computation kept in this comment:
    //   $showGate = ($userrole == 'instructor')
    //       && in_array($ts['state'], ['expired','none'], true)
    //       && (($ts['after'] ?? 'soft') !== 'none');
    $showGate = false;
@endphp

{{-- 2026-06-25 — Auto-popup DISABLED on request: the "Pick a plan to keep teaching"
     modal must NOT pop up automatically on the dashboard (it was appearing for newly
     registered coaches). Coaches can still open the plans via the "Choose plan" button
     and the Membership page. To re-enable the auto-popup, uncomment the block below. --}}
{{-- @if ($showGate)
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var myModal = new bootstrap.Modal(document.getElementById('subscriptionModal'));
            myModal.show();
        });
    </script>
@endif --}}

{{-- ============================================================
     SUBSCRIPTION MODAL — corporate rebuild
     2026-06-25 — gated on $showGate (configurable trial state) instead of the
     removed legacy $isExpired flag.
     ============================================================ --}}
@if ($showGate)
    <style>
        /* Modal is rendered at <body> level (Bootstrap moves it),
           so .coach-dash tokens don't reach it. Use its own scope. */
        #subscriptionModal .modal-content {
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            overflow: hidden;
            box-shadow:
                0 12px 32px -8px rgba(15, 23, 42, 0.18),
                0 24px 60px -16px rgba(15, 23, 42, 0.14);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        #subscriptionModal .modal-body { padding: 0; background: #fafbfc; }

        .sub-head {
            padding: 28px 32px 20px;
            background: #ffffff;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            text-align: left;
        }
        .sub-head__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: color-mix(in srgb, {{ $brand->primaryColor ?: '#10b981' }} 10%, #ffffff);
            color: {{ $brand->primaryColor ?: '#10b981' }};
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 10px;
        }
        .sub-head h5 {
            font-size: 22px;
            font-weight: 800;
            color: #0b1220;
            letter-spacing: -0.022em;
            margin: 0 0 6px;
        }
        .sub-head p {
            font-size: 13.5px;
            color: #6b7280;
            margin: 0;
            line-height: 1.5;
        }

        .sub-plans {
            padding: 24px 24px 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
        .sub-plan {
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 12px;
            padding: 20px 18px;
            text-align: left;
            cursor: pointer;
            position: relative;
            transition: all .18s cubic-bezier(.22,1,.36,1);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .sub-plan:hover {
            border-color: color-mix(in srgb, {{ $brand->primaryColor ?: '#10b981' }} 40%, transparent);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -6px rgba(15, 23, 42, 0.12);
        }
        .sub-plan.selected {
            border-color: {{ $brand->primaryColor ?: '#10b981' }};
            box-shadow:
                0 0 0 3px color-mix(in srgb, {{ $brand->primaryColor ?: '#10b981' }} 18%, transparent),
                0 8px 20px -6px rgba(15, 23, 42, 0.14);
        }
        .sub-plan__check {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 22px; height: 22px;
            border-radius: 50%;
            background: #fff;
            border: 1.5px solid rgba(15, 23, 42, 0.12);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            transition: all .15s cubic-bezier(.22,1,.36,1);
        }
        .sub-plan.selected .sub-plan__check {
            background: {{ $brand->primaryColor ?: '#10b981' }};
            border-color: {{ $brand->primaryColor ?: '#10b981' }};
        }
        .sub-plan__name {
            font-size: 14px;
            font-weight: 700;
            color: #0b1220;
            margin: 0 0 12px;
            letter-spacing: -0.01em;
        }
        .sub-plan__price {
            font-size: 28px;
            font-weight: 800;
            color: #0b1220;
            letter-spacing: -0.025em;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }
        .sub-plan__duration {
            font-size: 12px;
            color: #6b7280;
            margin: 4px 0 14px;
            font-weight: 500;
        }
        .sub-plan__desc {
            font-size: 12.5px;
            color: #4b5563;
            line-height: 1.55;
            margin: 0;
        }

        .sub-footer {
            padding: 20px 28px;
            background: #ffffff;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .sub-footer__note {
            font-size: 12px;
            color: #6b7280;
            margin: 0;
        }
        .sub-footer__btn {
            padding: 11px 24px;
            font-size: 13.5px;
            font-weight: 600;
            color: #fff;
            background: {{ $brand->primaryColor ?: '#10b981' }};
            border: 0;
            border-radius: 9px;
            cursor: pointer;
            box-shadow: 0 4px 12px -2px color-mix(in srgb, {{ $brand->primaryColor ?: '#10b981' }} 35%, transparent);
            transition: all .15s cubic-bezier(.22,1,.36,1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .sub-footer__btn:hover {
            background: color-mix(in srgb, {{ $brand->primaryColor ?: '#10b981' }} 88%, #000);
        }
        .sub-footer__btn:disabled {
            background: #cbd5e1;
            box-shadow: none;
            cursor: not-allowed;
        }
    </style>

    <div class="modal fade" id="subscriptionModal" tabindex="-1" role="dialog"
         aria-labelledby="subscriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 880px;" role="document">
            <div class="modal-content">
                <div class="modal-body">

                    <div class="sub-head">
                        <span class="sub-head__eyebrow">
                            <i class="fas fa-crown" style="font-size:10px;"></i>
                            {{ __('Subscription required') }}
                        </span>
                        <h5>{{ __('Pick a plan to keep teaching') }}</h5>
                        <p>{{ __('Choose the plan that fits your roster size. You can switch anytime.') }}</p>
                    </div>

                    <form action="{{ route('instructor.instructor-subscription', $userId) }}" method="POST"
                          id="subscriptionForm">
                        @csrf
                        <input type="hidden" name="subscription_plan_id" id="selectedPlan">

                        <div class="sub-plans">
                            @foreach ($plans as $plan)
                                <div class="sub-plan" data-plan-id="{{ $plan->id }}">
                                    <span class="sub-plan__check"><i class="fas fa-check"></i></span>
                                    <h6 class="sub-plan__name">{{ $plan->name }}</h6>
                                    <div class="sub-plan__price">{{ currency($plan->price) }}</div>
                                    <div class="sub-plan__duration">
                                        {{ $plan->duration_days }} {{ __('days') }}
                                    </div>
                                    <p class="sub-plan__desc">{{ $plan->description }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="sub-footer">
                            <p class="sub-footer__note">
                                <i class="fas fa-shield-alt" style="color:{{ $brand->primaryColor ?: '#10b981' }};"></i>
                                {{ __('Secure payment · cancel anytime') }}
                            </p>
                            {{-- 2026-06-25 — routes to the working membership picker
                                 (the old inline POST hit the dismantled Subscription
                                 module → empty/dead modal). --}}
                            <a href="{{ route('membership.index') }}" class="sub-footer__btn"
                               style="text-decoration:none;">
                                <i class="fas fa-arrow-right" style="font-size:11px;"></i>
                                {{ __('Choose your plan') }}
                            </a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var cards = document.querySelectorAll('.sub-plan');
            var input = document.getElementById('selectedPlan');
            var btn   = document.getElementById('subConfirmBtn');
            cards.forEach(function (card) {
                card.addEventListener('click', function () {
                    cards.forEach(function (c) { c.classList.remove('selected'); });
                    card.classList.add('selected');
                    if (input) input.value = card.getAttribute('data-plan-id');
                    if (btn)   btn.disabled = false;
                });
            });
        })();
    </script>
@endif

{{-- ============================================================
     DASHBOARD CONTENT
     ============================================================ --}}
@section('dashboard-contents')

    {{-- Teacher (CoachStaff non-instructor) gets a tailored dashboard.
         Coach branch falls through below. --}}
    @if (! empty($isTeacher) && ! empty($teacherDashboard))
        @include('frontend.instructor-dashboard.partials._teacher-dashboard')
    @else

    @include('frontend.instructor-dashboard.settings.partials._corporate')

    <style>
        /* ── Scoped helpers on top of the corp design system ───
           Only what's missing from corp primitives. Everything
           reuses var(--corp-*) so it inherits the global tokens. */
        #coachDashboard {
            --cd-brand:       {{ $brand->primaryColor ?: '#10b981' }};
            --cd-brand-2:     {{ $brand->accentColor  ?: '#059669' }};
            --cd-brand-grad:  linear-gradient(135deg, var(--cd-brand) 0%, var(--cd-brand-2) 100%);
        }

        /* Override corp brand to track coach's brand on white-label.
           corp-page already exposes --corp-brand; we redirect it. */
        #coachDashboard .corp-grad-text {
            background: var(--cd-brand-grad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Header — separate the date into its own pill so the subtitle
           reads as a single sentence. */
        .cd-date {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--corp-line);
            font-size: 12.5px;
            font-weight: 600;
            color: var(--corp-text);
            box-shadow: var(--corp-shadow-sm);
            white-space: nowrap;
        }
        .cd-date i { color: var(--cd-brand); font-size: 11px; }

        /* Section head — replaces the inline-styled divs that
           prefixed Today's Pulse / Lifetime Performance / etc. */
        .cd-section-head {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--corp-muted);
            margin: 22px 0 10px;
        }
        .cd-section-head__icon {
            width: 22px; height: 22px;
            border-radius: 6px;
            background: color-mix(in srgb, var(--cd-brand) 10%, #ffffff);
            color: var(--cd-brand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            border: 1px solid color-mix(in srgb, var(--cd-brand) 16%, transparent);
        }
        .cd-section-head__date {
            color: var(--corp-subtle);
            font-weight: 500;
            text-transform: none;
            letter-spacing: 0;
            margin-left: 4px;
            font-size: 12px;
        }

        /* My Content 3-panel grid — keeps the existing structure
           but with consistent gap/min-width. */
        .cd-mycontent {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
        }
        .cd-mycontent .corp-form-card { margin-bottom: 0; }
        .cd-mycontent .corp-form-card__title {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; letter-spacing: .4px;
        }
        .cd-mycontent .corp-form-card__title i { color: var(--cd-brand); }
        .cd-link-all {
            margin-left: auto;
            font-size: 11px;
            font-weight: 500;
            color: var(--cd-brand);
            text-decoration: none;
            text-transform: none;
            letter-spacing: 0;
        }
        .cd-link-all:hover { text-decoration: underline; }

        .cd-list-row {
            display: block;
            padding: 9px 0;
            border-bottom: 1px solid var(--corp-line-soft);
            text-decoration: none;
            color: inherit;
            transition: background .12s;
        }
        .cd-list-row:hover {
            background: color-mix(in srgb, var(--cd-brand) 5%, #ffffff);
            color: var(--corp-text);
            text-decoration: none;
        }
        .cd-list-row:last-child { border-bottom: none; }
        .cd-list-row__main {
            display: flex; justify-content: space-between; align-items: center; gap: 8px;
        }
        .cd-list-row__title {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--corp-text);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            flex: 1;
        }
        .cd-list-row__meta {
            font-size: 11px;
            color: var(--corp-muted);
            margin-top: 2px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .cd-list-row__meta i { font-size: 9px; }

        .cd-empty {
            font-size: 12px;
            color: var(--corp-muted);
            padding: 22px 8px;
            text-align: center;
            line-height: 1.55;
        }
        .cd-empty i {
            display: block;
            margin-bottom: 6px;
            color: var(--corp-subtle);
            font-size: 22px;
        }
        .cd-empty a { color: var(--cd-brand); font-weight: 600; text-decoration: none; }
        .cd-empty a:hover { text-decoration: underline; }

        /* KPI tiles as links — adds the lift cue without rewriting
           the corp-kpi__tile primitive. */
        .cd-tile-link {
            text-decoration: none;
            color: inherit;
            transition: transform .14s, box-shadow .14s, border-color .14s;
            display: block;
        }
        .cd-tile-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px -8px rgba(15, 23, 42, .14);
            color: inherit;
            text-decoration: none;
        }

        /* Quick-action card grid — replaces the row of pill buttons. */
        .cd-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-top: 6px;
        }
        .cd-action {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 14px;
            background: #fff;
            border: 1px solid var(--corp-line);
            border-radius: 11px;
            text-decoration: none;
            color: inherit;
            transition: all .15s cubic-bezier(.22,1,.36,1);
            box-shadow: var(--corp-shadow-sm);
        }
        .cd-action:hover {
            border-color: color-mix(in srgb, var(--cd-brand) 35%, var(--corp-line));
            transform: translateY(-1px);
            box-shadow: var(--corp-shadow-md);
            text-decoration: none;
            color: inherit;
        }
        .cd-action__icon {
            width: 34px; height: 34px;
            border-radius: 9px;
            background: color-mix(in srgb, var(--cd-brand) 10%, #ffffff);
            color: var(--cd-brand);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
            border: 1px solid color-mix(in srgb, var(--cd-brand) 16%, transparent);
        }
        .cd-action__body { min-width: 0; flex: 1; }
        .cd-action__title {
            font-size: 13px;
            font-weight: 600;
            color: var(--corp-text);
            line-height: 1.35;
            margin: 0 0 2px;
        }
        .cd-action__hint {
            font-size: 11px;
            color: var(--corp-muted);
            line-height: 1.4;
        }
    </style>

    <div class="corp-page" id="coachDashboard">

        {{-- ── Page header ── --}}
        <style>
            /* 2026-07-03 — dashboard header layout (Option 4, approved):
               title on the LEFT, action buttons on the RIGHT in one line;
               equal-height controls; graceful stack + scroll on mobile. */
            /* 2026-07-18 — the header must establish a stacking context ABOVE the
               dashboard cards that follow it in the DOM, otherwise the profile
               chip's dropdown (nested inside the header) is painted over by the
               trial / onboarding / membership widgets. A nested z-index can't
               escape its ancestor's paint slot, so the lift has to be here. */
            .corp-header { align-items: center; flex-wrap: nowrap; position: relative; z-index: 50; }
            /* basis 0 so the title yields space and the buttons stay on the same
               line on the right; the greeting wraps within its column if needed. */
            .corp-header__title { flex: 1 1 0; min-width: 0; }
            .idash-head-actions {
                display:flex; align-items:center; justify-content:flex-end;
                gap:10px; flex-wrap:wrap; flex:0 0 auto; margin-left:auto;
            }
            .idash-head-actions .btn-corp-primary,
            .idash-head-actions .btn-corp-secondary {
                display:inline-flex; align-items:center; justify-content:center; gap:8px;
                height:42px; padding:0 16px; white-space:nowrap; text-decoration:none;
            }
            .idash-head-actions .cd-date {
                display:inline-flex; align-items:center; gap:8px; height:42px; padding:0 14px;
                border-radius:10px; background:#fff; border:1px solid #e6e8f0; color:#475569;
                font-weight:600; font-size:13.5px; white-space:nowrap;
            }
            /* 2026-07-18 — date as a light inline line under the greeting (not a
               bordered pill), so the header reads as a clean title block now that
               the right-side action buttons are gone. */
            .cd-head-date {
                display:inline-flex; align-items:center; gap:7px; margin-top:8px;
                font-size:12.5px; font-weight:600; color:var(--corp-muted, #64748b);
            }
            .cd-head-date i { color: var(--cd-brand); font-size: 11px; }
            html[data-theme="dark"] .cd-head-date { color:#94a3b8; }
            /* Mobile: title stacks on top, buttons become a neat horizontal
               scroll strip below — never a ragged wrap. */
            @media (max-width: 767px) {
                .corp-header { align-items: flex-start; flex-wrap: wrap; }
                .corp-header__title { flex: 1 1 100%; }
                .idash-head-actions {
                    width:100%; margin-top:12px; flex-wrap:nowrap; justify-content:flex-start;
                    overflow-x:auto; -webkit-overflow-scrolling:touch; padding-bottom:6px;
                    scrollbar-width:thin;
                }
                .idash-head-actions > * { flex:0 0 auto; }
            }
        </style>
        <div class="corp-header">
            <div class="corp-header__title">
                <h4>
                    {{ __('Welcome back') }},
                    <span class="corp-grad-text">{{ userAuth()->name }}</span>
                </h4>
                <p>
                    {{ __("Your coaching home. Today's revenue, classes, and the items you're actively running — all in one place.") }}
                </p>
                {{-- 2026-07-18 — date moved from a lone right-side pill into the
                     title block (the header's action buttons were removed in Phase A,
                     which left the pill floating alone). JS still fills #idash-today's
                     inner <span>. --}}
                <div class="cd-head-date" id="idash-today">
                    <i class="fas fa-calendar-alt"></i>
                    <span>—</span>
                </div>
            </div>
            <div class="corp-header__actions idash-head-actions">
                {{-- 2026-06-25 — live trial countdown chip (configurable trial system).
                     Shows days-left during the trial and the grace window; links to
                     the plan picker. Hidden for paid/expired/none states. --}}
                @if (!empty($trialStatus) && in_array($trialStatus['state'] ?? '', ['trial', 'grace'], true))
                    @php
                        $isGrace   = ($trialStatus['state'] === 'grace');
                        $daysLeft  = (int) ($isGrace ? ($trialStatus['grace_left'] ?? 0) : ($trialStatus['days_left'] ?? 0));
                        $chipBg    = $isGrace ? '#fef3c7' : '#dcfce7';
                        $chipText  = $isGrace ? '#92400e' : '#047857';
                        $chipIcon  = $isGrace ? 'fa-hourglass-half' : 'fa-rocket';
                        $chipLabel = $isGrace
                            ? trans_choice('Trial ended · :count day grace|Trial ended · :count days grace', $daysLeft, ['count' => $daysLeft])
                            : trans_choice('Free trial · :count day left|Free trial · :count days left', $daysLeft, ['count' => $daysLeft]);
                    @endphp
                    <a href="{{ route('membership.index') }}"
                       title="{{ __('Manage your plan') }}"
                       style="display:inline-flex; align-items:center; gap:7px; padding:7px 13px; border-radius:999px;
                              background:{{ $chipBg }}; color:{{ $chipText }}; font-size:12.5px; font-weight:600;
                              text-decoration:none; white-space:nowrap;">
                        <i class="fas {{ $chipIcon }}" style="font-size:11px;"></i>{{ $chipLabel }}
                    </a>
                @endif
                {{-- 2026-07-18 (Dashboard Nav Enhancement #8) — profile-completion
                     lives here as a compact header chip (with dropdown) instead of a
                     permanent full-width card lower on the page. Auto-hides at 100%. --}}
                @include('frontend.layouts.partials.profile-completion', ['variant' => 'chip'])
                {{-- 2026-07-18 — "Get started" onboarding is now a matching header
                     chip next to the Profile chip; the large card was removed from
                     the body below. Both auto-hide at 100%. --}}
                @include('frontend.layouts.partials.coach-onboarding', ['variant' => 'chip'])
                {{-- 2026-07-18 (Dashboard Nav Enhancement #1/#4) — the header's
                     Live Classes / Instant Meeting / Analytics buttons duplicated the
                     Quick Actions cards and the sidebar. Removed here; those pages are
                     reached from Quick Actions below and the category sidebar. --}}
            </div>
        </div>

        {{-- ── Pre-built partials (membership, today's batches with attendance
                summary). The "Get started" onboarding card was moved into the
                header as a chip (see above). ── --}}
        @include('frontend.layouts.partials.membership-widget')
        @include('frontend.layouts.partials.coach-batch-attendance-widget')

        {{-- 2026-07-18 (Dashboard Nav Enhancement #2) — "Today's Pulse" section
             removed in full. Its revenue / new-orders / live-classes-today figures
             are covered by Lifetime Performance below and the Reports module, so the
             duplicate day-scoped strip (and its $coachPulse query) is retired. --}}

        {{-- ── Lifetime Performance — 6 KPI tiles ── --}}
        <div class="cd-section-head">
            <span class="cd-section-head__icon"><i class="fas fa-chart-line"></i></span>
            {{ __('Lifetime Performance') }}
            <span class="cd-section-head__date">{{ __('all-time totals') }}</span>
        </div>
        {{-- KPI icon chips — scoped <style> here so adding a unique icon
             to each tile doesn't require changing the corp-kpi primitive
             which is shared by other pages. --}}
        <style>
            #coachDashboard .corp-kpi__tile { padding-left: 70px; min-height: 96px; }
            #coachDashboard .corp-kpi__tile .cd-kpi-icon {
                position: absolute;
                top: 18px;
                left: 18px;
                width: 38px; height: 38px;
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #ffffff);
                color: var(--accent, var(--corp-brand));
                border: 1px solid color-mix(in srgb, var(--accent, var(--corp-brand)) 18%, transparent);
                font-size: 15px;
            }
        </style>

        <style>
            /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
            html[data-theme="dark"] .cd-date  { background: #1e293b; }
            html[data-theme="dark"] .cd-action { background: #1e293b; }
            html[data-theme="dark"] .idash-head-actions .cd-date { background: #1e293b; border-color: #2a3a55; color: #94a3b8; }

            /* Subscription modal (rendered only when $showGate is true; body-level scope). */
            html[data-theme="dark"] .sub-head          { background: #1e293b; }
            html[data-theme="dark"] .sub-head p        { color: #94a3b8; }
            html[data-theme="dark"] .sub-plan          { background: #1e293b; }
            html[data-theme="dark"] .sub-plan__check   { background: #1e293b; }
            html[data-theme="dark"] .sub-plan__duration { color: #94a3b8; }
            html[data-theme="dark"] .sub-footer        { background: #1e293b; }
            html[data-theme="dark"] .sub-footer__note  { color: #94a3b8; }
        </style>
        <div class="corp-kpi">
            <a href="{{ route('instructor.courses.index') }}" class="corp-kpi__tile cd-tile-link" style="--accent:var(--cd-brand);">
                <span class="cd-kpi-icon"><i class="fas fa-graduation-cap"></i></span>
                <div class="corp-kpi__label">{{ __('Total Courses') }}</div>
                <div class="corp-kpi__value">{{ number_format($totalCourses) }}</div>
                <div class="corp-kpi__sub">{{ __('all courses you offer') }}</div>
            </a>
            <a href="{{ route('instructor.courses.index') }}" class="corp-kpi__tile cd-tile-link" style="--accent:#f59e0b;">
                <span class="cd-kpi-icon"><i class="fas fa-hourglass-half"></i></span>
                <div class="corp-kpi__label">{{ __('Pending Courses') }}</div>
                <div class="corp-kpi__value" style="color:{{ $totalPendingCourses > 0 ? '#92400e' : 'var(--corp-text)' }};">
                    {{ number_format($totalPendingCourses) }}
                </div>
                <div class="corp-kpi__sub">
                    {{ $totalPendingCourses > 0 ? __('waiting on approval') : __('nothing pending') }}
                </div>
            </a>
            <a href="{{ route('instructor.my-sells.index') }}" class="corp-kpi__tile cd-tile-link" style="--accent:var(--cd-brand);">
                <span class="cd-kpi-icon"><i class="fas fa-shopping-cart"></i></span>
                <div class="corp-kpi__label">{{ __('Total Orders') }}</div>
                <div class="corp-kpi__value">{{ number_format($totalOrders) }}</div>
                <div class="corp-kpi__sub">{{ __('all-time received') }}</div>
            </a>
            <a href="{{ route('instructor.my-sells.index') }}" class="corp-kpi__tile cd-tile-link" style="--accent:#ef4444;">
                <span class="cd-kpi-icon"><i class="fas fa-exclamation-circle"></i></span>
                <div class="corp-kpi__label">{{ __('Pending Orders') }}</div>
                <div class="corp-kpi__value" style="color:{{ $totalPendingOrders > 0 ? '#b91c1c' : 'var(--corp-text)' }};">
                    {{ number_format($totalPendingOrders) }}
                </div>
                <div class="corp-kpi__sub">
                    {{ $totalPendingOrders > 0 ? __('need follow-up') : __('all clear') }}
                </div>
            </a>
            <a href="{{ route('instructor.payout.index') }}" class="corp-kpi__tile cd-tile-link" style="--accent:#10b981;">
                <span class="cd-kpi-icon"><i class="fas fa-wallet"></i></span>
                <div class="corp-kpi__label">{{ __('Current Balance') }}</div>
                <div class="corp-kpi__value">{{ currency(userAuth()->wallet_balance) }}</div>
                <div class="corp-kpi__sub">{{ __('available in your wallet') }}</div>
            </a>
            <a href="{{ route('instructor.payout.index') }}" class="corp-kpi__tile cd-tile-link" style="--accent:#14b8a6;">
                <span class="cd-kpi-icon"><i class="fas fa-hand-holding-usd"></i></span>
                <div class="corp-kpi__label">{{ __('Total Payout') }}</div>
                <div class="corp-kpi__value">{{ currency($totalWithdraw) }}</div>
                <div class="corp-kpi__sub">{{ __('approved withdrawals') }}</div>
            </a>
        </div>

        {{-- ── Quick Actions ── --}}
        <div class="cd-section-head">
            <span class="cd-section-head__icon"><i class="fas fa-bolt"></i></span>
            {{ __('Quick Actions') }}
            <span class="cd-section-head__date">{{ __('shortcuts to the pages you open most often') }}</span>
        </div>
        <div class="cd-actions">
            <a href="{{ route('instructor.courses.index') }}" class="cd-action">
                <span class="cd-action__icon"><i class="fas fa-graduation-cap"></i></span>
                <span class="cd-action__body">
                    <span class="cd-action__title">{{ __('Courses') }}</span>
                    <span class="cd-action__hint">{{ __('Create, edit, and publish') }}</span>
                </span>
            </a>
            <a href="{{ route('instructor.live-classes.index') }}" class="cd-action">
                <span class="cd-action__icon"><i class="fas fa-video"></i></span>
                <span class="cd-action__body">
                    <span class="cd-action__title">{{ __('Live Classes') }}</span>
                    <span class="cd-action__hint">{{ __('Schedule and host sessions') }}</span>
                </span>
            </a>
            {{-- 2026-07-03 — quick access to a private 1:1 meeting with a student. --}}
            <a href="{{ route('instructor.instant-meetings.index') }}" class="cd-action">
                <span class="cd-action__icon"><i class="fas fa-user-friends"></i></span>
                <span class="cd-action__body">
                    <span class="cd-action__title">{{ __('Instant Meeting 1:1') }}</span>
                    <span class="cd-action__hint">{{ __('Start a private 1:1 session now') }}</span>
                </span>
            </a>
            <a href="{{ route('instructor.course-batches.index') }}" class="cd-action">
                <span class="cd-action__icon"><i class="fas fa-layer-group"></i></span>
                <span class="cd-action__body">
                    <span class="cd-action__title">{{ __('Batches') }}</span>
                    <span class="cd-action__hint">{{ __('Manage cohorts and capacity') }}</span>
                </span>
            </a>
            <a href="{{ route('instructor.my-students.index') }}" class="cd-action">
                <span class="cd-action__icon"><i class="fas fa-users"></i></span>
                <span class="cd-action__body">
                    <span class="cd-action__title">{{ __('Students') }}</span>
                    <span class="cd-action__hint">{{ __('Roster, progress, attendance') }}</span>
                </span>
            </a>
            <a href="{{ route('instructor.fees.index') }}" class="cd-action">
                <span class="cd-action__icon"><i class="fas fa-coins"></i></span>
                <span class="cd-action__body">
                    <span class="cd-action__title">{{ __('Fees') }}</span>
                    <span class="cd-action__hint">{{ __('Plans and invoices') }}</span>
                </span>
            </a>
            <a href="{{ route('instructor.payout.index') }}" class="cd-action">
                <span class="cd-action__icon"><i class="fas fa-wallet"></i></span>
                <span class="cd-action__body">
                    <span class="cd-action__title">{{ __('Request Payout') }}</span>
                    <span class="cd-action__hint">{{ __('Withdraw to your bank') }}</span>
                </span>
            </a>
        </div>

        {{-- ── My Content (3 panels) ── --}}
        @if (isset($myContent))
            <div class="cd-section-head">
                <span class="cd-section-head__icon"><i class="fas fa-stream"></i></span>
                {{ __('My Content') }}
                <span class="cd-section-head__date">{{ __('what you are actively running') }}</span>
            </div>

            <div class="cd-mycontent">

                {{-- Upcoming Live Classes --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-video"></i>
                            {{ __('Upcoming Live Classes') }}
                            <a href="{{ route('instructor.live-classes.index') }}" class="cd-link-all">
                                {{ __('View all') }} →
                            </a>
                        </h6>
                    </div>
                    <div class="corp-form-card__body" style="padding:6px 14px 12px;">
                        @forelse ($myContent['upcoming_live'] as $lc)
                            @php
                                $startAt    = $lc->start_time ? \Carbon\Carbon::parse($lc->start_time) : null;
                                $isToday    = $startAt && $startAt->isToday();
                                $isTomorrow = $startAt && $startAt->isTomorrow();
                                $whenLabel  = $isToday ? __('Today')
                                    : ($isTomorrow ? __('Tomorrow') : ($startAt ? $startAt->format('M j') : '—'));
                                $whenClass  = $isToday ? 'corp-pill--success'
                                    : ($isTomorrow ? 'corp-pill--warning' : 'corp-pill--muted');
                            @endphp
                            <a href="{{ route('instructor.live-classes.index') }}" class="cd-list-row">
                                <div class="cd-list-row__main">
                                    <span class="cd-list-row__title">
                                        {{ \Illuminate\Support\Str::limit($lc->lesson_title ?? $lc->course_title ?? __('Live class'), 32) }}
                                    </span>
                                    <span class="corp-pill {{ $whenClass }}">{{ $whenLabel }}</span>
                                </div>
                                <div class="cd-list-row__meta">
                                    @if ($startAt)
                                        <i class="fas fa-clock"></i> {{ $startAt->format('g:i A') }}
                                    @endif
                                    @if ($lc->batch_title)
                                        · <i class="fas fa-layer-group"></i>
                                        {{ \Illuminate\Support\Str::limit($lc->batch_title, 18) }}
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="cd-empty">
                                <i class="fas fa-calendar-times"></i>
                                {{ __('No upcoming live classes') }}
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Active Batches --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-layer-group"></i>
                            {{ __('Active Batches') }}
                            <a href="{{ route('instructor.course-batches.index') }}" class="cd-link-all">
                                {{ __('View all') }} →
                            </a>
                        </h6>
                    </div>
                    <div class="corp-form-card__body" style="padding:6px 14px 12px;">
                        @forelse ($myContent['active_batches'] as $batch)
                            @php
                                $courseTitle = $batch->course?->title ?? '—';
                                $startStr    = $batch->start_date ? $batch->start_date->format('M j') : null;
                                $endStr      = $batch->end_date   ? $batch->end_date->format('M j')   : null;
                            @endphp
                            <a href="{{ route('instructor.batch-attendance.show', $batch->id) }}" class="cd-list-row">
                                <div class="cd-list-row__main">
                                    <span class="cd-list-row__title">
                                        {{ \Illuminate\Support\Str::limit($batch->title, 28) }}
                                    </span>
                                    @if ($batch->capacity)
                                        <span class="corp-pill corp-pill--muted">
                                            <i class="fas fa-users"></i> {{ $batch->capacity }}
                                        </span>
                                    @endif
                                </div>
                                <div class="cd-list-row__meta">
                                    {{ \Illuminate\Support\Str::limit($courseTitle, 30) }}
                                    @if ($startStr || $endStr)
                                        · {{ $startStr ?? '?' }} – {{ $endStr ?? '?' }}
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="cd-empty">
                                <i class="fas fa-layer-group"></i>
                                {{ __('No active batches') }}
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Recent Courses --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-graduation-cap"></i>
                            {{ __('Recent Courses') }}
                            <a href="{{ route('instructor.courses.index') }}" class="cd-link-all">
                                {{ __('View all') }} →
                            </a>
                        </h6>
                    </div>
                    <div class="corp-form-card__body" style="padding:6px 14px 12px;">
                        @forelse ($myContent['recent_courses'] as $course)
                            @php
                                $approved = $course->is_approved === 'approved';
                                $active   = $course->status === 'active';
                                if (! $approved)      { $pillClass = 'corp-pill--warning'; $pillTxt = __('Pending'); }
                                elseif (! $active)    { $pillClass = 'corp-pill--muted';   $pillTxt = __('Inactive'); }
                                else                  { $pillClass = 'corp-pill--success'; $pillTxt = __('Active'); }
                            @endphp
                            <a href="{{ route('instructor.courses.edit-view', $course->id) }}" class="cd-list-row">
                                <div class="cd-list-row__main">
                                    <span class="cd-list-row__title">
                                        {{ \Illuminate\Support\Str::limit($course->title, 30) }}
                                    </span>
                                    <span class="corp-pill {{ $pillClass }}">{{ $pillTxt }}</span>
                                </div>
                                <div class="cd-list-row__meta">
                                    <i class="fas fa-clock"></i>
                                    {{ $course->created_at?->diffForHumans() ?? '—' }}
                                </div>
                            </a>
                        @empty
                            <div class="cd-empty">
                                <i class="fas fa-graduation-cap"></i>
                                {{ __('No courses yet') }} —
                                <a href="{{ route('instructor.courses.create') }}">{{ __('Create one') }}</a>
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>
        @endif

    </div> {{-- /corp-page --}}

    <script>
        (function () {
            var el = document.querySelector('#idash-today span');
            if (el) {
                try {
                    var d = new Date();
                    el.textContent = d.toLocaleDateString(undefined, {
                        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
                    });
                } catch (e) { /* keep — if Intl unavailable */ }
            }
        })();
    </script>

    @endif {{-- /teacher-vs-coach branch (P1) --}}
@endsection
