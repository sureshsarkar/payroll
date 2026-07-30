<?php

namespace Modules\Subscription\app\Models;
 
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Subscription\app\Models\Subscription;
use Modules\Subscription\Database\factories\OrderItemFactory;

class SubscriptionHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'subscription_plan_id',
        'price',
        'description',
        'start_date',
        'end_date',
        'status',
    ];

    public function subscription() {
        return $this->belongsTo(Subscription::class, 'subscription_plan_id', 'id');
    }

    public function username() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // function order() : HasOne{
    //     return $this->hasOne(SubscriptionHistory::class, 'id', 'order_id');
    // }
}
