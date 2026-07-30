@extends('admin.master_layout')
@section('title')<title>{{ __('Landing Page Enquiries') }}</title>@endsection
@section('admin-content')
<style>
    .ale-head { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin-bottom:16px; display:flex; align-items:center; gap:16px; }
    .ale-head .thumb { width:120px; height:72px; object-fit:cover; border-radius:6px; }
    .ale-head .title { font-size:18px; font-weight:700; color:#1c1a4a; margin-bottom:2px; }
    .ale-head .meta { font-size:12px; color:#64748b; }
    .ale-status { padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; }
</style>

<div class="page-content">
    <div class="mb-2">
        <a href="{{ route('admin.coach-landing-pages.index') }}" class="text-muted">
            ← {{ __('Back to landing pages') }}
        </a>
    </div>

    <div class="ale-head">
        @php
            $tpl = $page->getTemplate;
        @endphp
        @if ($tpl && $tpl->image)
            <img src="{{ asset($tpl->image) }}" class="thumb" alt="">
        @endif
        <div style="flex:1;">
            <div class="title">{{ $page->website_name ?: $page->title }}</div>
            <div class="meta">
                <strong>{{ $page->coach->name ?? '—' }}</strong>
                @if ($tpl)
                    · {{ $tpl->template_name }}
                @endif
                @if ($tpl?->categoryname?->name)
                    · {{ $tpl->categoryname->name }}
                @endif
                · @if ($page->subdomain) {{ $page->subdomain }} @else /coach/{{ $page->slug }} @endif
            </div>
        </div>
        <div>
            @if ($page->is_published)
                <a href="{{ route('publish-landing-page.path-show', $page->slug) }}" target="_blank" rel="noopener"
                   class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-external-link-alt"></i> {{ __('View live') }}
                </a>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Contact') }}</th>
                            <th>{{ __('Service / interest') }}</th>
                            <th>{{ __('Source') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Received') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($enquiries as $e)
                            @php $badge = $e->statusBadge(); @endphp
                            <tr>
                                <td>{{ $e->id }}</td>
                                <td>{{ trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')) ?: '—' }}</td>
                                <td>
                                    @if ($e->email) <div style="font-size:12px;">✉ {{ $e->email }}</div> @endif
                                    @if ($e->phone) <div style="font-size:12px;">☎ {{ $e->phone }}</div> @endif
                                </td>
                                <td>{{ $e->service ?: '—' }}</td>
                                <td>{{ $e->sourceLabel() }}</td>
                                <td>
                                    <span class="ale-status" style="background-color: {{ $badge['color'] }}; color:#fff;">
                                        {{ $badge['label'] }}
                                    </span>
                                </td>
                                <td title="{{ $e->created_at }}">{{ $e->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    {{ __('No enquiries on this landing page yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($enquiries->hasPages())
        <div class="mt-3">{{ $enquiries->links() }}</div>
    @endif
</div>
@endsection
