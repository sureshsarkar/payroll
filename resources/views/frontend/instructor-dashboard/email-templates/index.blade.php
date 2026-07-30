@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard-section-title mb-4">
    <h4 class="title">{{ __('Email Templates') }}</h4>
    <p class="text-muted">{{ __('Customise the subject and content of the emails your students and you receive. If you don\'t customise one, your branded version of the platform default is used.') }}</p>
</div>

@if(session('messege'))
    <div class="alert alert-success">{{ session('messege') }}</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="padding:14px 18px;">{{ __('Email') }}</th>
                        <th style="padding:14px 18px;">{{ __('Status') }}</th>
                        <th style="padding:14px 18px;" class="text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td style="padding:14px 18px;">
                                <span class="fw-bold">{{ __($row['label']) }}</span>
                            </td>
                            <td style="padding:14px 18px;">
                                @if($row['is_custom'])
                                    <span class="badge bg-success-subtle text-success">{{ __('Customised') }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">{{ __('Default') }}</span>
                                @endif
                            </td>
                            <td style="padding:14px 18px;" class="text-end">
                                <a href="{{ route('instructor.email-templates.edit', $row['key']) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-pen me-1"></i> {{ __('Edit') }}
                                </a>
                                @if($row['is_custom'])
                                    <form method="POST" action="{{ route('instructor.email-templates.reset', $row['key']) }}" class="d-inline"
                                          onsubmit="return confirm('{{ __('Revert this email to the default template?') }}');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-rotate-left me-1"></i> {{ __('Reset') }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="text-muted mt-3 mb-0" style="font-size:13px;">
    <i class="fas fa-circle-info me-1"></i>
    {{ __('All emails are still sent in your brand (logo, colours, sender) — this only changes the wording.') }}
</p>
@endsection
