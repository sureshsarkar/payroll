@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@php
    $badge = $enquiry->statusBadge();
    $followUpStr = $enquiry->follow_up_at?->format('Y-m-d\TH:i');
    $followUpOverdue = $enquiry->follow_up_at && $enquiry->follow_up_at->isPast();
@endphp
<style>
    .lpe-show-header {
        display: flex; flex-wrap: wrap; gap: 12px;
        justify-content: space-between; align-items: center;
        margin-bottom: 16px;
    }
    .lpe-show-meta { display: grid; grid-template-columns: max-content 1fr; gap: 6px 14px; font-size: 13px; }
    .lpe-show-meta dt { font-weight: 600; color: #6b7280; }
    .lpe-show-meta dd { margin: 0; color: #1c1a4a; }
    .lpe-show-meta a { color: var(--corp-brand); text-decoration: none; }
    .lpe-show-meta a:hover { text-decoration: underline; }

    .lpe-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 14px; }
    .lpe-section h5 { font-size: 14px; font-weight: 700; color: #1c1a4a; margin-bottom: 12px; }

    .lpe-note {
        padding: 10px 12px;
        border-left: 3px solid var(--corp-brand);
        background: #f8fafc;
        border-radius: 6px;
        margin-bottom: 8px;
    }
    .lpe-note .meta { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
    .lpe-note .body { font-size: 13px; color: #1c1a4a; white-space: pre-wrap; }

    .lpe-followup-overdue { color: #ef4444; font-weight: 600; }
    .lpe-followup-set     { color: #10b981; font-weight: 600; }

    /* 2026-07-14 — enterprise polish (styling-only; brand-aware via --corp-brand). */
    .lpe-page .lpe-section, .lpe-page .lpe-stepper {
        border: 1px solid #edeff2; border-radius: 14px;
        box-shadow: 0 1px 2px rgba(16,24,40,.05);
        transition: box-shadow .16s ease;
    }
    .lpe-page .lpe-section:hover { box-shadow: 0 6px 18px -8px rgba(16,24,40,.13); }
    .lpe-page .lpe-section h5 {
        display: flex; align-items: center; gap: 9px;
        font-size: 13px; font-weight: 600; letter-spacing: .005em; color: #111827;
        margin: -2px 0 14px; padding-bottom: 11px; border-bottom: 1px solid #f1f2f4;
    }
    .lpe-page .lpe-section h5::before {
        content: ''; width: 4px; height: 15px; border-radius: 3px;
        background: var(--corp-brand); flex: none;
    }
    /* Brand-colored actions (replace the orange gradient with the coach brand). */
    .lpe-page .btn-primary, .lpe-page .btn-hight-basic {
        background: var(--corp-brand) !important; border-color: var(--corp-brand) !important;
        background-image: none !important; box-shadow: none !important; color: #fff !important;
    }
    .lpe-page .btn-primary:hover, .lpe-page .btn-hight-basic:hover { filter: brightness(.93); }
    .lpe-page .btn-outline-primary {
        color: var(--corp-brand) !important; background: #fff !important;
        border-color: color-mix(in srgb, var(--corp-brand) 42%, #e5e7eb) !important;
    }
    .lpe-page .btn-outline-primary:hover { background: color-mix(in srgb, var(--corp-brand) 8%, #fff) !important; color: var(--corp-brand) !important; }
    .lpe-page .lpe-show-header .title { font-size: 19px; font-weight: 700; color: #111827; }

    html[data-theme="dark"] .lpe-page .lpe-section, html[data-theme="dark"] .lpe-page .lpe-stepper { background:#1e293b; border-color:#2a3a55; box-shadow:0 1px 2px rgba(0,0,0,.3); }
    html[data-theme="dark"] .lpe-page .lpe-section h5 { color:#e2e8f0; border-bottom-color:#2a3a55; }
    html[data-theme="dark"] .lpe-page .lpe-note { background:#0f172a; }
</style>

<div class="dashboard__content-wrap lpe-page">
    <div class="lpe-show-header">
        <div>
            <h4 class="title mb-1">
                {{ trim(($enquiry->first_name ?? '') . ' ' . ($enquiry->last_name ?? '')) ?: __('Unnamed lead') }}
            </h4>
            <span class="badge"
                  style="background-color: {{ $badge['color'] }}; color:#fff; padding:6px 10px; font-weight:500;">
                <i class="fa {{ $badge['icon'] }}" style="margin-right:4px;"></i>
                {{ $badge['label'] }}
            </span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('instructor.landing-page-enquiry.edit', $enquiry->id) }}" class="btn btn-outline-primary">
                {{ __('Edit details') }}
            </a>
            <a href="{{ route('instructor.landing-page-enquiry.index') }}" class="btn">
                ← {{ __('Back to list') }}
            </a>
        </div>
    </div>

    {{-- ── Funnel stage stepper — click a stage to advance the lead; Won/Lost
         are the outcome buttons. Posts to the existing update-status endpoint
         (IDOR-gated + audit-logged). Tenant-safe + works for every coach. ── --}}
    @php
        $funnel = [
            \App\Models\LandingPageEnquiry::STATUS_NEW,
            \App\Models\LandingPageEnquiry::STATUS_CONTACTED,
            \App\Models\LandingPageEnquiry::STATUS_FOLLOWUP,
            \App\Models\LandingPageEnquiry::STATUS_QUALIFIED,
            \App\Models\LandingPageEnquiry::STATUS_PROPOSAL,
        ];
        $opts = \App\Models\LandingPageEnquiry::statusOptions();
        $cur  = $enquiry->status === 'published' ? \App\Models\LandingPageEnquiry::STATUS_NEW : $enquiry->status;
        $curIdx = array_search($cur, $funnel, true);
    @endphp
    <div class="lpe-stepper" role="group" aria-label="{{ __('Funnel stage') }}">
        @foreach ($funnel as $i => $st)
            @php $done = $curIdx !== false && $i <= $curIdx; @endphp
            <button type="button" class="lpe-step {{ $done ? 'is-done' : '' }} {{ $cur === $st ? 'is-current' : '' }}"
                    data-status="{{ $st }}" style="--step:{{ $opts[$st]['color'] }};">
                <span class="lpe-step__dot"><i class="fa {{ $opts[$st]['icon'] }}"></i></span>
                <span class="lpe-step__label">{{ __($opts[$st]['label']) }}</span>
            </button>
            @if (! $loop->last)<span class="lpe-step__line {{ ($curIdx !== false && $i < $curIdx) ? 'is-done' : '' }}"></span>@endif
        @endforeach
        <span class="lpe-step__outcomes">
            <button type="button" class="lpe-outcome won {{ $cur === \App\Models\LandingPageEnquiry::STATUS_WON ? 'active' : '' }}" data-status="{{ \App\Models\LandingPageEnquiry::STATUS_WON }}"><i class="fa fa-trophy"></i> {{ __('Won') }}</button>
            <button type="button" class="lpe-outcome lost {{ $cur === \App\Models\LandingPageEnquiry::STATUS_LOST ? 'active' : '' }}" data-status="{{ \App\Models\LandingPageEnquiry::STATUS_LOST }}"><i class="fa fa-circle-xmark"></i> {{ __('Lost') }}</button>

            @if ($enquiry->converted_user_id)
                <span class="lpe-outcome convert active" style="cursor:default;" title="{{ __('Converted to a student') }}">
                    <i class="fa fa-user-check"></i> {{ __('Converted') }}
                </span>
            @else
                <form method="POST" action="{{ route('instructor.landing-page-enquiry.convert', $enquiry->id) }}"
                      onsubmit="return confirm('{{ __('Convert this lead into a student account (and enrol them in their course if set)?') }}');"
                      style="display:inline;">
                    @csrf
                    <button type="submit" class="lpe-outcome convert"><i class="fa fa-user-plus"></i> {{ __('Convert to student') }}</button>
                </form>
            @endif
        </span>
    </div>

    @if ($enquiry->value !== null || $enquiry->won_at || $enquiry->lost_reason)
        <div class="lpe-meta-strip">
            @if ($enquiry->value !== null)
                <span class="lpe-meta"><i class="fa fa-tag"></i> {{ __('Deal value') }}: <strong>{{ currency($enquiry->value) }}</strong></span>
            @endif
            @if ($enquiry->won_at)
                <span class="lpe-meta lpe-meta--won"><i class="fa fa-trophy"></i> {{ __('Won on') }} {{ \Illuminate\Support\Carbon::parse($enquiry->won_at)->format('d M Y') }}</span>
            @endif
            @if ($enquiry->lost_reason)
                <span class="lpe-meta lpe-meta--lost"><i class="fa fa-circle-xmark"></i> {{ __('Lost') }}: {{ $enquiry->lost_reason }}</span>
            @endif
        </div>
    @endif

    <style>
        .lpe-meta-strip { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:14px; }
        .lpe-meta { font-size:13px; color:#475569; background:#f1f5f9; border-radius:8px; padding:7px 12px; }
        .lpe-meta strong { color:#0f172a; }
        .lpe-meta--won { background:#dcfce7; color:#15803d; }
        .lpe-meta--lost { background:#fef2f2; color:#b91c1c; }
        .lpe-stepper { display:flex; align-items:center; flex-wrap:wrap; gap:4px; background:#fff; border:1px solid #eef2f7; border-radius:14px; padding:16px 18px; margin-bottom:18px; box-shadow:0 1px 3px rgba(15,23,42,.04); }
        .lpe-step { display:inline-flex; flex-direction:column; align-items:center; gap:6px; border:none; background:transparent; cursor:pointer; min-width:84px; padding:4px; }
        .lpe-step__dot { width:34px; height:34px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background:#eef2f7; color:#94a3b8; font-size:14px; border:2px solid #e2e8f0; transition:all .15s; }
        .lpe-step__label { font-size:12px; font-weight:600; color:#94a3b8; }
        .lpe-step.is-done .lpe-step__dot { background:var(--step); color:#fff; border-color:var(--step); }
        .lpe-step.is-done .lpe-step__label { color:#334155; }
        .lpe-step.is-current .lpe-step__dot { box-shadow:0 0 0 4px color-mix(in srgb, var(--step) 22%, transparent); }
        .lpe-step:hover .lpe-step__dot { transform:translateY(-1px); }
        .lpe-step__line { flex:1 1 18px; height:3px; min-width:18px; background:#e2e8f0; border-radius:3px; }
        .lpe-step__line.is-done { background:#cbd5e1; }
        .lpe-step__outcomes { margin-left:auto; display:flex; gap:8px; }
        .lpe-outcome { border:1.5px solid #e2e8f0; background:#fff; border-radius:9px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all .15s; }
        .lpe-outcome.won { color:#10b981; } .lpe-outcome.won:hover, .lpe-outcome.won.active { background:#10b981; color:#fff; border-color:#10b981; }
        .lpe-outcome.lost { color:#ef4444; } .lpe-outcome.lost:hover, .lpe-outcome.lost.active { background:#ef4444; color:#fff; border-color:#ef4444; }
        .lpe-outcome.convert { color:#4f46e5; border-color:#a7f3d0; } .lpe-outcome.convert:hover, .lpe-outcome.convert.active { background:#4f46e5; color:#fff; border-color:#4f46e5; }

        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke
           components. This CRM page ships its own hardcoded light palette (not the
           design-system tokens), so the app-wide token override can't reach it;
           these rules re-theme it explicitly. `html[data-theme="dark"]` (0,1,1+)
           outranks the light class rules above regardless of source order. Semantic
           accents (brand green / won / lost / convert) are preserved. */
        html[data-theme="dark"] .lpe-section { background:#1e293b; border-color:#2a3a55; }
        html[data-theme="dark"] .lpe-section h5,
        html[data-theme="dark"] .lpe-show-meta dd,
        html[data-theme="dark"] .lpe-note .body,
        html[data-theme="dark"] .lpe-meta strong { color:#e2e8f0; }
        html[data-theme="dark"] .lpe-show-meta dt,
        html[data-theme="dark"] .lpe-note .meta { color:#94a3b8; }
        html[data-theme="dark"] .lpe-note { background:#17233a; }
        html[data-theme="dark"] .lpe-meta { background:#22304a; color:#cbd5e1; }
        html[data-theme="dark"] .lpe-meta--won { background:#08312a; color:#5dcaa5; }
        html[data-theme="dark"] .lpe-meta--won strong { color:#5dcaa5; }
        html[data-theme="dark"] .lpe-meta--lost { background:#3a1518; color:#f09595; }
        html[data-theme="dark"] .lpe-meta--lost strong { color:#f09595; }
        html[data-theme="dark"] .lpe-stepper { background:#1e293b; border-color:#2a3a55; box-shadow:none; }
        html[data-theme="dark"] .lpe-step__dot { background:#17233a; border-color:#2a3a55; color:#94a3b8; }
        html[data-theme="dark"] .lpe-step__label { color:#7c8aa0; }
        html[data-theme="dark"] .lpe-step.is-done .lpe-step__label { color:#cbd5e1; }
        html[data-theme="dark"] .lpe-step__line { background:#2a3a55; }
        html[data-theme="dark"] .lpe-step__line.is-done { background:#475569; }
        html[data-theme="dark"] .lpe-outcome { background:#1e293b; border-color:#2a3a55; }
    </style>

    <script nonce="{{ csp_nonce() }}">
    (function () {
        var URL = "{{ route('instructor.landing-page-enquiry.update-status', $enquiry->id) }}";
        var TOKEN = "{{ csrf_token() }}";
        document.querySelectorAll('.lpe-stepper [data-status]').forEach(function (b) {
            b.addEventListener('click', function () {
                var status = b.dataset.status;
                var payload = { _token: TOKEN, status: status };
                if (status === '{{ \App\Models\LandingPageEnquiry::STATUS_LOST }}') {
                    var reason = prompt("{{ __('Why is this lead lost? (optional — helps win/loss analytics)') }}", '');
                    if (reason === null) { return; } // cancelled
                    payload.lost_reason = reason;
                }
                b.disabled = true;
                fetch(URL, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': TOKEN },
                    body: new URLSearchParams(payload)
                }).then(function (r) {
                    if (!r.ok) throw new Error();
                    window.location.reload();   // re-render stepper + activity log
                }).catch(function () { b.disabled = false; alert("{{ __('Could not update stage. Please try again.') }}"); });
            });
        });
    })();
    </script>

    <div class="row">
        {{-- LEFT: contact + status + follow-up + source --}}
        <div class="col-lg-5">
            <div class="lpe-section">
                <h5>{{ __('Contact') }}</h5>
                <dl class="lpe-show-meta">
                    <dt>{{ __('Email') }}</dt>
                    <dd>
                        @if ($enquiry->email)
                            <a href="mailto:{{ $enquiry->email }}">✉ {{ $enquiry->email }}</a>
                        @else — @endif
                    </dd>
                    <dt>{{ __('Phone') }}</dt>
                    <dd>
                        @if ($enquiry->phone)
                            @php
                                $cleanPhone = preg_replace('/[^0-9+]/', '', (string) $enquiry->phone);
                                // WhatsApp wa.me link: strip leading + and any non-digit.
                                $waPhone = preg_replace('/[^0-9]/', '', (string) $enquiry->phone);
                            @endphp
                            <a href="tel:{{ $cleanPhone }}">☎ {{ $enquiry->phone }}</a>
                            ·
                            <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener"
                               style="color:#10b981;" title="{{ __('Open WhatsApp chat') }}">💬 WhatsApp</a>
                            ·
                            <a href="sms:{{ $cleanPhone }}" title="{{ __('Send SMS') }}">📱 SMS</a>
                        @else — @endif
                    </dd>
                    <dt>{{ __('Enquiry type') }}</dt>
                    <dd>{{ $enquiry->enquiry_type ?: '—' }}</dd>
                    <dt>{{ __('Service / course') }}</dt>
                    <dd>{{ $enquiry->productname?->title ?? $enquiry->service ?: '—' }}</dd>
                    <dt>{{ __('Source') }}</dt>
                    <dd>
                        <strong>{{ $enquiry->sourceLabel() }}</strong>
                        @if ($enquiry->business_category)
                            <span style="display:inline-block;background:#ecfdf5;color:#3730a3;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-left:6px;">
                                {{ $enquiry->business_category }}
                            </span>
                        @endif
                    </dd>
                    <dt>{{ __('Created') }}</dt>
                    <dd title="{{ $enquiry->created_at }}">{{ $enquiry->created_at?->diffForHumans() }}</dd>
                </dl>
            </div>

            {{-- Phase 3 — origin panel. Shown only when the lead came from a
                 landing page we can map (manual/imported leads skip it). --}}
            @if ($enquiry->landing_page_id || $enquiry->template_id)
                @php
                    $tpl  = $enquiry->template;
                    $page = $enquiry->landingPage;
                @endphp
                <div class="lpe-section">
                    <h5>{{ __('Origin') }}</h5>
                    <dl class="lpe-show-meta">
                        @if ($page)
                            <dt>{{ __('Landing page') }}</dt>
                            <dd>
                                {{-- 2026-07-10 fix: guard the null slug. A legacy landing page
                                     with an empty slug made route('publish-landing-page.path-show')
                                     throw UrlGenerationException → 500 on the details page. --}}
                                @if (filled($page->slug))
                                    <a href="{{ route('publish-landing-page.path-show', $page->slug) }}" target="_blank" rel="noopener">
                                        {{ $page->website_name ?: $page->title }}
                                        <i class="fa fa-external-link-alt" style="font-size:10px;opacity:.6;margin-left:4px;"></i>
                                    </a>
                                @else
                                    {{ $page->website_name ?: $page->title ?: __('Landing page') }}
                                @endif
                                @if ($page->subdomain)
                                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                                        {{ $page->subdomain }}
                                    </div>
                                @endif
                            </dd>
                        @endif
                        @if ($tpl)
                            <dt>{{ __('Template') }}</dt>
                            <dd>
                                @if ($tpl->image)
                                    <img src="{{ asset($tpl->image) }}" alt="" style="width:80px;height:48px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px;">
                                @endif
                                {{ $tpl->template_name }}
                            </dd>
                        @endif
                        @if ($enquiry->source_url)
                            <dt>{{ __('Referrer') }}</dt>
                            <dd>
                                <span title="{{ $enquiry->source_url }}" style="font-size:12px;color:#64748b;word-break:break-all;">
                                    {{ \Illuminate\Support\Str::limit($enquiry->source_url, 64) }}
                                </span>
                            </dd>
                        @endif

                        {{-- Phase 6 — UTM campaign attribution. Only shown when
                             the visitor arrived from a UTM-tagged URL. Pills
                             use the same styling as the source label so the
                             group reads as one "where did this come from"
                             cluster. --}}
                        @if ($enquiry->utm_source || $enquiry->utm_medium || $enquiry->utm_campaign)
                            <dt>{{ __('Campaign') }}</dt>
                            <dd>
                                @if ($enquiry->utm_source)
                                    <span title="utm_source" style="display:inline-block;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-right:4px;">
                                        {{ $enquiry->utm_source }}
                                    </span>
                                @endif
                                @if ($enquiry->utm_medium)
                                    <span title="utm_medium" style="display:inline-block;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-right:4px;">
                                        {{ $enquiry->utm_medium }}
                                    </span>
                                @endif
                                @if ($enquiry->utm_campaign)
                                    <span title="utm_campaign" style="display:inline-block;background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;">
                                        {{ $enquiry->utm_campaign }}
                                    </span>
                                @endif
                            </dd>
                        @endif
                    </dl>
                </div>
            @endif

            <div class="lpe-section">
                <h5>{{ __('Follow-up reminder') }}</h5>
                <form method="POST" action="{{ route('instructor.landing-page-enquiry.follow-up', $enquiry->id) }}"
                      class="d-flex gap-2 flex-wrap align-items-end">
                    @csrf
                    <div class="flex-fill">
                        <label for="follow_up_at" class="form-label small text-muted mb-1">
                            {{ __('Next contact date') }}
                        </label>
                        <input type="datetime-local" id="follow_up_at" name="follow_up_at"
                               class="form-control"
                               value="{{ $followUpStr }}">
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                </form>
                @if ($enquiry->follow_up_at)
                    <p class="mt-2 small {{ $followUpOverdue ? 'lpe-followup-overdue' : 'lpe-followup-set' }}">
                        @if ($followUpOverdue)
                            ⚠ {{ __('Overdue — scheduled') }}
                        @else
                            ⏰ {{ __('Scheduled') }}
                        @endif
                        {{ $enquiry->follow_up_at->format('M d, Y h:i A') }}
                        ({{ $enquiry->follow_up_at->diffForHumans() }})
                    </p>
                @else
                    <p class="mt-2 small text-muted">{{ __('No follow-up set.') }}</p>
                @endif
            </div>

            @if (filled($enquiry->message))
                <div class="lpe-section">
                    <h5>{{ __('Original enquiry message') }}</h5>
                    <div style="white-space: pre-wrap; font-size: 13px; color: #4b5563;">{{ $enquiry->message }}</div>
                </div>
            @endif

            {{-- ============== ASSIGNMENT ==============
                 Coach can assign the lead to themselves or to one of
                 their coach_staff. Server validates the target id is
                 either the coach or a staff member — see assign() in
                 LandingPageEnquiryController. --}}
            @php
                $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
                // 2026-07-10 fix: query the coach's staff from the users table (as
                // CoachStaffController does). The old DB::table('coach_staff') hit a
                // table that does not exist → 500 whenever this panel rendered.
                $staff = \App\Models\User::where('added_by', $coachId)
                    ->where('role', '!=', 'student')
                    ->select('id', 'name')
                    ->get();
                $coachUser = \App\Models\User::select('id', 'name')->find($coachId);
            @endphp
            <div class="lpe-section">
                <h5>{{ __('Assigned to') }}</h5>
                <form method="POST" action="{{ route('instructor.landing-page-enquiry.assign', $enquiry->id) }}"
                      class="d-flex gap-2 flex-wrap align-items-end">
                    @csrf
                    <div class="flex-fill">
                        <select name="assigned_to" class="form-select form-select-sm">
                            <option value="">{{ __('— Unassigned —') }}</option>
                            @if ($coachUser)
                                <option value="{{ $coachUser->id }}"
                                        @selected((int) $enquiry->assigned_to === (int) $coachUser->id)>
                                    {{ $coachUser->name }} ({{ __('coach') }})
                                </option>
                            @endif
                            @foreach ($staff as $s)
                                <option value="{{ $s->id }}"
                                        @selected((int) $enquiry->assigned_to === (int) $s->id)>
                                    {{ $s->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                </form>
            </div>

            {{-- ============== AUDIT LOG ==============
                 Append-only event stream — read directly from the
                 lead_audit_logs table for this enquiry. --}}
            @php
                $auditEvents = \App\Models\LeadAuditLog::with('actor:id,name')
                    ->where('enquiry_id', $enquiry->id)
                    ->orderByDesc('id')
                    ->limit(40)
                    ->get();
            @endphp
            <div class="lpe-section">
                <h5>
                    {{ __('Activity log') }}
                    <span class="badge bg-secondary ms-1">{{ $auditEvents->count() }}</span>
                </h5>
                @forelse ($auditEvents as $ev)
                    <div style="border-bottom:1px solid #f1f5f9;padding:6px 0;font-size:12px;">
                        <div>
                            <strong>{{ $ev->actor->name ?? __('System') }}</strong>
                            @switch($ev->event)
                                @case('status_changed')
                                    {{ __('changed status from') }}
                                    <code>{{ $ev->from_value ?: '—' }}</code> →
                                    <code>{{ $ev->to_value }}</code>
                                    @break
                                @case('assigned')
                                    @if ($ev->to_value)
                                        {{ __('assigned the lead to user #:id', ['id' => $ev->to_value]) }}
                                    @else
                                        {{ __('unassigned the lead') }}
                                    @endif
                                    @break
                                @case('email_sent')
                                    {{ __('emailed') }} <code>{{ $ev->to_value }}</code>
                                    @break
                                @case('imported')
                                    {{ __('created via CSV import') }}
                                    @break
                                @default
                                    {{ $ev->event }}
                            @endswitch
                        </div>
                        <div class="text-muted" style="font-size:11px;">{{ $ev->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">{{ __('No activity yet.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- RIGHT: notes timeline --}}
        <div class="col-lg-7">
            <div class="lpe-section">
                <h5>
                    {{ __('Notes timeline') }}
                    <span class="badge bg-secondary ms-1">{{ $enquiry->notes->count() }}</span>
                </h5>

                {{-- Add-note form. Notes are append-only; posting creates a
                     new row, never updates an existing one. --}}
                <form method="POST" action="{{ route('instructor.landing-page-enquiry.notes.add', $enquiry->id) }}"
                      class="mb-3">
                    @csrf
                    <textarea name="body" class="form-control" rows="3" maxlength="5000"
                              placeholder="{{ __('e.g. Called on Tuesday — left voicemail. Will retry Thursday.') }}"
                              required></textarea>
                    <div class="d-flex justify-content-end mt-2">
                        <button type="submit" class="btn btn-primary">
                            {{ __('Add note') }}
                        </button>
                    </div>
                </form>

                @forelse ($enquiry->notes as $note)
                    <div class="lpe-note">
                        <div class="meta">
                            <strong>{{ $note->author->name ?: __('Unknown') }}</strong>
                            · {{ $note->created_at->format('M d, Y h:i A') }}
                            ({{ $note->created_at->diffForHumans() }})
                        </div>
                        <div class="body">{{ $note->body }}</div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">{{ __('No notes yet. Add one above to start the timeline.') }}</p>
                @endforelse
            </div>

            {{-- ============== SEND EMAIL ==============
                 Minimal template variables: {first_name}, {last_name},
                 {email}, {phone}, {service}. Substitution happens server-
                 side so the recipient never sees curly-brace placeholders. --}}
            <div class="lpe-section">
                <h5>{{ __('Send email') }}</h5>
                @if (empty($enquiry->email))
                    <p class="text-muted small mb-0">{{ __('This lead has no email address on file.') }}</p>
                @else
                    <p class="text-muted small mb-2">
                        {{ __('To:') }} <strong>{{ $enquiry->email }}</strong>
                        · {{ __('Variables:') }}
                        <code>{first_name}</code> <code>{last_name}</code>
                        <code>{email}</code> <code>{phone}</code> <code>{service}</code>
                    </p>
                    <form method="POST" action="{{ route('instructor.landing-page-enquiry.email', $enquiry->id) }}">
                        @csrf
                        <input type="text" name="subject" class="form-control mb-2"
                               placeholder="{{ __('Subject') }}" required maxlength="255"
                               value="{{ old('subject', __('Following up on your enquiry')) }}">
                        <textarea name="body" class="form-control" rows="6" required maxlength="20000"
                                  placeholder="{{ __('Hi {first_name}, …') }}">{{ old('body', "Hi {first_name},\n\nThanks for your interest in {service}. I'd love to set up a quick chat — when works for you?\n\n— ") }}</textarea>
                        <div class="d-flex justify-content-end mt-2">
                            <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('{{ __('Send this email now?') }}')">
                                ✉ {{ __('Send') }}
                            </button>
                        </div>
                    </form>

                    {{-- Recent sends for this lead. Lets the coach see
                         "yes I emailed them yesterday" without searching
                         their own mailbox. --}}
                    @php
                        $recentSends = \App\Models\EmailSend::where('enquiry_id', $enquiry->id)
                            ->orderByDesc('id')->limit(5)->get();
                    @endphp
                    @if ($recentSends->isNotEmpty())
                        <hr>
                        <p class="text-muted small mb-1">{{ __('Recent sends:') }}</p>
                        @foreach ($recentSends as $send)
                            <div style="font-size:12px;border-left:3px solid {{ $send->status === 'sent' ? '#10b981' : ($send->status === 'failed' ? '#ef4444' : '#9ca3af') }};padding:4px 8px;margin-bottom:4px;">
                                <strong>{{ $send->subject }}</strong>
                                <span class="badge bg-{{ $send->status === 'sent' ? 'success' : ($send->status === 'failed' ? 'danger' : 'secondary') }}">
                                    {{ ucfirst($send->status) }}
                                </span>
                                <span class="text-muted">· {{ $send->created_at->diffForHumans() }}</span>
                                @if ($send->error)
                                    <div style="color:#ef4444;font-size:11px;">{{ \Illuminate\Support\Str::limit($send->error, 200) }}</div>
                                @endif
                            </div>
                        @endforeach
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
