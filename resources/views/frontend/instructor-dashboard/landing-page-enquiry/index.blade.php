@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@php
    // Helper used by the sortable column headers — keeps the existing
    // filters in the URL when the user clicks to re-sort.
    $sortLink = function (string $key, string $label) use ($filters) {
        $isActive = ($filters['sort'] ?? 'created_at') === $key;
        $dir = $isActive && ($filters['dir'] ?? 'desc') === 'asc' ? 'desc' : 'asc';
        $arrow = $isActive ? (($filters['dir'] ?? 'desc') === 'asc' ? ' ↑' : ' ↓') : '';
        $qs = http_build_query(array_merge($filters, ['sort' => $key, 'dir' => $dir]));
        $url = route('instructor.landing-page-enquiry.index') . '?' . $qs;
        return '<a href="' . e($url) . '" style="color:inherit;text-decoration:none;">'
            . e($label) . $arrow . '</a>';
    };
@endphp
<style>
    /* CRM-style polish — kept inline because the existing index page
       did the same, and the rest of the instructor dashboard's CSS
       is too generic to lean on for this layout. */
    .lpe-stat-card {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        padding: 14px 16px 14px 70px; min-height: 96px;
        position: relative;
        display: flex; flex-direction: column; gap: 4px;
    }
    /* Left accent bar mirrors the corp-kpi__tile look */
    .lpe-stat-card::before {
        content: ''; position: absolute;
        top: 0; bottom: 0; left: 0;
        width: 3px;
        background: var(--accent, #10b981);
    }
    .lpe-stat-card .lpe-kpi-icon {
        position: absolute; top: 14px; left: 18px;
        width: 38px; height: 38px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--accent, #10b981) 12%, #ffffff);
        color: var(--accent, #10b981);
        border: 1px solid color-mix(in srgb, var(--accent, #10b981) 18%, transparent);
        font-size: 15px;
    }
    .lpe-stat-card .label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; font-weight: 600; }
    .lpe-stat-card .value { font-size: 24px; font-weight: 700; color: #1c1a4a; line-height: 1.1; }
    .lpe-stat-card .sub   { font-size: 11px; color: #9ca3af; }
    .lpe-stat-pills { display: flex; gap: 4px; flex-wrap: wrap; }
    .lpe-stat-pill {
        font-size: 11px; font-weight: 600; padding: 2px 8px;
        border-radius: 999px; color: #fff;
    }

    .lpe-filters {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        padding: 14px 16px; margin-bottom: 14px;
    }
    .lpe-filters .form-control, .lpe-filters .form-select {
        border-radius: 8px; border-color: #e5e7eb; font-size: 13px;
    }
    .lpe-filters label { font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }

    .lpe-cell-contact {
        display: flex; flex-direction: column; gap: 2px;
        font-size: 13px;
    }
    .lpe-cell-contact a { color: var(--corp-brand); text-decoration: none; }
    .lpe-cell-contact a:hover { text-decoration: underline; }

    .lpe-status-select {
        font-size: 12px; padding: 4px 24px 4px 10px;
        border-radius: 999px;
        border: 1px solid #e5e7eb;
        background: #fff;
        font-weight: 600;
        cursor: pointer;
        min-width: 130px;
    }
    .lpe-msg-preview {
        max-width: 240px;
        font-size: 12px; color: #4b5563;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        cursor: pointer;
    }
    .lpe-msg-preview:hover { color: #1c1a4a; }
    .lpe-msg-empty { color: #cbd5e1; font-style: italic; }

    .btn-edit {
        background: #10b98130 !important; border-radius: 16px; padding: 4px 9px;
    }
    .btn-edit i { color: #10b981; }
    .btn-delete {
        background: #ef444439; border-radius: 16px; padding: 4px 9px;
        color: #fff; border: none;
    }
    .btn-delete i { color: #ef4444; }

    .lpe-toolbar {
        display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between;
        align-items: center; margin-bottom: 12px;
    }
    .lpe-toolbar .lpe-actions { display: flex; gap: 8px; }
    .btn-export {
        /* color-mix to keep the alpha-blend effect that the old #10b981XX
           hex codes carried, but with brand-aware tinting. */
        background: color-mix(in srgb, var(--corp-brand) 6%, transparent);
        color: var(--corp-brand);
        border: 1px solid color-mix(in srgb, var(--corp-brand) 18%, transparent);
        padding: 7px 14px; border-radius: 8px; font-weight: 600; font-size: 13px;
        text-decoration: none;
    }
    .btn-export:hover {
        background: color-mix(in srgb, var(--corp-brand) 12%, transparent);
        color: var(--corp-brand);
    }

    /* 2026-07-14 — enterprise polish (styling-only; brand-aware via --corp-brand). */
    .lpe-page .lpe-stat-card, .lpe-page .lpe-filters {
        border: 1px solid #edeff2; border-radius: 14px;
        box-shadow: 0 1px 2px rgba(16,24,40,.05);
        transition: box-shadow .16s ease, transform .16s ease;
    }
    .lpe-page .lpe-stat-card:hover { box-shadow: 0 6px 18px -8px rgba(16,24,40,.14); transform: translateY(-1px); }
    .lpe-page .lpe-stat-card .value { color: #111827; font-size: 25px; }
    .lpe-page .btn-primary, .lpe-page .btn-hight-basic {
        background: var(--corp-brand) !important; border-color: var(--corp-brand) !important;
        background-image: none !important; box-shadow: none !important; color: #fff !important;
    }
    .lpe-page .btn-primary:hover, .lpe-page .btn-hight-basic:hover { filter: brightness(.93); }
    .lpe-page .btn-outline-primary, .lpe-page .btn-outline-secondary {
        color: var(--corp-brand) !important; background: #fff !important;
        border-color: color-mix(in srgb, var(--corp-brand) 40%, #e5e7eb) !important;
    }
    .lpe-page .btn-outline-primary:hover, .lpe-page .btn-outline-secondary:hover { background: color-mix(in srgb, var(--corp-brand) 8%, #fff) !important; color: var(--corp-brand) !important; }
    .lpe-page table tbody tr { transition: background .12s ease; }
    .lpe-page table tbody tr:hover { background: #fafbfc; }
    html[data-theme="dark"] .lpe-page .lpe-stat-card:hover { box-shadow: 0 6px 18px -8px rgba(0,0,0,.5); }
    html[data-theme="dark"] .lpe-page table tbody tr:hover { background: #16233b; }
</style>

<style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] .lpe-stat-card { background:#1e293b; border-color:#2a3a55; }
    html[data-theme="dark"] .lpe-stat-card .label { color:#94a3b8; }
    html[data-theme="dark"] .lpe-stat-card .value { color:#e2e8f0; }
    html[data-theme="dark"] .lpe-stat-card .sub   { color:#94a3b8; }
    html[data-theme="dark"] .lpe-filters { background:#1e293b; border-color:#2a3a55; }
    html[data-theme="dark"] .lpe-filters .form-control,
    html[data-theme="dark"] .lpe-filters .form-select { border-color:#2a3a55; }
    html[data-theme="dark"] .lpe-filters label { color:#94a3b8; }
    html[data-theme="dark"] .lpe-status-select { background:#1e293b; border-color:#2a3a55; }
    html[data-theme="dark"] .lpe-msg-preview:hover { color:#e2e8f0; }
</style>

<div class="dashboard__content-wrap lpe-page">
    <div class="dashboard__content-title d-flex flex-wrap justify-content-between mb-3" style="gap:8px;">
        <h4 class="title mb-0">{{ __('Landing Page Enquiries') }}</h4>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('instructor.landing-page-enquiry.kanban') }}" class="btn btn-outline-primary">
                ⊞ {{ __('Pipeline view') }}
            </a>
            @if (userAuth()->role == 'instructor' || (!in_array(strtolower(trim(userAuth()->role)), ['instructor','student']) && checkPermissionView('landing-page-enquiry-create')))
                <a href="{{ route('instructor.landing-page-enquiry.import') }}" class="btn btn-outline-secondary">
                    ⬆ {{ __('Import CSV') }}
                </a>
                <a href="{{ route('instructor.landing-page-enquiry.create') }}" class="btn btn-primary btn-hight-basic">
                    + {{ __('Add New') }}
                </a>
            @endif
        </div>
    </div>

    {{-- ============== STATS BANNER ============== --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="lpe-stat-card" style="--accent:var(--corp-brand);">
                <span class="lpe-kpi-icon"><i class="fas fa-filter"></i></span>
                <div class="label">{{ __('Filtered total') }}</div>
                <div class="value">{{ $stats['total'] }}</div>
                <div class="sub">{{ __('matching current filters') }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="lpe-stat-card" style="--accent:#3b82f6;">
                <span class="lpe-kpi-icon"><i class="fas fa-bolt"></i></span>
                <div class="label">{{ __('New (last 7 days)') }}</div>
                <div class="value" style="color:#3b82f6;">{{ $stats['new_this_week'] }}</div>
                <div class="sub">{{ __('fresh leads to follow up') }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="lpe-stat-card" style="--accent:#10b981;">
                <span class="lpe-kpi-icon"><i class="fas fa-trophy"></i></span>
                <div class="label">{{ __('Win rate') }}</div>
                <div class="value" style="color:#10b981;">{{ $stats['win_rate'] }}%</div>
                <div class="sub">{{ __('won / (won + lost)') }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="lpe-stat-card" style="--accent:#059669;">
                <span class="lpe-kpi-icon"><i class="fas fa-chart-line"></i></span>
                <div class="label">{{ __('Conversion') }}</div>
                <div class="value" style="color:#059669;">{{ $stats['conversion'] }}%</div>
                <div class="sub">{{ __('won / all leads') }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="lpe-stat-card" style="--accent:#0ea5e9;">
                <span class="lpe-kpi-icon"><i class="fas fa-coins"></i></span>
                <div class="label">{{ __('Pipeline value') }}</div>
                <div class="value" style="color:#0ea5e9;font-size:20px;">{{ currency($stats['pipeline_value']) }}</div>
                <div class="sub">{{ __('open deals') }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="lpe-stat-card" style="--accent:#10b981;">
                <span class="lpe-kpi-icon"><i class="fas fa-trophy"></i></span>
                <div class="label">{{ __('Won value') }}</div>
                <div class="value" style="color:#10b981;font-size:20px;">{{ currency($stats['won_value']) }}</div>
                <div class="sub">{{ __('closed revenue') }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="lpe-stat-card" style="--accent:#f59e0b;">
                <span class="lpe-kpi-icon"><i class="fas fa-tags"></i></span>
                <div class="label">{{ __('By status') }}</div>
                <div class="lpe-stat-pills mt-1">
                    @foreach ($statusOptions as $key => $opt)
                        @php $c = (int) ($stats['by_status'][$key] ?? 0); @endphp
                        @if ($c > 0)
                            <span class="lpe-stat-pill" style="background-color: {{ $opt['color'] }};"
                                  title="{{ $opt['label'] }}">{{ $c }}</span>
                        @endif
                    @endforeach
                    @if (array_sum($stats['by_status']) === 0)
                        <span class="sub">—</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ============== FILTERS ============== --}}
    <form method="GET" action="{{ route('instructor.landing-page-enquiry.index') }}" class="lpe-filters">
        <div class="row g-2 align-items-end">
            <div class="col-md-4 col-12">
                <label for="lpe-q">{{ __('Search') }}</label>
                <input type="text" id="lpe-q" name="q" value="{{ $filters['q'] }}"
                       class="form-control" placeholder="{{ __('Name, email, phone, service…') }}">
            </div>
            <div class="col-md-2 col-6">
                <label for="lpe-status">{{ __('Status') }}</label>
                <select id="lpe-status" name="status" class="form-select">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($statusOptions as $key => $opt)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>
                            {{ $opt['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label for="lpe-from">{{ __('From') }}</label>
                <input type="date" id="lpe-from" name="from" value="{{ $filters['from'] }}" class="form-control">
            </div>
            <div class="col-md-2 col-6">
                <label for="lpe-to">{{ __('To') }}</label>
                <input type="date" id="lpe-to" name="to" value="{{ $filters['to'] }}" class="form-control">
            </div>
            <div class="col-md-2 col-6 d-flex gap-1">
                <button type="submit" class="btn btn-primary flex-fill">{{ __('Apply') }}</button>
                <a href="{{ route('instructor.landing-page-enquiry.index') }}"
                   class="btn btn-outline-secondary" title="{{ __('Clear filters') }}">×</a>
            </div>
        </div>
    </form>

    {{-- ============== TOOLBAR (bulk actions + export) ==============
         Bulk actions are wrapped around the WHOLE table because the
         row checkboxes need to live inside the same form. The toolbar
         visible-state JS toggles "Apply bulk" disabled until ≥1 row is
         checked. --}}
    <form method="POST" action="{{ route('instructor.landing-page-enquiry.bulk') }}" id="lpe-bulk-form">
        @csrf
        <div class="lpe-toolbar">
            <div class="text-muted small d-flex align-items-center gap-3">
                <span>
                    {{ __('Showing') }} <strong>{{ $enquiry->count() }}</strong> {{ __('of') }}
                    <strong>{{ $enquiry->total() }}</strong> {{ __('enquiries') }}
                </span>
                <span id="lpe-bulk-selected" class="badge bg-light text-dark" style="display:none;">
                    <span id="lpe-bulk-count">0</span> {{ __('selected') }}
                </span>
            </div>
            <div class="lpe-actions d-flex gap-2 flex-wrap">
                {{-- Note: no name= attribute — this select is JS-only UI.
                     The actual `action` and `status` form values are written
                     to the hidden inputs below just before submit. --}}
                <select id="lpe-bulk-action" class="form-select form-select-sm" disabled
                        style="width:auto;">
                    <option value="">{{ __('Bulk action…') }}</option>
                    <optgroup label="{{ __('Change status to') }}">
                        @foreach ($statusOptions as $key => $opt)
                            <option value="status:{{ $key }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </optgroup>
                    <option value="delete" style="color:#ef4444;">{{ __('Delete selected') }}</option>
                </select>
                <button type="button" id="lpe-bulk-apply" class="btn btn-sm btn-primary" disabled>
                    {{ __('Apply') }}
                </button>
                <a href="{{ route('instructor.landing-page-enquiry.export', request()->query()) }}"
                   class="btn-export">
                    ⬇ {{ __('Export CSV') }}
                </a>
            </div>
            {{-- Hidden inputs populated by the JS below before submit. --}}
            <input type="hidden" name="status" id="lpe-bulk-status-input">
            <input type="hidden" name="action" id="lpe-bulk-action-input">
        </div>
    </form>{{-- /lpe-bulk-form: closed BEFORE the table on purpose. The per-row
           DELETE forms in the table below must NOT be nested inside this form —
           nested <form>s are invalid HTML, so the browser merged them and a bulk
           submit carried a stray _method=DELETE, making the POST to /bulk route
           as DELETE → "MethodNotAllowed: DELETE not supported". Row checkboxes
           still post into this form via their form="lpe-bulk-form" attribute. --}}

        <div class="row">
            <div class="col-12">
                <div class="dashboard__review-table table-responsive">
                    <table class="table table-borderless align-middle">
                        <thead>
                            <tr>
                                <th style="width:32px;">
                                    <input type="checkbox" id="lpe-bulk-all" title="{{ __('Select all visible') }}">
                                </th>
                                <th>{!! $sortLink('name', __('Name')) !!}</th>
                                <th>{{ __('Contact') }}</th>
                                <th>{{ __('Enquiry') }}</th>
                                <th>{{ __('Service') }}</th>
                                <th>{{ __('Message') }}</th>
                                <th>{!! $sortLink('status', __('Status')) !!}</th>
                                <th>{{ __('Follow-up') }}</th>
                                <th>{!! $sortLink('created_at', __('Added')) !!}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                    <tbody>
                        @forelse ($enquiry as $s)
                            <tr data-enquiry-row="{{ $s->id }}">
                                <td>
                                    <input type="checkbox" name="ids[]" value="{{ $s->id }}"
                                           class="js-lpe-row-check" form="lpe-bulk-form">
                                </td>
                                <td>
                                    {{-- Clicking the name opens the detail page with notes + follow-up. --}}
                                    <a href="{{ route('instructor.landing-page-enquiry.show', $s->id) }}"
                                       style="font-weight:600;color:#1c1a4a;text-decoration:none;">
                                        {{ trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) ?: '—' }}
                                    </a>
                                    @if ($s->notes_count > 0)
                                        <span class="badge bg-light text-dark"
                                              style="font-size:10px;color:var(--corp-brand)!important;background:#eef0fb!important;"
                                              title="{{ $s->notes_count }} {{ __('notes') }}">
                                            💬 {{ $s->notes_count }}
                                        </span>
                                    @endif
                                </td>
                                {{-- Click-to-call / click-to-email turn the table into actionable
                                     surface instead of read-only display. mobile = tel: dialer
                                     opens; desktop = mailto: launches default mail client. --}}
                                <td>
                                    <div class="lpe-cell-contact">
                                        @if ($s->phone)
                                            @php
                                                $cleanPhone = preg_replace('/[^0-9+]/', '', (string) $s->phone);
                                                $waPhone    = preg_replace('/[^0-9]/', '', (string) $s->phone);
                                            @endphp
                                            <span>
                                                <a href="tel:{{ $cleanPhone }}"
                                                   title="{{ __('Call') }} {{ $s->phone }}">
                                                    ☎ {{ $s->phone }}
                                                </a> 
                                            </span>
                                        @endif
                                        @if ($s->email)
                                            <a href="mailto:{{ $s->email }}"
                                               title="{{ __('Email') }} {{ $s->email }}">
                                                ✉ {{ $s->email }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($s->enquiry_type)
                                        <span class="badge bg-success">{{ ucwords($s->enquiry_type) }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>{{ $s->productname?->title ?? $s->service ?? '—' }}</td>
                                <td>
                                    @if (filled($s->message))
                                        <div class="lpe-msg-preview"
                                             title="{{ $s->message }}"
                                             data-lpe-message="{{ $s->message }}">
                                            {{ Str::limit($s->message, 60) }}
                                        </div>
                                    @else
                                        <span class="lpe-msg-empty">—</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- In-line quick status change via the AJAX endpoint.
                                         3 clicks → 1 click. Falls back to the edit form for
                                         everything else (name, phone, email). --}}
                                    @php
                                        $current = $s->normalized_status;
                                        $cBadge  = $s->statusBadge();
                                    @endphp
                                    <select class="lpe-status-select js-lpe-status"
                                            data-id="{{ $s->id }}"
                                            data-csrf="{{ csrf_token() }}"
                                            style="border-color: {{ $cBadge['color'] }}; color: {{ $cBadge['color'] }};">
                                        @foreach ($statusOptions as $key => $opt)
                                            <option value="{{ $key }}" @selected($current === $key)>
                                                {{ $opt['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    {{-- Follow-up date cell. Overdue gets the red treatment so the
                                         coach scans the column and sees what needs action NOW. --}}
                                    @if ($s->follow_up_at)
                                        @php $overdue = $s->follow_up_at->isPast(); @endphp
                                        <span style="font-size:12px;color: {{ $overdue ? '#ef4444' : '#10b981' }};font-weight:600;"
                                              title="{{ $s->follow_up_at->format('Y-m-d H:i') }}">
                                            {{ $overdue ? '⚠' : '⏰' }} {{ $s->follow_up_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span title="{{ $s->created_at }}" style="font-size:12px;color:#6b7280;">
                                        {{ $s->created_at->diffForHumans() }}
                                    </span>
                                </td>
                                <td class="d-flex" style="gap:4px;">
                                    @if (userAuth()->role == 'instructor' || (!in_array(strtolower(trim(userAuth()->role)), ['instructor','student']) && checkPermissionView('landing-page-enquiry-edit')))
                                        <a href="{{ route('instructor.landing-page-enquiry.edit', [$s->id]) }}"
                                           class="btn-edit" title="{{ __('Edit') }}">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (userAuth()->role == 'instructor' || (!in_array(strtolower(trim(userAuth()->role)), ['instructor','student']) && checkPermissionView('landing-page-enquiry-delete')))
                                        <form action="{{ route('instructor.landing-page-enquiry.destroy', $s->id) }}"
                                              method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    onclick="return confirm('{{ __('Are you sure?') }}')"
                                                    class="btn-delete" title="{{ __('Delete') }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted p-4">
                                    {{ __('No enquiries match your filters.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $enquiry->links() }}
        </div>
    </div>
</div>

{{-- ============== MESSAGE PREVIEW MODAL ==============
     Click a truncated message → see the full text. Cheap implementation:
     populate a single shared modal from the data-lpe-message attribute. --}}
<div class="modal fade" id="lpeMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Enquiry message') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="lpeMessageModalBody" style="white-space:pre-wrap;"></div>
        </div>
    </div>
</div>

<script>
"use strict";
(function () {
    // In-line status change handler. POSTs to the AJAX endpoint, updates
    // the select's border + text color to match the new badge color so
    // the row visually reflects the change without a full reload.
    document.querySelectorAll('.js-lpe-status').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var id    = this.getAttribute('data-id');
            var csrf  = this.getAttribute('data-csrf');
            var newStatus = this.value;
            var selectEl = this;
            // Visually mark as in-flight so the user knows something
            // happened. Removed once the response is back.
            selectEl.disabled = true;
            selectEl.style.opacity = '0.6';

            var url = "{{ route('instructor.landing-page-enquiry.update-status', ['id' => '__ID__']) }}".replace('__ID__', id);
            fetch(url, {
                method:  'POST',
                headers: {
                    'X-CSRF-TOKEN':    csrf,
                    'Content-Type':    'application/json',
                    'Accept':          'application/json',
                    'X-Requested-With':'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ status: newStatus }),
            }).then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            }).then(function (data) {
                if (data && data.badge) {
                    selectEl.style.borderColor = data.badge.color;
                    selectEl.style.color       = data.badge.color;
                }
            }).catch(function (err) {
                alert("{{ __('Could not update status:') }} " + (err.message || 'unknown'));
                // Restore previous selection if we know it — we don't, so
                // just reload to re-fetch the canonical state.
                window.location.reload();
            }).finally(function () {
                selectEl.disabled = false;
                selectEl.style.opacity = '';
            });
        });
    });

    // Message preview modal — Bootstrap 5 modal API. The dashboard
    // already loads BS5 so no extra includes needed.
    var modalEl  = document.getElementById('lpeMessageModal');
    var modalBody = document.getElementById('lpeMessageModalBody');
    if (modalEl && modalBody && window.bootstrap) {
        var modal = new bootstrap.Modal(modalEl);
        document.querySelectorAll('.lpe-msg-preview').forEach(function (el) {
            el.addEventListener('click', function () {
                modalBody.textContent = el.getAttribute('data-lpe-message') || '';
                modal.show();
            });
        });
    }

    // ============== Bulk actions ==============
    // Wire row checkboxes ↔ "Select all" ↔ Apply button. The bulk-action
    // <select> uses combined "verb:value" values (e.g. "status:contacted",
    // "delete") which we split here just before submit so the server-side
    // request shape matches the controller's validate() block.
    var rowChecks   = document.querySelectorAll('.js-lpe-row-check');
    var checkAll    = document.getElementById('lpe-bulk-all');
    var bulkSelect  = document.getElementById('lpe-bulk-action');
    var bulkApply   = document.getElementById('lpe-bulk-apply');
    var bulkBadge   = document.getElementById('lpe-bulk-selected');
    var bulkCount   = document.getElementById('lpe-bulk-count');
    var statusInput = document.getElementById('lpe-bulk-status-input');
    var actionInput = document.getElementById('lpe-bulk-action-input');
    var bulkForm    = document.getElementById('lpe-bulk-form');

    function recountSelected() {
        var n = 0;
        rowChecks.forEach(function (c) { if (c.checked) n++; });
        if (bulkBadge) bulkBadge.style.display = n > 0 ? '' : 'none';
        if (bulkCount) bulkCount.textContent = n;
        var ready = n > 0 && bulkSelect && bulkSelect.value !== '';
        if (bulkSelect) bulkSelect.disabled = n === 0;
        if (bulkApply)  bulkApply.disabled  = !ready;
    }
    rowChecks.forEach(function (c) { c.addEventListener('change', recountSelected); });
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            rowChecks.forEach(function (c) { c.checked = checkAll.checked; });
            recountSelected();
        });
    }
    if (bulkSelect) bulkSelect.addEventListener('change', recountSelected);

    if (bulkApply) {
        bulkApply.addEventListener('click', function () {
            var val = (bulkSelect && bulkSelect.value) || '';
            if (!val) return;
            // Confirmation guard for the destructive option.
            if (val === 'delete' && !confirm("{{ __('Delete the selected enquiries? This cannot be undone.') }}")) {
                return;
            }
            // "status:contacted" → action=status + status=contacted
            // "delete"           → action=delete + status=(unused)
            if (val.indexOf('status:') === 0) {
                actionInput.value = 'status';
                statusInput.value = val.slice(7);
            } else {
                actionInput.value = val;
                statusInput.value = '';
            }
            bulkForm.submit();
        });
    }
    recountSelected();
})();
</script>
@endsection
