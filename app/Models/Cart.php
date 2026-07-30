<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model {
    use HasFactory;
    protected $fillable = ['user_id', 'course_id', 'qty', 'guest_id','batch_id'];

    // Relationship with the Course model
    public function course() {
        return $this->belongsTo(Course::class);
    }
    public function user() {
        return $this->belongsTo(User::class,'user_id');
    }

    public function getPriceAttribute() {
        return $this->course->price;
    }
    public function getDiscountPriceAttribute() {
        return $this->course->discount;
    }
    public function getTotalPriceAttribute() {
        $discountPrice = $this->discount_price ?? $this->price;
        return $discountPrice * $this->qty;
    }
}
