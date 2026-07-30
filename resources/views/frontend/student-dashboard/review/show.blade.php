@extends('frontend.student-dashboard.layouts.master')
<style>
    .students-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 24px;
            border-bottom: 1px solid;
    }

    .btn-add-new {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #fff;
        border: 2px solid #10b981;
        color: #10b981 !important;
        font-size: 13.5px;
        font-weight: 500;
        padding: 9px 20px;
        border-radius: 100px;
        text-decoration: none;
        transition: var(--tr);
        margin-bottom: 10px;
    }
</style>
@section('dashboard-contents')
    <div class="dashboard__content-wrap">
        <div class="dashboard__content-title">

            <div class="students-topbar">
                <h4>{{ __('Review Details') }}</h4>
                <a href="{{ route('student.reviews.index') }}" class="btn-add-new">
                    <i class="fa fa-arrow-left"></i> Go Back
                </a>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="dashboard__review-table table-responsive">
                    <table class="table">
                        <tbody>
                            <tr>
                                <td>{{ __('Course') }}</td>
                                <td>{{ $review->course->title }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('Rating') }}</td>
                                <td>
                                    @for ($i = 0; $i < $review->rating; $i++)
                                        <i class="fa fa-star text-warning"></i>
                                    @endfor
                                </td>
                            </tr>
                            <tr>
                                <td>{{ __('Review') }}</td>
                                <td>{{ $review->review }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('Date') }}</td>
                                <td>{{ formatDate($review->created_at) }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('Status') }}</td>
                                <td>
                                    @if ($review->status == 1)
                                        <div class="badge bg-success">{{ __('Approved') }}</div>
                                    @else
                                        <div class="badge bg-warning">{{ __('Pending') }}</div>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
        html[data-theme="dark"] .btn-add-new { background: #1e293b; }
    </style>
@endsection
