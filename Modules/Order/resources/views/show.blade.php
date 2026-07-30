@extends('admin.master_layout')
@section('title')
    <title>{{ __('Order Details') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Invoice') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('Invoice') }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="invoice">
                    <div class="invoice-print">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="row">
                                    <div class="col-6">
                                        <h3>{{ __('Invoice') }}</h3>
                                        <address>
                                            <strong>{{ __('Billed To') }}:</strong><br>
                                            {{ $order->user->name }}<br>
                                            {{ __('Phone:') }} {{ $order->user->phone }}<br>
                                            {{ __('Email') }} {{ $order->user->email }}<br>
                                            {{ __('Address') }} {{ $order->user->address }}<br>
                                        </address>
                                        <address>
                                            <strong>{{ __('Payment Method') }}:</strong><br>
                                            {{ $order->payment_method }}<br>
                                        </address>
                                        <address>
                                            <strong>{{ __('Payment Status') }}:</strong><br>
                                            {{ $order->payment_status }}<br><br>
                                        </address>
                                    </div>

                                    <div class="col-6 text-right">
                                        <h6>{{ __('Order ') }} #<span
                                                class="text-primary">{{ $order->invoice_id }}</span></h6>
                                        <address>
                                            <strong>{{ __('Order Date') }}:</strong><br>
                                            {{ formatDate($order->created_at) }}<br><br>
                                        </address>
                                        @if ($order->isBundleOrder())
                                            <address>
                                                <strong>{{ __('Bundle Name') }}:</strong><br>
                                                {{ $order?->order_details?->title }}<br>
                                            </address>
                                        @endif
                                        @if ($order->isGiftOrder())
                                            <address>
                                                <strong>{{ __('Gift') }}:</strong><br>
                                                {{ __('Recipient Name') }}:
                                                {{ $order?->order_details?->recipient_name }}<br>
                                                {{ __('Recipient Email') }}:
                                                {{ $order?->order_details?->recipient_email }}<br>
                                                @if (empty($order?->order_details?->verification_token))
                                                    <div class="badge badge-success my-2">{{ __('Claimed') }}</div>
                                                @else
                                                    <div class="badge badge-warning my-2">{{ __('Pending') }}</div>
                                                    <br>
                                                    <a href="{{route('admin.resend.gift-claim-mail',$order?->invoice_id)}}" class="btn btn-sm btn-success">{{__('Re Send Claim Email')}}</a>
                                                @endif
                                            </address>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="section-title">{{ __('Order Summary') }}</div>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover table-md">
                                        <tr>
                                            <th data-width="40">#</th>
                                            <th>{{ __('Item') }}</th>
                                            <th>{{ __('by') }}</th>
                                            <th class="text-center">{{ __('Price') }}</th>
                                        </tr>
                                        @foreach ($order->orderItems as $item)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $item->course->title }}</td>
                                                <td>
                                                    {{ $item->course->instructor->name }}
                                                    <br>
                                                    {{ $item->course->instructor->email }}
                                                </td>
                                                <td class="text-center">
                                                    @if ($order->isBundleOrder())
                                                        --
                                                    @else
                                                        {{ number_format($item->price * $order->conversion_rate, 2) }}
                                                        {{ $order->payable_currency }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-lg-4">
                                        {{-- @if (\Modules\BasicPayment\app\Services\PaymentMethodService::OFFLINE_PAYMENT == $order->payment_method)
                                            <div class="section-title">{{ __('Payment Receipt File') }}</div>
                                            <a class="btn btn-sm btn-success text-white cursor-pointer"
                                                href="{{ route('admin.download.payment-receipt', $order->id) }}"><i
                                                    class="fas fa-download"></i> {{ __('Download Receipt') }}</a>
                                        @endif --}}

                                        <form action="{{ route('admin.order.update', $order->id) }}" method="POST"
                                            class="d-print-none">
                                            @csrf
                                            <div class="section-title">{{ __('Payment Status') }}</div>
                                            <select name="payment_status" class="form-control" id=""
                                                {{ $order->payment_status == 'paid' ? 'disabled' : '' }}>
                                                <option value="pending" @selected(old('payment_status', $order->payment_status) == 'pending')>{{ __('Pending') }}
                                                </option>
                                                <option value="paid" @selected(old('payment_status', $order->payment_status) == 'paid')>{{ __('Paid') }}
                                                </option>
                                                <option value="cancelled" @selected(old('payment_status', $order->payment_status) == 'cancelled')>
                                                    {{ __('Cancelled') }}</option>
                                            </select>

                                            <div class="section-title">{{ __('Order Status') }}</div>
                                            <select name="order_status" class="form-control" id="">
                                                <option value="pending" @selected(old('order_status', $order->status) == 'pending')>{{ __('Pending') }}
                                                </option>
                                                <option value="processing" @selected(old('order_status', $order->status) == 'processing')>
                                                    {{ __('Processing') }}</option>
                                                <option value="completed" @selected(old('order_status', $order->status) == 'completed')>
                                                    {{ __('Completed') }}</option>
                                                <option value="declined" @selected(old('order_status', $order->status) == 'declined')>{{ __('Declined') }}
                                                </option>
                                            </select>
                                            <button type="submit"
                                                class="btn btn-primary mt-4">{{ __('Update') }}</button>
                                        </form>
                                    </div>
                                    <div class="col-lg-8 text-right">
                                        @if ($order->isBundleOrder())
                                            @php
                                                $subTotal = $order->payable_amount;
                                                $subTotalWithCharge = $subTotal * $order->conversion_rate;
                                                $gatewayCharge = 0;
                                                if ($order->gateway_charge > 0) {
                                                    $gatewayCharge =
                                                        ($order->gateway_charge / $subTotalWithCharge) * 100;
                                                }
                                                $total = number_format($subTotalWithCharge + $order->gateway_charge, 2);
                                            @endphp
                                            <div class="invoice-detail-item">
                                                <div class="invoice-detail-name">{{ __('Subtotal') }}</div>
                                                <div class="invoice-detail-value">
                                                    {{ number_format($subTotal * $order->conversion_rate, 2) }}
                                                    {{ $order->payable_currency }}
                                                </div>

                                            </div>
                                            <div class="invoice-detail-item">
                                                <div class="invoice-detail-name">{{ __('Gateway Charge') }}
                                                    ({{ number_format($gatewayCharge, 2) }}%)</div>
                                                <div class="invoice-detail-value">
                                                    {{ number_format($order->gateway_charge, 2) }}
                                                    {{ $order->payable_currency }}
                                                </div>
                                            </div>
                                            <hr class="mt-2 mb-2">
                                            <div class="invoice-detail-item">
                                                <div class="invoice-detail-name">{{ __('Total') }}</div>
                                                <div class="invoice-detail-value invoice-detail-value-lg">
                                                    {{ $total }}
                                                    {{ $order->payable_currency }}
                                                </div>
                                            </div>
                                        @else
                                            @php
                                                $subTotal = 0;
                                                $discount = 0;
                                                $gatewayCharge = 0;
                                                foreach ($order->orderItems as $item) {
                                                    $subTotal += $item->price;
                                                }
                                                $subTotalWithConversion = $subTotal * $order->conversion_rate;

                                                if ($order->coupon_discount_amount > 0) {
                                                    $discount = $order->coupon_discount_amount;
                                                }

                                                if ($order->gateway_charge > 0) {
                                                    $gatewayCharge =
                                                        ($order->gateway_charge /
                                                            ($subTotalWithConversion - $discount)) *
                                                        100;
                                                }

                                                $total = number_format(
                                                    $subTotalWithConversion - $discount + $order->gateway_charge,
                                                    2,
                                                );
                                            @endphp
                                            <div class="invoice-detail-item">
                                                <div class="invoice-detail-name">{{ __('Subtotal') }}</div>
                                                <div class="invoice-detail-value">
                                                    {{ number_format($subTotal * $order->conversion_rate, 2) }}
                                                    {{ $order->payable_currency }}
                                                </div>

                                            </div>
                                            <div class="invoice-detail-item">
                                                <div class="invoice-detail-name">{{ __('Gateway Charge') }}
                                                    ({{ number_format($gatewayCharge, 2) }}%)</div>
                                                <div class="invoice-detail-value">
                                                    {{ number_format($order->gateway_charge, 2) }}
                                                    {{ $order->payable_currency }}
                                                </div>
                                            </div>
                                            <div class="invoice-detail-item">
                                                <div class="invoice-detail-name">{{ __('Discount') }}</div>
                                                <div class="invoice-detail-value">
                                                    {{ number_format($discount, 2) }}
                                                    {{ $order->payable_currency }}
                                                </div>
                                            </div>
                                            <hr class="mt-2 mb-2">
                                            <div class="invoice-detail-item">
                                                <div class="invoice-detail-name">{{ __('Total') }}</div>
                                                <div class="invoice-detail-value invoice-detail-value-lg">
                                                    {{ $total }}
                                                    {{ $order->payable_currency }}
                                                </div>
                                            </div>
                                        @endif


                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-md-right">

                        <a target="_blank" href="{{ route('admin.print-invoice', $order->id) }}"
                            class="btn btn-warning btn-icon icon-left print-btn"><i class="fas fa-print"></i>
                            {{ __('Print') }}</a>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" tabindex="-1" role="dialog" id="delete">
        <div class="modal-dialog" role="document">
            <form action="" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Delete Order') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="text-danger">{{ __('Are You Sure to Delete this order ?') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                            data-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('Yes, Delete') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
