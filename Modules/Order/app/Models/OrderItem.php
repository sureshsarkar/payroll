<?php

namespace Modules\Order\app\Models;

use App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Order\Database\factories\OrderItemFactory;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id' => 'order_id',
        'price' => 'price',
        'course_id' => 'course_id',
        'batch_id' => 'batch_id',
        'commission_rate' => 'commission_rate',
        'tax_amount' => 'tax_amount',
        'tax_rate_applied' => 'tax_rate_applied',
    ];

    public function course() {
        return $this->belongsTo(Course::class, 'course_id', 'id')->withTrashed();
    }

    /**
     * 2026-06-01 (audit H9) — single source of truth for the commission rate
     * that applies to THIS line item.
     *
     * Priority:
     *   1. order_items.commission_rate captured at checkout (this is the
     *      per-coach rate — the API checkout already stores each item's
     *      coach effectiveCommissionRate here; web checkout stores the
     *      global rate, so the two agree until a coach override exists).
     *   2. fall back to the order-level rate for legacy rows that never
     *      captured a per-item value.
     *
     * Reading the SAME persisted value on both the charge and the refund
     * path is what guarantees wallet symmetry (no over/under-credit drift).
     */
    public function effectiveCommissionRate(?float $orderRate): float
    {
        $rate = $this->commission_rate !== null ? (float) $this->commission_rate : (float) ($orderRate ?? 0);
        return max(0.0, min(100.0, $rate));
    }

    /**
     * 2026-07-07 ("Coach Order amount Issue") — the amount the student ACTUALLY
     * paid for this line: the item price minus this item's proportional share of
     * any order-level coupon discount. For an order with NO coupon this equals
     * the raw price, so no-coupon behaviour is byte-identical to before — only
     * coupon orders change. Derived purely from persisted columns (price,
     * order.coupon_discount_amount) so the charge and refund paths stay
     * symmetric (same inputs → same value).
     */
    public function netPaid(): float
    {
        $price = (float) ($this->price ?? 0);
        $order = $this->order;
        $discount = $order ? (float) ($order->coupon_discount_amount ?? 0) : 0.0;
        if ($discount <= 0.0 || ! $order) {
            return round($price, 2);
        }
        // Allocate the whole-order coupon discount across items by gross price.
        $grossTotal = (float) $order->orderItems->sum(fn ($i) => (float) ($i->price ?? 0));
        if ($grossTotal <= 0.0) {
            return round($price, 2);
        }
        return round(max(0.0, $price - $discount * ($price / $grossTotal)), 2);
    }

    /**
     * Admin commission for THIS line, computed on the coupon-adjusted amount the
     * student paid (NOT the original price).
     */
    public function commissionAmount(?float $orderRate): float
    {
        $rate = $this->effectiveCommissionRate($orderRate);
        return round($this->netPaid() * $rate / 100, 2);
    }

    /**
     * 2026-06-01 (audit H9) — the coach payout for THIS line item: the amount
     * paid minus its commission. Used by every wallet credit/debit path so
     * charge and refund are always symmetric and per-item (never the whole-order
     * amount). 2026-07-07: base is now the coupon-adjusted netPaid() so the coach
     * is never credited on money the student did not pay.
     */
    public function coachPayout(?float $orderRate): float
    {
        return round($this->netPaid() - $this->commissionAmount($orderRate), 2);
    }

    function order() : HasOne{
        return $this->hasOne(Order::class, 'id', 'order_id');
    }
}
