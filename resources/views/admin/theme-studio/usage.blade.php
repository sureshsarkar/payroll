@extends('admin.master_layout')
@section('title') <title>{{ __('Theme Usage — ') . $theme->name }}</title> @endsection
@section('admin-content')
<div class="main-content">
<section class="section">
<div class="section-body">

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3" style="gap:12px;">
        <a href="{{ route('admin.themes.edit', $theme->id) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h4 class="mb-0" style="font-weight:800;letter-spacing:-0.02em;">
            <i class="fa-solid fa-users" style="color:#6366F1;"></i> {{ __('Coaches using') }} "{{ $theme->name }}"
        </h4>
    </div>

    <div class="card" style="border:0;">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead>
                    <tr style="background:#F8FAFC;">
                        <th>{{ __('Coach') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Version') }}</th>
                        <th>{{ __('Applied') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $a)
                        <tr>
                            <td><strong>{{ $a->coach->name ?? '#' . $a->coach_id }}</strong></td>
                            <td>{{ $a->coach->email ?? '—' }}</td>
                            <td><code>v{{ $a->version }}</code></td>
                            <td>{{ $a->applied_at?->diffForHumans() }}</td>
                            <td>
                                @if($a->superseded_at)
                                    <span class="badge bg-secondary">{{ __('Superseded') }}</span>
                                @else
                                    <span class="badge bg-success">{{ __('Active') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">{{ __('No coaches have applied this theme yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $applications->links() }}</div>
</div>

</div>
</section>
</div>
@endsection
