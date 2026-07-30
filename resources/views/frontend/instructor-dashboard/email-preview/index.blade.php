@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard-section-title mb-4">
    <h4 class="title">{{ __('Email Preview & Test') }}</h4>
    <p class="text-muted">{{ __('See how your branded emails look to students and send yourself a live test.') }}</p>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <label class="form-label fw-bold">{{ __('Template') }}</label>
                <select id="ep-template" class="form-select mb-3">
                    @foreach($samples as $key => $s)
                        <option value="{{ $key }}">{{ $s['label'] }}</option>
                    @endforeach
                </select>

                <form method="POST" action="{{ route('instructor.email-preview.test-send') }}">
                    @csrf
                    <input type="hidden" name="template" id="ep-template-hidden" value="live_class">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane me-1"></i> {{ __('Send test to my email') }}
                    </button>
                </form>
                <p class="text-muted small mt-2 mb-0">
                    {{ __('The test is rendered in your brand and sent to your account email.') }}
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-0">
                <iframe id="ep-frame"
                        src="{{ route('instructor.email-preview.render', ['template' => 'live_class']) }}"
                        style="width:100%; height:640px; border:0; border-radius:8px;"
                        title="{{ __('Email preview') }}"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var sel = document.getElementById('ep-template');
        var frame = document.getElementById('ep-frame');
        var hidden = document.getElementById('ep-template-hidden');
        var base = "{{ route('instructor.email-preview.render') }}";
        sel.addEventListener('change', function () {
            frame.src = base + '?template=' + encodeURIComponent(sel.value);
            hidden.value = sel.value;
        });
    })();
</script>
@endsection
