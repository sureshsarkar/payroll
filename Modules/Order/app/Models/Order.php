<?php

namespace Modules\Order\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model {
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'invoice_id',
        'buyer_id',
        'seller_id',
        'status',
        'has_coupon',
        'coupon_code',
        'coupon_discount_percent',
        'coupon_discount_amount',
        'payment_method',
        // Tenant-aware gateway provenance (coach-specific payment gateways,
        // 2026-06-29) — records which gateway config processed the order so
        // webhook/callback verification re-resolves the right credentials.
        'gateway_owner_type',
        'gateway_config_id',
        'gateway_coach_id',
        'payment_verified_at',
        'webhook_verified_at',
        'payment_status',
        'payable_amount',
        // Tax snapshot (Phase 1, 2026-06-13) — captured at checkout so the
        // invoice + reports are reproducible even if the coach later edits rates.
        'tax_amount',
        'taxable_amount',
        'tax_rate_applied',
        'tax_mode',
        'tax_label',
        'tax_registration',
        'tax_components',
        'gateway_charge',
        'payable_with_charge',
        'paid_amount',
        'conversion_rate',
        'payable_currency',
        'payment_details',
        'transaction_id',
        'commission_rate',
        'order_type',
        'order_details',
        // Coach Marketing Website attribution (audit 2026-05-25)
        'source',
        'source_page_id',
        'source_section_id',
    ];

    /**
     * Auto-attribute orders created during a session that originated on a
     * coach marketing site. The CaptureSiteAttribution middleware stores
     * the source in session('site_attribution') when the visitor lands
     * via ?ref=coach_site; here we lift it onto the Order if not set
     * explicitly.
     */
    protected static function booted(): void
    {
        static::creating(function ($order) {
            if (! empty($order->source)) {
                return; // explicit value wins
            }
            $attrib = session('site_attribution');
            if (! is_array($attrib) || ($attrib['source'] ?? null) !== 'coach_site') {
                return;
            }
            // 24-hour shelf life
            if (($attrib['captured_at'] ?? 0) < (now()->timestamp - 86400)) {
                return;
            }
            $order->source = 'coach_site';
            // Resolve page_id from slug if possible
            if (! empty($attrib['source_page_slug'])) {
                $pageId = \DB::table('coach_pages')
                    ->where('slug', $attrib['source_page_slug'])
                    ->value('id');
                if ($pageId) $order->source_page_id = $pageId;
            }
            if (! empty($attrib['source_section_id'])) {
                $order->source_section_id = (int) $attrib['source_section_id'];
            }
        });
    }
    protected $casts = [
        'order_details' => 'array',
        'tax_components' => 'array', // [{name, rate, amount}, ...] snapshot for the invoice
    ];

    /**
     * 2026-05-29 — Hardened accessor.
     *
     * Original implementation was `return json_decode($value);`. That blew up
     * in two ways:
     *   1. The 'array' cast above decodes the column to an array before the
     *      accessor sees it on modern Eloquent — json_decode(array) is a
     *      TypeError under PHP 8.
     *   2. Legacy rows have order_details storing a quoted scalar JSON like
     *      `"some string"` — json_decode of that returns a PHP string,
     *      violating the `?object` return type and triggering the 500 that
     *      Playwright caught on /admin/orders.
     *
     * The fix tolerates every shape the column can hold: array, object,
     * JSON string, scalar JSON. Anything that isn't a valid JSON object
     * decodes to null (which the admin orders index handles).
     */
    public function getOrderDetailsAttribute($value): object | null {
        if (is_array($value))  return (object) $value;
        if (is_object($value)) return $value;
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value);
            if (is_object($decoded)) return $decoded;
            if (is_array($decoded))  return (object) $decoded;
        }
        return null;
    }

    public const ORDER_TYPE_BUNDLE = 'bundle';
    public function isBundleOrder(): bool {
        return $this->order_type == self::ORDER_TYPE_BUNDLE;
    }

    public const ORDER_TYPE_GIFT = 'gift';
    public function isGiftOrder(): bool {
        return $this->order_type == self::ORDER_TYPE_GIFT;
    }
    public function scopeGiftOrder($query) {
        return $query->where('order_type', self::ORDER_TYPE_GIFT);
    }

    public function user() {
        return $this->belongsTo(User::class, 'buyer_id', 'id')->select('id', 'name', 'email', 'phone', 'address', 'image');
    }

    function orderItems() {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    /* ── 2026-07-07 "Coach Order amount Issue" — canonical money breakdown. ──
       One source of truth for the four figures the coach order list / details /
       invoice must show, so they never disagree. payable_amount is the pre-tax,
       post-coupon course base (= gross items − coupon discount), which is also
       the commission base; tax/gateway are separate pass-through fields. */

    /** "Original Course Price" — gross total of all line items, before coupon. */
    public function grossItemsTotal(): float
    {
        return round((float) $this->orderItems->sum(fn ($i) => (float) ($i->price ?? 0)), 2);
    }

    /** "Coupon Discount" applied to this order. */
    public function couponDiscountAmount(): float
    {
        return round((float) ($this->coupon_discount_amount ?? 0), 2);
    }

    /** "Final Amount Paid by Student" — course amount net of coupon (pre-tax base). */
    public function finalPaidAmount(): float
    {
        $payable = $this->payable_amount !== null
            ? (float) $this->payable_amount
            : ($this->grossItemsTotal() - $this->couponDiscountAmount());
        return round(max(0.0, $payable), 2);
    }

    /** "Final Coach Earnings" over the given items (default all) — paid − commission. */
    public function coachEarnings($items = null): float
    {
        $items = $items ?? $this->orderItems;
        return round((float) collect($items)->sum(
            fn ($i) => $i->coachPayout((float) ($this->commission_rate ?? 0))
        ), 2);
    }

    /** Admin commission over the given items = amount paid − coach earnings. */
    public function adminCommission($items = null): float
    {
        $items = $items ?? $this->orderItems;
        $netPaid = round((float) collect($items)->sum(fn ($i) => $i->netPaid()), 2);
        return round(max(0.0, $netPaid - $this->coachEarnings($items)), 2);
    }
}
