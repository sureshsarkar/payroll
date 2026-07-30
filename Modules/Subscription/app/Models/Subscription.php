<?php

namespace Modules\Subscription\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model {
    use HasFactory;

    protected $table = 'subscription_plans';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'price',
        'duration_days',
        'status',
        'description',
    ];
    protected $casts = [
        'order_details' => 'array',
    ];
    public function getOrderDetailsAttribute($value): object | null {
        return json_decode($value);
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

    // function orderItems() {
    //     return $this->hasMany(OrderItem::class, 'order_id', 'id');
    // }
}
