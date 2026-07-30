@extends('admin.master_layout')
@section('title')
    <title>{{ $title }}</title>
@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>{{ __('Trial Sessions') }}</h1>
        </div>

        <div class="section-body">
            {{-- Totals --}}
            <div class="row">
                <div class="col-md-4">
                    <div class="card"><div class="card-body">
                        <div class="text-muted">{{ __('Enquiries') }}</div>
                        <h3 class="mb-0">{{ number_format($totals['all']) }}</h3>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card"><div class="card-body">
                        <div class="text-muted">{{ __('Paid') }}</div>
                        <h3 class="mb-0">{{ number_format($totals['paid']) }}</h3>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card"><div class="card-body">
                        <div class="text-muted">{{ __('Trial Revenue') }}</div>
                        <h3 class="mb-0">₹ {{ number_format($totals['revenue'], 2) }}</h3>
                    </div></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4>{{ __('All Trial Enquiries') }}</h4></div>
                <div class="card-body">
                    {{-- Filters --}}
                    <form action="{{ route('admin.trial-sessions.index') }}" method="GET" class="mb-3">
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <input type="text" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="{{ __('Name / email / mobile') }}">
                            </div>
                            <div class="col-md-3 form-group">
                                <select name="coach_id" class="form-control">
                                    <option value="">{{ __('All coaches') }}</option>
                                    @foreach($coaches as $c)
                                        <option value="{{ $c->id }}" {{ (string)request('coach_id')===(string)$c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 form-group">
                                <select name="status" class="form-control">
                                    <option value="">{{ __('All status') }}</option>
                                    @foreach(['pending'=>'Pending','contacted'=>'Contacted','closed'=>'Closed','cancelled'=>'Cancelled'] as $k=>$v)
                                        <option value="{{ $k }}" {{ request('status')===$k ? 'selected' : '' }}>{{ __($v) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 form-group">
                                <select name="payment_status" class="form-control">
                                    <option value="">{{ __('All payments') }}</option>
                                    @foreach(['unpaid'=>'Unpaid','paid'=>'Paid','failed'=>'Failed','free'=>'Free'] as $k=>$v)
                                        <option value="{{ $k }}" {{ request('payment_status')===$k ? 'selected' : '' }}>{{ __($v) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 form-group">
                                <select name="plan_type" class="form-control">
                                    <option value="">{{ __('All plans') }}</option>
                                    @foreach(['online'=>'Online','offline'=>'Offline'] as $k=>$v)
                                        <option value="{{ $k }}" {{ request('plan_type')===$k ? 'selected' : '' }}>{{ __($v) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 form-group">
                                <select name="course_type" class="form-control">
                                    <option value="">{{ __('All course types') }}</option>
                                    @foreach(['individual'=>'Individual','couple'=>'Couple'] as $k=>$v)
                                        <option value="{{ $k }}" {{ request('course_type')===$k ? 'selected' : '' }}>{{ __($v) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 form-group">
                                <input type="date" name="from" value="{{ request('from') }}" class="form-control" title="{{ __('From') }}">
                            </div>
                            <div class="col-md-2 form-group">
                                <input type="date" name="to" value="{{ request('to') }}" class="form-control" title="{{ __('To') }}">
                            </div>
                            <div class="col-md-2 form-group">
                                <button class="btn btn-primary btn-block">{{ __('Filter') }}</button>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('Coach') }}</th>
                                    <th>{{ __('Customer') }}</th>
                                    <th>{{ __('Plan / Course') }}</th>
                                    <th>{{ __('Time slot') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Payment') }}</th>
                                    <th>{{ __('Student') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($enquiries as $e)
                                    <tr>
                                        <td>{{ $e->coach?->name ?? '—' }}</td>
                                        <td>
                                            <div>{{ $e->name }}</div>
                                            <small class="text-muted">{{ $e->email }}<br>{{ $e->mobile }}</small>
                                        </td>
                                        <td class="text-capitalize">{{ $e->plan_type }} · {{ $e->course_type }}</td>
                                        <td>{{ $e->time_slot ?: '—' }}</td>
                                        <td>{{ (float)$e->price > 0 ? ($e->currency.' '.number_format((float)$e->price,2)) : '—' }}</td>
                                        <td>
                                            @php $badge = ['paid'=>'success','unpaid'=>'warning','failed'=>'danger','free'=>'info'][$e->payment_status] ?? 'secondary'; @endphp
                                            <span class="badge badge-{{ $badge }} text-capitalize">{{ $e->payment_status }}</span>
                                        </td>
                                        <td>
                                            @if($e->student_id && $e->student)
                                                <span class="badge badge-{{ $e->student_was_new ? 'success' : 'info' }}">{{ $e->student_was_new ? __('New') : __('Existing') }}</span>
                                                <div><small class="text-muted">{{ $e->student->name }}</small></div>
                                            @elseif(in_array($e->payment_status, ['paid','free'], true))
                                                <small class="text-muted">{{ __('Processing…') }}</small>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-capitalize">{{ $e->status }}</td>
                                        <td>{{ $e->created_at?->format('d M Y, H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No trial enquiries found.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">{{ $enquiries->links() }}</div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
