<?php

namespace Modules\Subscription\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\BasicPayment\app\Services\PaymentMethodService;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Traits\GiftOrderTraits;
use Modules\Subscription\app\Models\Subscription;
use Modules\Subscription\app\Models\SubscriptionHistory;

class SubscriptionController extends Controller
{
    use GiftOrderTraits;

    public function index(Request $request)
    {
        // FT-IDOR-16 fix (2026-05-28) — was commented out.
        // The accompanying migration 2026_05_28_100000_seed_subscriptions_management_permission
        // registers the slug and grants it to Super Admin + Finance.
        checkAdminHasPermissionAndThrowException('subscriptions.management');

        $query = Subscription::query();
        $query->when($request->keyword, fn ($q) => $q->where('id', 'like', "%{$request->keyword}%"));
        $query->when($request->subscription_status, fn ($q) => $q->where('status', $request->subscription_status));
        // $query->when($request->payment_status, fn($q) => $q->where('payment_status', $request->payment_status));
        $orderBy = $request->order_by == 1 ? 'asc' : 'desc';
        $subscriptions = $request->get('par-page') == 'all' ?
        $query->orderBy('id', $orderBy)->get() :
        $query->orderBy('id', $orderBy)->paginate($request->get('par-page') ?? null)->withQueryString();

        $title = __('Subscription Plan');

        return view('subscription::index', ['subscriptions' => $subscriptions, 'title' => $title]);
    }

    public function subscription_histories(Request $request)
    {
        // FT-IDOR-16 fix (2026-05-28) — was commented out.
        // The accompanying migration 2026_05_28_100000_seed_subscriptions_management_permission
        // registers the slug and grants it to Super Admin + Finance.
        checkAdminHasPermissionAndThrowException('subscriptions.management');

        $query = SubscriptionHistory::query();
        $query->when($request->keyword, fn ($q) => $q->where('id', 'like', "%{$request->keyword}%"));
        $query->when($request->subscription_histories_status, fn ($q) => $q->where('status', $request->subscription_histories_status));
        // $query->when($request->payment_status, fn($q) => $q->where('payment_status', $request->payment_status));
        $orderBy = $request->order_by == 1 ? 'asc' : 'desc';
        $subscriptions_history = $request->get('par-page') == 'all' ?
        $query->orderBy('id', $orderBy)->get() :
        $query->orderBy('id', $orderBy)->paginate($request->get('par-page') ?? null)->withQueryString();

        $title = __('Subscription History');

        return view('subscription::subscription-histories', ['subscriptions_history' => $subscriptions_history, 'title' => $title]);
    }

    public function pending_order()
    {

        checkAdminHasPermissionAndThrowException('order.management');

        $orders = Subscription::with('user')->where('payment_status', 'pending')->latest()->paginate();
        $title = __('Pending Order');

        return view('order::pending-orders', ['orders' => $orders, 'title' => $title]);
    }


     function printInvoice(Request $request, $id) {
        // FT-IDOR-30 (Subscription sibling, 2026-05-28) — was no
        // permission gate. Renders any order's invoice = PII leak
        // (buyer's name + email + address + amount). Same shape
        // as the Order\OrderController::printInvoice fix; the
        // Subscription module's invoice page reuses the
        // order::invoice blade, so the same gate applies.
        checkAdminHasPermissionAndThrowException('order.management');
        $order = Order::where('id', $id)->firstOrFail();
        return view('order::invoice', ['order' => $order]);
    }

    public function create()
    {
        // FT-IDOR-16 fix — see index().
        checkAdminHasPermissionAndThrowException('subscriptions.management');

        return view('subscription::create', []);
    }

    public function show(string $id)
    {
        // FT-IDOR-16 fix (2026-05-28) — was commented out.
        // The accompanying migration 2026_05_28_100000_seed_subscriptions_management_permission
        // registers the slug and grants it to Super Admin + Finance.
        checkAdminHasPermissionAndThrowException('subscriptions.management');

        $subscription = Subscription::where('id', $id)->firstOrFail();

        return view('subscription::show', ['subscription' => $subscription]);
    }

