@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')
@php $minDate = date('Y-m-d', strtotime('+1 day')); @endphp

<div class="corp-page">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-ticket"></i> {{ __('Coupons') }}</h4>
            <p>{{ __('Create discount codes for your website. Each coupon applies only to your own courses at checkout.') }}</p>
        </div>
        <div class="corp-header__actions">
            <button type="button" class="btn-corp-primary" data-bs-toggle="modal" data-bs-target="#createCouponModal">
                <i class="fas fa-plus"></i> {{ __('New coupon') }}
            </button>
        </div>
    </div>

    @if ($coupons->count())
        <div class="corp-form-card">
            <div class="corp-form-card__body" style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
                    <thead>
                        <tr style="text-align:left;color:#64748b;border-bottom:1px solid #eef0f5;">
                            <th style="padding:12px 14px;font-weight:600;">{{ __('Code') }}</th>
                            <th style="padding:12px 14px;font-weight:600;">{{ __('Offer') }}</th>
                            <th style="padding:12px 14px;font-weight:600;">{{ __('Min purchase') }}</th>
                            <th style="padding:12px 14px;font-weight:600;">{{ __('Expires') }}</th>
                            <th style="padding:12px 14px;font-weight:600;">{{ __('Uses') }}</th>
                            <th style="padding:12px 14px;font-weight:600;">{{ __('Status') }}</th>
                            <th style="padding:12px 14px;font-weight:600;text-align:right;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($coupons as $c)
                            @php $expired = $c->expired_date && $c->expired_date < date('Y-m-d'); @endphp
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:12px 14px;"><span style="font-weight:700;letter-spacing:.04em;background:#ecfdf5;color:#065f46;padding:3px 10px;border-radius:6px;">{{ $c->coupon_code }}</span></td>
                                <td style="padding:12px 14px;font-weight:600;">{{ (int) $c->offer_percentage }}%</td>
                                <td style="padding:12px 14px;">{{ currency($c->min_price) }}</td>
                                <td style="padding:12px 14px;{{ $expired ? 'color:#dc2626;' : '' }}">{{ \Carbon\Carbon::parse($c->expired_date)->format('d M Y') }}</td>
                                <td style="padding:12px 14px;color:#64748b;">{{ (int) $c->usage_count }}{{ $c->usage_limit ? ' / '.(int) $c->usage_limit : '' }}</td>
                                <td style="padding:12px 14px;">
                                    @if ($c->status === 'active' && !$expired)
                                        <span style="background:#ecfdf5;color:#0f766e;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:600;">{{ __('Active') }}</span>
                                    @else
                                        <span style="background:#f1f5f9;color:#64748b;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:600;">{{ $expired ? __('Expired') : __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td style="padding:12px 14px;text-align:right;white-space:nowrap;">
                                    <button type="button" class="btn-corp-secondary btn-corp-sm" data-bs-toggle="modal" data-bs-target="#editCouponModal{{ $c->id }}" aria-label="{{ __('Edit') }}"><i class="fas fa-pen"></i></button>
                                    <form action="{{ route('instructor.coupons.destroy', $c->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('{{ __('Delete this coupon?') }}');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-corp-secondary btn-corp-sm" style="color:#dc2626;" aria-label="{{ __('Delete') }}"><i class="fas fa-trash-can"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="margin-top:14px;">{{ $coupons->links() }}</div>
            </div>
        </div>
    @else
        <div class="corp-form-card">
            <div class="corp-form-card__body" style="text-align:center;padding:54px 20px;">
                <i class="fas fa-ticket" style="font-size:42px;color:#cbd5e1;margin-bottom:14px;display:block;"></i>
                <h5 style="margin:0 0 6px;">{{ __('No coupons yet') }}</h5>
                <p style="color:#64748b;margin-bottom:18px;">{{ __('Create your first discount code — it will work only on your website.') }}</p>
                <button type="button" class="btn-corp-primary" data-bs-toggle="modal" data-bs-target="#createCouponModal"><i class="fas fa-plus"></i> {{ __('New coupon') }}</button>
            </div>
        </div>
    @endif
</div>

{{-- ── Create modal ─────────────────────────────────────────────── --}}
<div class="modal fade" id="createCouponModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:0;border-radius:16px;">
            <div class="modal-header"><h5 class="modal-title">{{ __('New coupon') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <form action="{{ route('instructor.coupons.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    @include('frontend.instructor-dashboard.coupons._fields', ['c' => null, 'minDate' => $minDate])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-corp-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-corp-primary">{{ __('Create coupon') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Edit modals (one per coupon) ─────────────────────────────── --}}
@foreach ($coupons as $c)
<div class="modal fade" id="editCouponModal{{ $c->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:0;border-radius:16px;">
            <div class="modal-header"><h5 class="modal-title">{{ __('Edit coupon') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <form action="{{ route('instructor.coupons.update', $c->id) }}" method="POST">
                @csrf @method('PUT')
                <div class="modal-body">
                    @include('frontend.instructor-dashboard.coupons._fields', ['c' => $c, 'minDate' => $minDate])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-corp-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-corp-primary">{{ __('Save changes') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
