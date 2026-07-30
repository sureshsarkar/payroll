@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    <div class="dashboard__content-wrap">

        <div class="dashboard__content-title d-flex justify-content-between">
            <h4 class="title">{{ __('Course Analytics') }}</h4>
        </div>
        <div class="row">
            <div class="col-12">
                @include('frontend.instructor-dashboard.course.navigation')
                <div class="instructor__profile-form-wrap">
                    <form action="{{ route('instructor.courses.update') }}"
                        class="instructor__profile-form course-form d-none">
                        @csrf
                        <input type="hidden" name="course_id" id="" value="{{ $course->id }}">
                        <input type="hidden" name="step" id="" value="4">
                        <input type="hidden" name="next_step" value="5">
                    </form>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12 mb-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h6 class="title">{{ __('Study Progress Tracker') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-area">
                            <canvas id="student_progress_chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h6 class="title">{{ __('Sales In') }}
                            {{ request()->has('year') ? Carbon\Carbon::createFromFormat('Y', request('year'))->format('Y') : date('Y') }}
                        </h6>
                        <form method="get" onchange="$(this).trigger('submit');" class="d-flex gap-2">
                            <select name="year" id="year" class="form-select mb-0">
                                @php
                                    $currentYear = Carbon\Carbon::now()->year;
                                    $selectYear = request('year') ?? $currentYear;
                                @endphp
                                @for ($i = $oldestYear; $i <= $latestYear; $i++)
                                    <option value="{{ $i }}" @selected($selectYear == $i)>
                                        {{ $i }}</option>
                                @endfor
                            </select>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="chart-area">
                            <canvas id="combined_monthly_chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .chart-area {
            min-height: 300px;
            max-height: 400px;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('frontend/js/default/courses.js') }}"></script>
    <script src="{{ asset('backend/js/chart.umd.min.js') }}"></script>
    <script>
        (function($) {
            "use strict";
            $(document).ready(function() {
                renderStudentProgressChart();
                renderOrderProgressChart();
            });

        })(jQuery);

        function renderStudentProgressChart() {
            const progressRanges = @json($progress_ranges);
            const labels = Object.keys(progressRanges).map(label => label + "%");
            const data = Object.values(progressRanges);

            var ctx = document.getElementById('student_progress_chart');
            var myLineChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: "{{ __('Number of Students') }}",
                        lineTension: 0.3,
                        backgroundColor: "rgba(75, 192, 192, 0.2)",
                        borderColor: "rgba(75, 192, 192, 1)",
                        pointRadius: 3,
                        pointBackgroundColor: "rgba(78, 115, 223, 1)",
                        pointBorderColor: "rgba(78, 115, 223, 1)",
                        pointHoverRadius: 3,
                        pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                        pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        data: data,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    layout: {
                        padding: {
                            left: 10,
                            right: 25,
                            top: 25,
                            bottom: 0
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: "{{ __('Completion Percentage Ranges') }}"
                            }
                        },
                        y: {
                            min: 0,
                            max: Math.max(...data),
                            title: {
                                display: true,
                                text: "{{ __('Students') }}",
                            },
                            ticks: {
                                beginAtZero: true,
                                stepSize: 1,
                                callback: function(value) {
                                    return value.toFixed(0);
                                }
                            }
                        }
                    }
                }
            });

        }

        function renderOrderProgressChart() {
            const date_labels = @json($month_labels);
            const commission_monthly_data = @json($commission_monthly_data);
            const net_monthly_data = @json($net_monthly_data);
            const order_monthly_data = @json($order_monthly_data);

            var ctx = document.getElementById('combined_monthly_chart');
            var myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: date_labels,
                    datasets: [{
                            label: "{{ __('Order Summary') }}",
                            type: 'bar',
                            backgroundColor: "rgba(78, 115, 223, 0.7)",
                            borderColor: "rgba(78, 115, 223, 1)",
                            data: order_monthly_data,
                            order: 1,
                        },
                        {
                            label: "{{ __('Total Sales') }}",
                            type: 'line',
                            backgroundColor: "rgba(255, 205, 86, 1)",
                            borderColor: "rgba(255, 205, 86, 1)",
                            pointBackgroundColor: "rgba(255, 205, 86, 1)",
                            data: order_monthly_data,
                        },
                        {
                            label: "{{ __('Total Earnings') }}",
                            type: 'line',
                            backgroundColor: "rgba(40, 167, 69, 1)",
                            borderColor: "rgba(40, 167, 69, 1)",
                            pointBackgroundColor: "rgba(40, 167, 69, 1)",
                            data: net_monthly_data,
                        },
                        {
                            label: "{{ __('Admin Commission') }}",
                            type: 'line',
                            backgroundColor: "rgba(255, 99, 132, 1)",
                            borderColor: "rgba(255, 99, 132, 1)",
                            pointBackgroundColor: "rgba(255, 99, 132, 1)",
                            data: commission_monthly_data,
                        }
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    layout: {
                        padding: {
                            left: 10,
                            right: 25,
                            top: 25,
                            bottom: 0
                        }
                    },
                    scales: {
                        y: {
                            min: 0,
                            ticks: {
                                beginAtZero: true,
                                color: "rgba(78, 115, 223, 1)",
                                callback: function(value) {
                                    return "{{ session()->get('currency_icon') }}" + number_format(value);
                                }
                            },
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return context[0].label;
                                },
                                label: function(context) {
                                    let currencyIcon = "{{ session()->get('currency_icon') }}";
                                    let sales = order_monthly_data[context.dataIndex] || 0;
                                    let commission = commission_monthly_data[context.dataIndex] || 0;
                                    let revenue = net_monthly_data[context.dataIndex] || 0;

                                    let datasetLabel = context.dataset.label;

                                    if (datasetLabel === "{{ __('Order Summary') }}") {
                                        return [
                                            "{{ __('Total Sales') }}: " + currencyIcon + number_format(
                                                sales),
                                            "{{ __('Total Earnings') }}: " + currencyIcon + number_format(
                                                revenue),
                                            "{{ __('Admin Commission') }}: " + currencyIcon +
                                            number_format(commission)
                                        ];
                                    }
                                    if (datasetLabel === "{{ __('Total Sales') }}") {
                                        return "{{ __('Total Sales') }}: " + currencyIcon + number_format(
                                            sales);
                                    } else if (datasetLabel === "{{ __('Total Earnings') }}") {
                                        return "{{ __('Total Earnings') }}: " + currencyIcon + number_format(
                                            revenue);
                                    } else if (datasetLabel === "{{ __('Admin Commission') }}") {
                                        return "{{ __('Admin Commission') }}: " + currencyIcon + number_format(
                                            commission);
                                    }
                                }
                            }
                        }
                    }
                }
            });
        }

        function number_format(number, decimals = 2, dec_point = '.', thousands_sep = ',') {
            number = parseFloat(number).toFixed(decimals);
            let parts = number.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousands_sep);
            return parts.join(dec_point);
        }
    </script>
@endpush