    public function subscription_histories_show(string $id)
    {
        // FT-IDOR-16 fix (2026-05-28) — was commented out.
        // The accompanying migration 2026_05_28_100000_seed_subscriptions_management_permission
        // registers the slug and grants it to Super Admin + Finance.
        checkAdminHasPermissionAndThrowException('subscriptions.management');

        $subscription_histories = SubscriptionHistory::where('id', $id)->firstOrFail();

        return view('subscription::subscription-histories-show', ['subscription_histories' => $subscription_histories]);
    }

    // -------------------------------------------------------------------------------

    public function store(Request $request)
    {
        // FT-IDOR-16 fix — store() had neither a permission gate NOR
        // input validation. Without validation an admin could create
        // a "Pro Plan" priced at -1 (subscribers get money back on
        // every billing cycle) or duration_days = 999999 (effectively
        // free lifetime access for $1). Both attacks were possible
        // from any authenticated admin including read-only sub-admins
        // since the perm gate was commented out.
        checkAdminHasPermissionAndThrowException('subscriptions.management');

        $request->validate([
            'name'          => ['required', 'string', 'max:190'],
            'price'         => ['required', 'numeric', 'min:0', 'max:9999999'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:36500'],
            'status'        => ['required', 'in:active,inactive'],
            'description'   => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required'          => __('Plan name is required'),
            'price.required'         => __('Price is required'),
            'price.min'              => __('Price cannot be negative'),
            'duration_days.required' => __('Duration is required'),
            'duration_days.integer'  => __('Duration must be a whole number of days'),
            'duration_days.min'      => __('Duration must be at least 1 day'),
            'status.required'        => __('Status is required'),
        ]);

        try {

            $subscription = Subscription::create([
                'name' => $request->name,
                'price' => $request->price,
                'duration_days' => $request->duration_days,
                'status' => $request->status,
                'description' => $request->description,

            ]);

            return redirect()->route('admin.subscriptions')->with('success', 'Added Successfully');

        } catch (Exception $e) {

            DB::rollBack();

            return response()->json(['status' => false, 'messege' => __('Subscription Failed')]);
        }

    }

    public function edit($id)
    {
        // FT-IDOR-16 fix — see index().
        checkAdminHasPermissionAndThrowException('subscriptions.management');
        $subscription = Subscription::where('id', $id)->first();

        return view('subscription::edit', compact('subscription'));
    }

    public function update($id, Request $request)
    {
        // FT-IDOR-16 fix — same gate + validation rules as store().
        // Without these, an admin could edit a previously-valid plan
        // down to price = -1 or duration = 999999 even though store()
        // is now guarded.
        checkAdminHasPermissionAndThrowException('subscriptions.management');

        $request->validate([
            'name'          => ['required', 'string', 'max:190'],
            'price'         => ['required', 'numeric', 'min:0', 'max:9999999'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:36500'],
            'status'        => ['required', 'in:active,inactive'],
            'description'   => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required'          => __('Plan name is required'),
            'price.required'         => __('Price is required'),
            'price.min'              => __('Price cannot be negative'),
            'duration_days.required' => __('Duration is required'),
            'duration_days.integer'  => __('Duration must be a whole number of days'),
            'duration_days.min'      => __('Duration must be at least 1 day'),
            'status.required'        => __('Status is required'),
        ]);

        try {

            $subscription = Subscription::findOrFail($id);

            $subscription->update([
                'name' => $request->name,
                'price' => $request->price,
                'duration_days' => $request->duration_days,
                'status' => $request->status,
                'description' => $request->description,
            ]);

            return redirect()->route('admin.subscriptions')->with('success', 'Updated Successfully');

        } catch (Exception $e) {

            DB::rollBack();

            return response()->json(['status' => false, 'messege' => __('Subscription Failed')]);
        }

    }

    // -------------------------------------------------------------------------------
 
 

    public function destroy($id)
    {
        // FT-IDOR-16 fix (2026-05-28) — was commented out.
        // The accompanying migration 2026_05_28_100000_seed_subscriptions_management_permission
        // registers the slug and grants it to Super Admin + Finance.
        checkAdminHasPermissionAndThrowException('subscriptions.management');
 
        $subscription = Subscription::findOrFail($id);
        
        $subscription->delete();

        $notification = __('Subscription deleted successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.subscriptions')->with($notification);
    }
 
  
}
