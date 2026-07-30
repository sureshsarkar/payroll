@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard-section-title mb-4">
    <h4 class="title">{{ __('Edit email') }} — {{ __($meta['label'] ?? $key) }}</h4>
    <p class="text-muted">{{ __('Customise the wording for this email. Leave it as-is to keep your branded version of the platform default.') }}</p>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('instructor.email-templates.update', $key) }}">
                    @csrf @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-bold">{{ __('Subject') }}</label>
                        <input type="text" name="subject" class="form-control" maxlength="255"
                               value="{{ old('subject', $subject) }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">{{ __('Message') }}</label>
                        <textarea name="message" class="form-control" rows="12" required>{{ old('message', $message) }}</textarea>
                        <small class="text-muted">{{ __('Basic HTML is allowed. The email frame, your logo, colours and footer are added automatically.') }}</small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> {{ __('Save') }}
                        </button>
                        <a href="{{ route('instructor.email-templates.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                        @if($is_custom)
                            <form method="POST" action="{{ route('instructor.email-templates.reset', $key) }}" class="ms-auto"
                                  onsubmit="return confirm('{{ __('Revert to the default template?') }}');">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger">
                                    <i class="fas fa-rotate-left me-1"></i> {{ __('Reset to default') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="fas fa-code me-1"></i> {{ __('Available variables') }}</h6>
                <p class="text-muted" style="font-size:13px;">{{ __('Use these inside the subject or message — they are replaced with real values when the email is sent.') }}</p>
                <ul class="list-unstyled mb-0" style="font-size:13px;">
                    @foreach($placeholders as $token => $desc)
                        <li class="mb-2">
                            <code>&#123;&#123;{{ $token }}&#125;&#125;</code>
                            <span class="text-muted d-block">{{ $desc }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
