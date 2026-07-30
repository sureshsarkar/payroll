@extends('admin.master_layout')
@section('title')
    <title>{{ __('Booking Enquiries') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Booking Enquiries') }}</h1>
            </div>

            <div class="section-body">
                <div class="card">
                    <div class="card-body">
                        {{-- Filters --}}
                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-md-3 mb-2">
                                <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                    placeholder="{{ __('Name / email / mobile / class / trainer...') }}">
                            </div>
                            <div class="col-md-3 mb-2">
                                <select name="coach" class="form-control">
                                    <option value="">{{ __('All coaches') }}</option>
                                    @foreach ($coaches as $cch)
                                        <option value="{{ $cch->id }}" {{ (string) request('coach') === (string) $cch->id ? 'selected' : '' }}>{{ $cch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="payment" class="form-control">
                                    <option value="">{{ __('All payments') }}</option>
                                    @foreach (['paid'=>'Paid','pending'=>'Pending','unpaid'=>'Unpaid','failed'=>'Failed','cancelled'=>'Cancelled'] as $k=>$v)
                                        <option value="{{ $k }}" {{ request('payment') === $k ? 'selected' : '' }}>{{ __($v) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="type" class="form-control" title="{{ __('Booking type') }}">
                                    <option value="">{{ __('All types') }}</option>
                                    <option value="{{ \App\Models\CoachPricingEnquiry::TYPE_TRAINER_SESSION }}" {{ request('type') === \App\Models\CoachPricingEnquiry::TYPE_TRAINER_SESSION ? 'selected' : '' }}>{{ __('Trainer sessions') }}</option>
                                    <option value="other" {{ request('type') === 'other' ? 'selected' : '' }}>{{ __('Pricing / Schedule') }}</option>
                                </select>
                            </div>
                            <div class="col-md-1 mb-2">
                                <input type="date" name="from" value="{{ request('from') }}" class="form-control" title="{{ __('From') }}">
                            </div>
                            <div class="col-md-1 mb-2">
                                <input type="date" name="to" value="{{ request('to') }}" class="form-control" title="{{ __('To') }}">
                            </div>
                            <div class="col-md-1 mb-2">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter"></i></button>
                            </div>
                            @if(request()->hasAny(['search','coach','payment','type','from','to']))
                                <div class="col-12"><a href="{{ route('admin.booking-enquiries') }}" class="btn btn-sm btn-secondary">{{ __('Clear filters') }}</a></div>
                            @endif
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Coach') }}</th>
                                        <th>{{ __('Contact') }}</th>
                                        <th>{{ __('Class / Slot / Trainer') }}</th>
                                        <th>{{ __('Plan') }}</th>
                                        <th>{{ __('Amount') }}</th>
                                        <th>{{ __('Payment') }}</th>
                                        <th>{{ __('Txn ID') }}</th>
                                        <th>{{ __('Booking date') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($enquiries as $e)
                                        @php
                                            $d = $e->details ?? [];
                                            $pay = $e->effectivePayment();
                                            $ps = $e->payment_status ?: 'unpaid';
                                            $badge = ['paid'=>'success','pending'=>'warning','failed'=>'danger','cancelled'=>'secondary','unpaid'=>'light'][$ps] ?? 'light';
                                            $cur = $e->currency ?: 'INR'; $sym = $cur === 'INR' ? '₹' : ($cur.' ');
                                        @endphp
                                        <tr>
                                            <td>{{ optional($e->coach)->name ?: ('#'.$e->coach_id) }}</td>
                                            <td>
                                                <div>{{ $e->name }}</div>
                                                <small class="text-muted">{{ $e->email }} · {{ $e->mobile }}</small>
                                            </td>
                                            <td>
                                                @if(!empty($d['class_name']) || $e->schedule_id)<div>{{ $d['class_name'] ?? $e->schedule_id }}</div>@endif
                                                <small class="text-muted">{{ $e->time_slot }}@if($e->trainer_id) · {{ $e->trainer_id }}@endif</small>
                                            </td>
                                            <td>
                                                <div>{{ $e->category }}</div>
                                                <small class="text-muted">{{ $e->course_type }}@if($e->course_type && $e->time_period) · @endif{{ $e->time_period }}</small>
                                            </td>
                                            <td>@if(!is_null($e->plan_amount)){{ $sym }}{{ number_format((float)$e->plan_amount,2) }}@elseif($e->price)₹{{ $e->price }}@else—@endif</td>
                                            <td><span class="badge badge-{{ $badge }}">{{ ucfirst($ps) }}</span>@if($pay)<br><small class="text-muted">{{ ucfirst($pay->gateway) }}</small>@endif</td>
                                            <td><small class="text-muted">{{ $pay->transaction_id ?? '—' }}</small></td>
                                            <td><small class="text-muted">{{ $e->created_at?->format('d M Y, h:i A') }}</small></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No booking enquiries found.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{ $enquiries->links() }}
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
