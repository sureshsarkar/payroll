@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard__content-wrap">
    <div class="dashboard__content-title d-flex flex-wrap justify-content-between mb-3">
        <h4 class="title">{{ __('Import leads from CSV') }}</h4>
        <a href="{{ route('instructor.landing-page-enquiry.index') }}" class="btn">
            ← {{ __('Back to list') }}
        </a>
    </div>

    <div class="card mb-3" style="border:1px solid #e5e7eb;border-radius:12px;">
        <div class="card-body">
            <p class="mb-2">{{ __('Upload a CSV file. The first row must be the header.') }}</p>
            <p class="text-muted small mb-3">
                {{ __('Recognized headers (case-insensitive, common synonyms accepted):') }}
                <br>
                <code>first_name, last_name, email, phone, service, source, enquiry_type, status, message</code>
            </p>

            <form method="POST" action="{{ route('instructor.landing-page-enquiry.import.preview') }}"
                  enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-end">
                @csrf
                <div class="flex-fill">
                    <label for="csv" class="form-label small text-muted">{{ __('CSV file (max 5MB)') }}</label>
                    <input type="file" name="csv" id="csv" accept=".csv,text/csv"
                           class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('Preview') }}</button>
            </form>
        </div>
    </div>

    @if (session('errors'))
        <div class="alert alert-danger">
            {{ session('errors')->first() }}
        </div>
    @endif
</div>
@endsection
