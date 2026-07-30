@extends('admin.master_layout')
@section('title')<title>{{ __('Coach Landing Pages') }}</title>@endsection
@section('admin-content')
<style>
    .alp-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:18px; }
    .alp-stat { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px; }
    .alp-stat .lbl { font-size:11px; letter-spacing:1px; text-transform:uppercase; color:#6b7280; font-weight:600; }
    .alp-stat .val { font-size:28px; font-weight:800; color:#1c1a4a; margin-top:4px; }
    .alp-stat .sub { font-size:12px; color:#94a3b8; }

    .alp-filters { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px; margin-bottom:16px; display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:10px; align-items:end; }
    .alp-filters label { font-size:11px; font-weight:600; color:#6b7280; letter-spacing:.5px; text-transform:uppercase; margin-bottom:4px; display:block; }
    .alp-filters input, .alp-filters select { width:100%; padding:8px 10px; border:1px solid #e5e7eb; border-radius:6px; font-size:13px; }

    .alp-row { vertical-align:middle; }
    .alp-row td { padding:12px 10px !important; }
    .alp-tpl-thumb { width:72px; height:42px; object-fit:cover; border-radius:4px; }
    .alp-cat-badge { background:#eef2ff; color:#3730a3; padding:2px 10px; border-radius:10px; font-size:11px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; }
    .alp-pub { background:#d1fae5; color:#065f46; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; }
    .alp-unpub { background:#fef3c7; color:#92400e; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; }
    .alp-enq-pill { background:#dbeafe; color:#1e40af; padding:3px 10px; border-radius:10px; font-size:12px; font-weight:700; }
    .alp-enq-pill.zero { background:#f1f5f9; color:#94a3b8; }

    @media (max-width: 900px) { .alp-stats { grid-template-columns: 1fr 1fr; } .alp-filters { grid-template-columns: 1fr; } }
</style>

<div class="page-content">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">{{ __('Coach Landing Pages') }}</h4>
            <small class="text-muted">Cross-coach oversight · template performance · enquiry traffic</small>
        </div>
        <a href="{{ route('admin.coach-landing-pages.templates-report') }}" class="btn btn-primary">
            <i class="fa fa-chart-line"></i> {{ __('Template Performance Report') }}
        </a>
    </div>

    <div class="alp-stats">
        <div class="alp-stat">
            <div class="lbl">{{ __('Total pages') }}</div>
            <div class="val">{{ number_format((int) ($stats->total_pages ?? 0)) }}</div>
            <div class="sub">{{ (int) ($stats->published_pages ?? 0) }} published</div>
        </div>
        <div class="alp-stat">
            <div class="lbl">{{ __('Distinct coaches') }}</div>
            <div class="val">{{ number_format((int) ($stats->distinct_coaches ?? 0)) }}</div>
            <div class="sub">with at least one page</div>
        </div>
        <div class="alp-stat">
            <div class="lbl">{{ __('Total enquiries') }}</div>
            <div class="val">{{ number_format($totalEnquiries) }}</div>
            <div class="sub">across all pages</div>
        </div>
        <div class="alp-stat">
            <div class="lbl">{{ __('Leads (last 7d)') }}</div>
            <div class="val">{{ number_format($leadsThisWeek) }}</div>
            <div class="sub">rolling window</div>
        </div>
    </div>

    <form class="alp-filters" method="get">
        <div>
            <label>{{ __('Search') }}</label>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Page name, subdomain, coach name…">
        </div>
        <div>
            <label>{{ __('Business vertical') }}</label>
            <select name="category">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $catName)
                    <option value="{{ $catName }}" @selected($filters['category'] === $catName)>{{ $catName }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>{{ __('Published') }}</label>
            <select name="published">
                <option value="">{{ __('Any') }}</option>
                <option value="1" @selected($filters['published'] === '1')>{{ __('Published only') }}</option>
                <option value="0" @selected($filters['published'] === '0')>{{ __('Drafts only') }}</option>
            </select>
        </div>
        <div>
            <button class="btn btn-primary" type="submit">{{ __('Apply') }}</button>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Template') }}</th>
                            <th>{{ __('Landing page') }}</th>
                            <th>{{ __('Coach') }}</th>
                            <th>{{ __('Vertical') }}</th>
                            <th>{{ __('Enquiries') }}</th>
                            <th>{{ __('Last lead') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $p)
                            <tr class="alp-row">
                                <td>{{ $p->id }}</td>
                                <td>
                                    @if ($p->template_image)
                                        <img src="{{ asset($p->template_image) }}" class="alp-tpl-thumb" alt="">
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="font-weight:600;">{{ $p->website_name ?: $p->title }}</div>
                                    <div style="font-size:11px;color:#94a3b8;">
                                        @if ($p->subdomain)
                                            <i class="fa fa-globe" style="opacity:.6;"></i> {{ $p->subdomain }}
                                        @else
                                            <code>/coach/{{ $p->slug }}</code>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:600;">{{ $p->coach_name ?: '—' }}</div>
                                    @if ($p->coach_email)
                                        <div style="font-size:11px;color:#94a3b8;">{{ $p->coach_email }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($p->business_category)
                                        <span class="alp-cat-badge">{{ $p->business_category }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="alp-enq-pill {{ $p->enquiry_count > 0 ? '' : 'zero' }}">
                                        {{ number_format($p->enquiry_count ?? 0) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($p->last_enquiry_at)
                                        <span title="{{ $p->last_enquiry_at }}">
                                            {{ \Carbon\Carbon::parse($p->last_enquiry_at)->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($p->is_published)
                                        <span class="alp-pub">{{ __('Published') }}</span>
                                    @else
                                        <span class="alp-unpub">{{ __('Draft') }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- M5 (2026-05-12) — aria-labels added to icon-only buttons.
                                         Row-name appended so screen readers can disambiguate
                                         when the same icon repeats down the column. --}}
                                    @if ($p->is_published)
                                        <a href="{{ route('publish-landing-page.path-show', $p->slug) }}"
                                           target="_blank" rel="noopener"
                                           class="btn btn-sm btn-outline-primary"
                                           title="{{ __('View live') }}"
                                           aria-label="{{ __('View live') }}: {{ $p->website_name ?: $p->title }}">
                                            <i class="fa fa-external-link-alt" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.coach-landing-pages.enquiries', $p->id) }}"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="{{ __('View enquiries') }}"
                                       aria-label="{{ __('View enquiries for') }} {{ $p->website_name ?: $p->title }}">
                                        <i class="fa fa-inbox" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    {{ __('No landing pages match the current filters.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($pages->hasPages())
        <div class="mt-3">{{ $pages->links() }}</div>
    @endif
</div>
@endsection
