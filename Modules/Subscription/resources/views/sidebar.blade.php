@php
    // 2026-07-20 — was an uncached ->count() run inline on EVERY admin page,
    // and Subscription's partial ran the identical query again. One cached
    // lookup now serves every sidebar badge.
    $pendingOrderCount = adminMenuCounts()['pending_orders'] ?? 0;
@endphp
@use('Nwidart\Modules\Facades\Module')
<li
    class="nav-item dropdown {{ isRoute(['admin.subscriptions', 'admin.subscriptions.*', 'admin.order', 'admin.pending-payment', 'admin.rejected-payment', 'admin.pending-subscriptions'], 'active') }}">
    <a href="#" class="nav-link has-dropdown" data-toggle="dropdown"><i class="fas fa-shopping-bag"></i>
        <span class="{{ $pendingOrderCount > 0 ? 'beep parent' : '' }}">{{ __('Manage Subscriptions') }} </span>
    </a>
    <ul class="dropdown-menu">
        <li class="{{ isRoute('admin.subscriptions', 'active') }} {{ isRoute('admin.subscriptions', 'active') }}"><a class="nav-link"
                href="{{ route('admin.subscriptions') }}">{{ __('Subscriptions') }}</a></li>
                
        {{-- @if (Module::has('GiftCourse') && Module::isEnabled('GiftCourse'))
            @php
                $pendingGiftOrderCount = \Modules\Order\app\Models\Order::giftOrder()
                    ->where('payment_status', 'paid')
                    ->whereNotNull('order_details->verification_token')
                    ->count();
            @endphp
            <li class="{{ isRoute('admin.subscriptions.gift', 'active') }}"><a class="nav-link"
                    href="{{ route('admin.subscriptions.gift') }}">{{ __('Gift Subscriptions') }}
                    @if ($pendingGiftOrderCount > 0)
                        <small class="badge badge-danger ml-2">{{ $pendingGiftOrderCount }}</small>
                    @endif
                </a>
            </li>
        @endif --}}

        <li class="{{ isRoute('admin.subscription-histories', 'active') }}"><a class="nav-link"
                href="{{ route('admin.subscription-histories') }}">{{ __('Subscription History') }}
                {{-- @if ($pendingOrderCount > 0)
                    <small class="badge badge-danger ml-2">{{ $pendingOrderCount }}</small>
                @endif --}}
            </a>
        </li>

    </ul>
</li>
