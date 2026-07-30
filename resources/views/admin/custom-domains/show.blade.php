@extends('admin.master_layout')
@section('title')<title>{{ __('Domain') }}: {{ $domain->hostname }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-globe"></i> {{ $domain->hostname }}</h1>
            <div class="section-header-breadcrumb">
                <a href="{{ route('admin.custom-domains.index') }}" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header"><h4>{{ __('Details') }}</h4></div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><th>{{ __('Coach') }}</th><td>{{ $domain->coach?->name }} <div style="font-size:11px;color:#6b7280;">{{ $domain->coach?->email }}</div></td></tr>
                            <tr><th>{{ __('Status') }}</th><td>{{ ucfirst($domain->status) }}</td></tr>
                            <tr><th>{{ __('SSL') }}</th><td>{{ ucfirst($domain->ssl_status) }} @if($domain->ssl_checked_at)<small class="text-muted">({{ $domain->ssl_checked_at->diffForHumans() }})</small>@endif</td></tr>
                            <tr><th>{{ __('Verified at') }}</th><td>{{ $domain->verified_at?->format('d M Y, H:i') ?? '—' }}</td></tr>
                            <tr><th>{{ __('Last DNS check') }}</th><td>{{ $domain->last_verified_at?->format('d M Y, H:i') ?? '—' }}</td></tr>
                            <tr><th>{{ __('Verify attempts') }}</th><td>{{ $domain->verify_attempts }}</td></tr>
                            <tr><th>{{ __('Last error') }}</th><td>{{ $domain->last_error ?? '—' }}</td></tr>
                            <tr><th>{{ __('Approved at') }}</th><td>{{ $domain->approved_at?->format('d M Y, H:i') ?? '—' }}</td></tr>
                            <tr><th>{{ __('Rejected reason') }}</th><td>{{ $domain->rejected_reason ?? '—' }}</td></tr>
                            <tr><th>{{ __('Added') }}</th><td>{{ $domain->created_at?->format('d M Y, H:i') }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card">
                    <div class="card-header"><h4>{{ __('Audit timeline') }}</h4></div>
                    <div class="card-body" style="max-height:520px; overflow:auto;">
                        @forelse ($domain->events as $e)
                            <div style="border-left:3px solid #5751e1; padding:6px 12px; margin-bottom:10px;">
                                <strong>{{ ucfirst(str_replace('_', ' ', $e->event)) }}</strong>
                                <span class="badge badge-light">{{ $e->actor_type }}</span>
                                <div style="font-size:12px; color:#6b7280;">
                                    {{ $e->created_at?->format('d M Y, H:i:s') }}
                                    @if($e->ip) · {{ $e->ip }} @endif
                                </div>
                                @if (!empty($e->meta))
                                    <div style="font-size:11px; color:#6b7280; font-family:monospace;">{{ json_encode($e->meta) }}</div>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted">{{ __('No events recorded yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
