@extends('admin.master_layout')
@section('title')<title>{{ __('Attendance Settings') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><i class="fas fa-percent"></i> {{ __('Attendance Settings') }}</h1>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">{{ __('Verified attendance threshold') }}</h4>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    {{ __('A student is marked Verified only after the live class ends AND their total attendance duration is at least this percentage of the class length. Set lower (e.g. 25%) for shorter classes; set higher (e.g. 75%) for stricter participation requirements.') }}
                </p>

                <form method="POST" action="{{ route('admin.attendance-settings.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="form-group" style="max-width:480px;">
                        <label for="attendance_min_percent">
                            {{ __('Minimum attendance percentage') }}
                            <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" name="attendance_min_percent" id="attendance_min_percent"
                                   class="form-control"
                                   min="1" max="100"
                                   value="{{ old('attendance_min_percent', $current) }}"
                                   required>
                            <div class="input-group-append">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        @error('attendance_min_percent')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                        <small class="form-text text-muted">
                            {{ __('Current value: :pct%. Example — a 60-minute class with threshold :pct% requires :min minutes of attendance to count.', ['pct' => $current, 'min' => max(1, (int) round(60 * $current / 100))]) }}
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> {{ __('Save') }}
                    </button>
                </form>

                <hr>
                <h6 class="mb-2">{{ __('How verification works') }}</h6>
                <ul class="small text-muted mb-0">
                    <li>{{ __('A scheduled task runs every 5 minutes (attendance:verify-ended-classes).') }}</li>
                    <li>{{ __('For every class whose scheduled end + 5-minute grace window has passed, it sums each student\'s total duration across all join sessions (rejoin scenarios).') }}</li>
                    <li>{{ __('Students whose total meets the threshold are marked Verified.') }}</li>
                    <li>{{ __('Manual coach marks bypass the threshold (coach decision is final).') }}</li>
                    <li>{{ __('Counters (attended / not-attended) in coach + admin dashboards only count Verified rows.') }}</li>
                </ul>
            </div>
        </div>
    </section>
</div>
@endsection
