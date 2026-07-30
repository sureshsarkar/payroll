<?php

namespace Modules\Coupon\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Coupon\app\Models\Coupon;
use Modules\Coupon\app\Models\CouponHistory;

class CouponController extends Controller
{
    public function index()
    {
        checkAdminHasPermissionAndThrowException('coupon.management');

        // 2026-06-02 (CRUD audit) — add search (coupon_code) + pagination.
        $search = trim((string) request('search'));
        $coupons = Coupon::where('author_id', 0)
            ->when($search !== '', fn ($q) => $q->where('coupon_code', 'like', "%{$search}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Per-coach coupons (audit M2) — the coach picker offers real coaches
        // (top-level instructor accounts). Empty selection = platform-wide.
        $coaches = \App\Models\User::where('role', 'instructor')
            ->whereNull('coach_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('coupon::index', compact('coupons', 'coaches'));
    }

    public function store(Request $request)
    {

        checkAdminHasPermissionAndThrowException('coupon.management');

        // FT-COUP-1 fix (2026-05-27) — was `required|numeric`.
        //   `numeric` alone accepts -50 and 9999. PaymentFulfilmentService
        //   applies the discount as `subtotal * (offer_percentage/100)`
        //   so a value > 100 makes the order total negative (i.e. the
        //   platform pays the buyer), and a negative value turns the
        //   coupon into a surcharge. Clamp to a sane [0, 100] integer.
        // FT-COUP-2 fix (2026-05-27) — was `required` only.
        //   No type check meant strings like "tomorrow" or past dates
        //   were accepted, then later compared against today() with
        //   string semantics. Force a real date in the future.
        $rules = [
            'coupon_code' => 'required|unique:coupons',
            'offer_percentage' => 'required|integer|min:0|max:100',
            'min_price' => 'required|numeric|min:0',
            'expired_date' => 'required|date|after:today',
            // Per-coach coupon (audit M2). Empty = platform-global (applies
            // everywhere). A coach id scopes the coupon to that coach's surface.
            'coach_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('role', 'instructor')],
            // F50 (audit 2026-06-26) — status drives the active/disabled flag; was
            // written unvalidated (arbitrary strings accepted).
            'status' => ['required', 'in:active,inactive'],
        ];
        $customMessages = [
            'coupon_code.required' => __('Coupon code is required'),
            'coupon_code.unique' => __('Coupon already exist'),
            'offer_percentage.required' => __('Offer percentage is required'),
            'offer_percentage.integer' => __('Offer percentage must be a whole number'),
            'offer_percentage.min' => __('Offer percentage cannot be negative'),
            'offer_percentage.max' => __('Offer percentage cannot exceed 100'),
            'expired_date.required' => __('Expired date is required'),
            'expired_date.date' => __('Expired date must be a valid date'),
            'expired_date.after' => __('Expired date must be in the future'),
            'min_price.required' => __('Minimum price is required'),
            'min_price.min' => __('Minimum price cannot be negative'),
            'coach_id.exists' => __('Selected coach is invalid'),
        ];

        $this->validate($request, $rules, $customMessages);

        $coupon = new Coupon();
        $coupon->author_id = 0;
        $coupon->coach_id = $request->filled('coach_id') ? (int) $request->coach_id : null;
        $coupon->coupon_code = $request->coupon_code;
        $coupon->offer_percentage = $request->offer_percentage;
        $coupon->min_price = $request->min_price;
        $coupon->expired_date = $request->expired_date;
        $coupon->status = $request->status;
        $coupon->save();

        $notification = __('Created Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);

    }

    public function update(Request $request, $id)
    {

        checkAdminHasPermissionAndThrowException('coupon.management');

        // FT-COUP-1 + FT-COUP-2 fix (2026-05-27) — same bounds applied
        // to update() as to store(); without this, an admin could
        // edit a previously-valid coupon down to offer_percentage=200
        // or backdate expired_date to 1970-01-01.
        // NOTE: `after:today` on update is intentional — an *expired*
        // coupon should not be re-saved; admins should issue a new one.
        // If business needs ever require editing past-dated rows, soften
        // to `date` only (drop `after:today`) here but keep store() strict.
        $rules = [
            'coupon_code' => 'required|unique:coupons,coupon_code,'.$id,
            'offer_percentage' => 'required|integer|min:0|max:100',
            'min_price' => 'required|numeric|min:0',
            'expired_date' => 'required|date|after:today',
            'coach_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('role', 'instructor')],
            // F50 (audit 2026-06-26) — status drives the active/disabled flag; was
            // written unvalidated (arbitrary strings accepted).
            'status' => ['required', 'in:active,inactive'],
        ];
        $customMessages = [
            'coupon_code.required' => __('Coupon code is required'),
            'coupon_code.unique' => __('Coupon already exist'),
            'offer_percentage.required' => __('Offer percentage is required'),
            'offer_percentage.integer' => __('Offer percentage must be a whole number'),
            'offer_percentage.min' => __('Offer percentage cannot be negative'),
            'offer_percentage.max' => __('Offer percentage cannot exceed 100'),
            'expired_date.required' => __('Expired date is required'),
            'expired_date.date' => __('Expired date must be a valid date'),
            'expired_date.after' => __('Expired date must be in the future'),
            'min_price.required' => __('Minimum price is required'),
            'min_price.min' => __('Minimum price cannot be negative'),
        ];

        $this->validate($request, $rules, $customMessages);

        $coupon = Coupon::findOrFail($id);
        $coupon->coach_id = $request->filled('coach_id') ? (int) $request->coach_id : null;
        $coupon->coupon_code = $request->coupon_code;
        $coupon->offer_percentage = $request->offer_percentage;
        $coupon->min_price = $request->min_price;
        $coupon->expired_date = $request->expired_date;
        $coupon->status = $request->status;
        $coupon->save();

        $notification = __('Updated Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);

    }

    public function destroy($id)
    {

        checkAdminHasPermissionAndThrowException('coupon.management');

        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        $notification = __('Deleted Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);

    }
}
