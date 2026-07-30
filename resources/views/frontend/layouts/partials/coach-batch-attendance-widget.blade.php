{{--
    Audit 2026-05-19 — Coach dashboard "Today's Batch Attendance" widget,
    rewritten as an operator-style dashboard.

    Design intent:
      - One primary number per batch (today's attendance %)
      - A trend indicator (▲/▼ vs yesterday), nothing more
      - Status pills at the top so a coach can see "how many need me"
        without scrolling
      - Batches needing attention bubble to the top (sorted at_risk first)
      - Idle batches collapsed into a single <details> block instead of
        rendering 700+ "no class today" cards
      - Expand-on-click reveals the full live-attended / joined / absent
        breakdown — kept off by default to reduce cognitive load

    All interactivity uses the native HTML <details> element — no JS
    needed, accessible by default, works on every browser.
--}}
@php
    $widgetUser = $widgetUser ?? auth('web')->user();
    $widgetCoachId = $widgetUser && $widgetUser->role === 'instructor'
        ? $widgetUser->id
        : ($widgetUser->coach_id ?? null);

    $widgetData = null;
    if ($widgetCoachId) {
        try {
            // Cached 60s — the widget renders on every coach-panel page
            // load, so we don't want to fan out 6 GROUP-BY queries per
            // navigation. Audit 2026-05-19 phase 2.
            $widgetData = app(\App\Services\BatchAttendanceService::class)
                ->dashboardForCoachCached($widgetCoachId, now(), 60);
        } catch (\Throwable $e) {
            \Log::warning('Coach batch widget failed', ['err' => $e->getMessage()]);
        }
    }
@endphp

@if ($widgetData && ($widgetData['aggregate']['total_batches'] ?? 0) > 0)
@php
    $agg     = $widgetData['aggregate'];
    $active  = $widgetData['active'];
    $idle    = $widgetData['idle'];
    $today   = \Carbon\Carbon::parse($widgetData['date']);

    // Fingerprint — when any of these counts change, the widget reappears
    // even if the coach previously dismissed it. Same pattern as the
    // student announcements widget.
    $batchFp = implode(':', [
        $widgetData['date'] ?? '',
        $agg['total_batches'] ?? 0,
        $agg['active_today'] ?? 0,
        $agg['healthy']      ?? 0,
        $agg['at_risk']      ?? 0,
        $agg['empty']        ?? 0,
    ]);
    $batchUserId = $widgetUser?->id ?? 0;
@endphp
<div class="coach-batch-widget mb-3"
     id="coachBatchWidget"
     data-batch-fingerprint="{{ $batchFp }}"
     data-batch-user-id="{{ $batchUserId }}"
     style="background:#fff; border-radius:12px; padding:18px 20px; border:1px solid #eef0f3; position:relative;">

    {{-- Dismiss button — fingerprint-based, reappears when counts change --}}
    <button type="button" id="coachBatchWidgetClose"
            aria-label="{{ __('Hide for now') }}"
            title="{{ __('Hide for now (reappears when batch status changes)') }}"
            style="position:absolute; top:10px; right:12px;
                   background:transparent; border:none; cursor:pointer;
                   color:#9ca3af; font-size:14px; padding:4px 8px;
                   border-radius:6px; line-height:1; transition:background .15s, color .15s; z-index:2;"
            onmouseover="this.style.background='#f3f4f6'; this.style.color='#1f2937';"
            onmouseout="this.style.background='transparent'; this.style.color='#9ca3af';">
        <i class="fas fa-times"></i>
    </button>

    {{-- Header ─────────────────────────────────────────────────── --}}
    <div style="display:flex; align-items:baseline; justify-content:space-between;
                flex-wrap:wrap; gap:12px; margin-bottom:14px; padding-right:30px;">
        <div>
            <div style="font-size:15px; font-weight:600; color:#1c1a4a;">
                <i class="fas fa-users me-1" style="color:#10b981;"></i>
                {{ __("Today's Batch Attendance") }}
            </div>
            <div style="font-size:12px; color:#6b7280; margin-top:2px;">
                {{ $today->format('l, F j') }}
                @if ($agg['active_today'] > 0)
                    · <strong style="color:#1c1a4a;">{{ $agg['active_today'] }}</strong>
                    {{ trans_choice('of :n with a class today|of :n with classes today', $agg['total_batches'], ['n' => $agg['total_batches']]) }}
                @else
                    · <span>{{ __('No classes scheduled today across :n batches', ['n' => $agg['total_batches']]) }}</span>
                @endif
            </div>
        </div>

        {{-- Status chips — only render if there's something today --}}
        @if ($agg['active_today'] > 0)
            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                @if ($agg['healthy'] > 0)
                    <span style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;
                                 padding:4px 10px; border-radius:999px; font-size:11px; font-weight:600;">
                        <i class="fas fa-check-circle"></i> {{ $agg['healthy'] }} {{ __('healthy') }}
                    </span>
                @endif
                @if ($agg['at_risk'] > 0)
                    <span style="background:#fffbeb; color:#92400e; border:1px solid #fcd34d;
                                 padding:4px 10px; border-radius:999px; font-size:11px; font-weight:600;">
                        <i class="fas fa-exclamation-triangle"></i> {{ $agg['at_risk'] }} {{ __('at risk') }}
                    </span>
                @endif
                @if ($agg['empty'] > 0)
                    <span style="background:#f3f4f6; color:#4b5563; border:1px solid #d1d5db;
                                 padding:4px 10px; border-radius:999px; font-size:11px; font-weight:600;">
                        <i class="fas fa-minus-circle"></i> {{ $agg['empty'] }} {{ __('empty') }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- Active section — one card per batch with a class today ─── --}}
    @if ($active->isNotEmpty())
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));
                    gap:12px; margin-bottom:14px;">
            @foreach ($active as $b)
                @php
                    $pct      = (int) $b['today_pct'];
                    $delta    = (int) $b['trend_delta'];
                    $status   = $b['status'];

                    // Visual tokens per status
                    if ($status === 'healthy') {
                        $border  = '#10b981';
                        $hero    = '#10b981';
                        $pillBg  = '#ecfdf5';
                        $pillFg  = '#047857';
                        $pillTxt = __('Active');
                    } elseif ($status === 'at_risk') {
                        $border  = '#f59e0b';
                        $hero    = '#d97706';
                        $pillBg  = '#fffbeb';
                        $pillFg  = '#92400e';
                        $pillTxt = __('At risk');
                    } else {
                        $border  = '#9ca3af';
                        $hero    = '#6b7280';
                        $pillBg  = '#f3f4f6';
                        $pillFg  = '#4b5563';
                        $pillTxt = __('Empty');
                    }

                    // Trend visual (only show if yesterday had data)
                    $hasTrend  = ($b['yesterday_pct'] ?? 0) > 0 || $delta !== 0;
                    $trendIcon = $delta > 0 ? 'fa-arrow-up' : ($delta < 0 ? 'fa-arrow-down' : 'fa-equals');
                    $trendCol  = $delta > 0 ? '#10b981' : ($delta < 0 ? '#ef4444' : '#9ca3af');
                @endphp

                <div style="border:1px solid #eef0f3; border-left:4px solid {{ $border }};
                            border-radius:10px; padding:14px 16px; background:#fafbfc;">

                    {{-- Title row + status pill --}}
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;
                                gap:8px; margin-bottom:10px;">
                        <a href="{{ route('instructor.batch-attendance.show', $b['batch_id']) }}"
                           style="font-size:13px; font-weight:600; color:#1c1a4a; text-decoration:none;
                                  overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
                                  display:block; max-width:170px;"
                           title="{{ $b['batch_title'] }}">
                            {{ $b['batch_title'] }}
                        </a>
                        <span style="background:{{ $pillBg }}; color:{{ $pillFg }};
                                     padding:2px 8px; border-radius:999px;
                                     font-size:10px; font-weight:600; white-space:nowrap;">
                            {{ $pillTxt }}
                        </span>
                    </div>

                    {{-- Hero metric: today's attendance % --}}
                    <div style="display:flex; align-items:baseline; gap:10px; margin-bottom:2px;">
                        <span style="font-size:32px; font-weight:700; color:{{ $hero }}; line-height:1;">{{ $pct }}%</span>
                        @if ($hasTrend)
                            <span style="font-size:12px; color:{{ $trendCol }}; font-weight:600;">
                                <i class="fas {{ $trendIcon }}" style="font-size:10px;"></i>
                                {{ abs($delta) }}%
                                <span style="color:#9ca3af; font-weight:400;">{{ __('vs yesterday') }}</span>
                            </span>
                        @endif
                    </div>

                    {{-- Sub-line: counts + class count --}}
                    <div style="font-size:12px; color:#6b7280; margin-top:6px;">
                        <strong style="color:#374151;">{{ $b['attended'] }}</strong>
                        {{ __('of') }}
                        <strong style="color:#374151;">{{ $b['total_students'] }}</strong>
                        {{ __('attended') }}
                        @if (count($b['live_class_ids']) > 0)
                            ·
                            <i class="fas fa-video" style="color:#10b981; font-size:10px;"></i>
                            {{ trans_choice(':n class|:n classes', count($b['live_class_ids']), ['n' => count($b['live_class_ids'])]) }}
                        @endif
                    </div>

                    {{-- Expandable details — kept collapsed by default --}}
                    <details style="margin-top:10px;">
                        <summary style="font-size:11px; color:#10b981; cursor:pointer; outline:none;
                                        list-style:none; user-select:none;">
                            <i class="fas fa-chevron-right" style="font-size:8px; transition:transform 0.15s;"></i>
                            {{ __('More') }}
                        </summary>
                        <dl style="margin:8px 0 0 0; font-size:11px; color:#6b7280;
                                   display:grid; grid-template-columns:auto 1fr; gap:4px 10px;">
                            <dt>{{ __('Yesterday') }}</dt>
                            <dd style="margin:0;">{{ $b['yesterday_attended'] }} / {{ $b['total_students'] }} · {{ $b['yesterday_pct'] }}%</dd>

                            <dt>{{ __('Joined') }}</dt>
                            <dd style="margin:0;">{{ $b['joined'] }}
                                @if ($b['joined'] > $b['attended'])
                                    <span style="color:#9ca3af;">({{ $b['joined'] - $b['attended'] }} {{ __('unverified') }})</span>
                                @endif
                            </dd>

                            <dt>{{ __('Absent') }}</dt>
                            <dd style="margin:0;">{{ $b['not_joined'] }}</dd>
                        </dl>
                    </details>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Idle section — collapsed list of batches with no class today ─ --}}
    @if ($idle->isNotEmpty())
        <details style="background:#fafbfc; border:1px solid #eef0f3; border-radius:10px;
                        padding:0;">
            <summary style="padding:10px 14px; cursor:pointer; font-size:12px; color:#6b7280;
                            outline:none; list-style:none;">
                <i class="fas fa-moon" style="color:#9ca3af; margin-right:6px;"></i>
                {{ $idle->count() }} {{ trans_choice('batch|batches', $idle->count()) }}
                {{ __('with no class today') }}
                <span style="color:#9ca3af; font-size:11px;">— {{ __('click to expand') }}</span>
            </summary>
            <ul style="list-style:none; padding:4px 14px 12px 14px; margin:0;
                       max-height:260px; overflow-y:auto;">
                @foreach ($idle as $b)
                    <li style="padding:6px 0; border-bottom:1px solid #f0f2f5;
                               display:flex; justify-content:space-between; align-items:center;
                               font-size:12px;">
                        <a href="{{ route('instructor.batch-attendance.show', $b['batch_id']) }}"
                           style="color:#374151; text-decoration:none; flex:1;
                                  overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            {{ $b['batch_title'] }}
                        </a>
                        <span style="color:#9ca3af; font-size:11px;">
                            <i class="fas fa-users" style="font-size:9px;"></i>
                            {{ $b['total_students'] }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
<script>
(function () {
    // Dismissible widget — hides on X click, reappears when the batch
    // counts change (new at-risk batch, new empty, date rollover, etc.)
    // OR localStorage is cleared. Same pattern as the student-side
    // announcements widget (mbsguru_announcement_dismissed_<id>).
    var widget = document.getElementById('coachBatchWidget');
    if (!widget) return;

    var fp     = widget.dataset.batchFingerprint || '';
    var userId = widget.dataset.batchUserId || '0';
    var KEY    = 'mbsguru_batch_widget_dismissed_' + userId;

    try {
        var stored = localStorage.getItem(KEY);
        if (stored && stored === fp) {
            widget.style.display = 'none';
        }
    } catch (e) { /* localStorage blocked — gracefully ignore */ }

    var closeBtn = document.getElementById('coachBatchWidgetClose');
    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            try { localStorage.setItem(KEY, fp); } catch (e) { /* ignore */ }
            widget.style.transition = 'opacity .15s, max-height .2s, margin .2s, padding .2s, border .2s';
            widget.style.opacity = '0';
            widget.style.maxHeight = widget.offsetHeight + 'px';
            setTimeout(function () {
                widget.style.maxHeight = '0';
                widget.style.padding   = '0';
                widget.style.margin    = '0';
                widget.style.border    = '0';
                widget.style.overflow  = 'hidden';
            }, 10);
        });
    }
})();
</script>
@endif
