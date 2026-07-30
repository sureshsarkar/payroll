<?php

namespace Modules\Coupon\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Coupon\Database\factories\CouponFactory;

class Coupon extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'author_id', 'coach_id', 'coupon_code', 'offer_percentage',
        'min_price', 'expired_date', 'status',
        'usage_limit', 'usage_count', 'per_user_limit',
    ];

    /**
     * Per-coach scope (audit M2). A coupon with a NULL coach_id is a
     * platform-global coupon and applies on every surface. A coupon with a
     * coach_id is private to that coach and may ONLY be redeemed on that coach's
     * white-label surface — never on another coach's checkout.
     *
     * $tenantCoachId is the coach owning the current checkout surface (0 = the
     * bare platform). Returns true when the coupon is usable there.
     */
    public function isUsableOnCoachSurface(int $tenantCoachId): bool
    {
        $couponCoach = (int) ($this->coach_id ?? 0);

        return $couponCoach === 0 || $couponCoach === $tenantCoachId;
    }

    protected static function newFactory(): CouponFactory
    {
        //return CouponFactory::new();
    }
}
