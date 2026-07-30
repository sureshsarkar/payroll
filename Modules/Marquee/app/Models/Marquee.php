<?php

namespace Modules\Marquee\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Marquee\Database\factories\MarqueeFactory;

class Marquee extends Model
{
    use HasFactory;

    /**
     * Explicit allowlist — was empty fillable + `$guarded = ['id']` which is
     * effectively unguarded. Schema columns: id, name, image, type, page, status, timestamps.
     */
    protected $fillable = [
        'name',
        'image',
        'type',
        'page',
        'status',
    ];
    
    // protected static function newFactory(): MarqueeFactory
    // {
    //     //return MarqueeFactory::new();
    // }
}
