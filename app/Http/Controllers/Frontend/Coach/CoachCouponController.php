<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Coupon\app\Models\Coupon;

/**
 * Coach-facing coupon management (2026-06-16).
 *
 * Each coach creates + manages their OWN coupons from the coach panel. Every
 * row is stamped with coach_id = the coach, and every read/write is scoped to
 * that coach — a coach can never see or touch another coach's (or the
 * platform's) coupons. The per-coach APPLICATION is already enforced at
 * checkout by Coupon::isUsableOnCoachSurface() (a coach coupon only works on
 * that coach's white-label surface), so creating one here is all a coach needs.
 *
 * Codes are globally unique (no DB unique index exists, so we enforce it at the
 * validation layer) — this keeps the existing checkout coupon lookup
 * (`where('coupon_code', $code)->first()` + isUsableOnCoachSurface) correct
 * without touching the payment flow.
 */
class CoachCouponController extends Controller
{
    protected string $pageName = 'coach-coupons';
    protected string $admin_error_view = 'errors.403';

    /** Effective coach id — a real coach is their own id; staff act for their coach. */
    private function coachId(): int
    {
        return userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
    }

    public function index(Request $request)
    {
        if (checkPermission($this->pageName) != 1) {
            return view($this->admin_error_view);
        }

        $coachId = $this->coachId();
        $search  = trim((string) $request->get('search'));

        $coupons = Coupon::where('coach_id', $coachId)
            ->when($search !== '', fn ($q) => $q->where('coupon_code', 'like', "%{$search}%"))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('frontend.instructor-dashboard.coupons.index', compact('coupons'));
    }

    public function store(Request $request)
    {
        if (checkPermission($this->pageName) != 1) {
            return view($this->admin_error_view);
        }

        $coachId = $this->coachId();

        $request->validate($this->rules(), $this->messages());

        $coupon = new Coupon();
        $coupon->author_id       = $coachId;   // who created it (the coach)
        $coupon->coach_id        = $coachId;   // tenant scope — applies ONLY on this coach's site
        $coupon->coupon_code     = strtoupper(trim($request->coupon_code));
        $coupon->offer_percentage = (int) $request->offer_percentage;
        $coupon->min_price       = (float) $request->min_price;
        $coupon->expired_date    = $request->expired_date;
        $coupon->status          = $request->status;
        $coupon->usage_limit     = $request->filled('usage_limit') ? (int) $request->usage_limit : null;
        $coupon->per_user_limit  = $request->filled('per_user_limit') ? (int) $request->per_user_limit : null;
        $coupon->usage_count     = 0;
        $coupon->save();

        return redirect()->back()->with(['messege' => __('Coupon created successfully'), 'alert-type' => 'success']);
    }

    public function update(Request $request, $id)
    {
        if (checkPermission($this->pageName) != 1) {
            return view($this->admin_error_view);
        }

        $coachId = $this->coachId();
        // Ownership scope — a coach can only edit their OWN coupons.
        $coupon = Coupon::where('coach_id', $coachId)->findOrFail($id);

        $request->validate($this->rules($id), $this->messages());

        $coupon->coupon_code     = strtoupper(trim($request->coupon_code));
        $coupon->offer_percentage = (int) $request->offer_percentage;
        $coupon->min_price       = (float) $request->min_price;
        $coupon->expired_date    = $request->expired_date;
        $coupon->status          = $request->status;
        $coupon->usage_limit     = $request->filled('usage_limit') ? (int) $request->usage_limit : null;
        $coupon->per_user_limit  = $request->filled('per_user_limit') ? (int) $request->per_user_limit : null;
        $coupon->save();

        return redirect()->back()->with(['messege' => __('Coupon updated successfully'), 'alert-type' => 'success']);
    }

    public function destroy($id)
    {
        if (checkPermission($this->pageName) != 1) {
            return response()->json(['status' => 'error', 'message' => __('Permission denied')], 403);
        }

        $coachId = $this->coachId();
        $coupon  = Coupon::where('coach_id', $coachId)->findOrFail($id);
        $coupon->delete();

        return redirect()->back()->with(['messege' => __('Coupon deleted successfully'), 'alert-type' => 'success']);
    }

    /** @return array<string,mixed> */
    private function rules($ignoreId = null): array
    {
        return [
            // Globally-unique code keeps the checkout lookup correct; per-coach
            // isolation at redemption is handled by isUsableOnCoachSurface().
            'coupon_code'      => ['required', 'string', 'max:32', \Illuminate\Validation\Rule::unique('coupons', 'coupon_code')->ignore($ignoreId)],
            'offer_percentage' => ['required', 'integer', 'min:1', 'max:100'],
            'min_price'        => ['required', 'numeric', 'min:0'],
            'expired_date'     => ['required', 'date', 'after:today'],
            'status'           => ['required', 'in:active,inactive'],
            'usage_limit'      => ['nullable', 'integer', 'min:1'],
            'per_user_limit'   => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string,string> */
    private function messages(): array
    {
        return [
            'coupon_code.required'      => __('Coupon code is required'),
            'coupon_code.unique'        => __('This coupon code is already taken'),
            'offer_percentage.required' => __('Offer percentage is required'),
            'offer_percentage.min'      => __('Offer must be at least 1%'),
            'offer_percentage.max'      => __('Offer cannot exceed 100%'),
            'min_price.required'        => __('Minimum purchase amount is required'),
            'expired_date.required'     => __('Expiry date is required'),
            'expired_date.after'        => __('Expiry date must be in the future'),
        ];
    }
}
