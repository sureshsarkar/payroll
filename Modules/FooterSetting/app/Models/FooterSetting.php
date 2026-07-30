<?php

namespace Modules\FooterSetting\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\FooterSetting\Database\factories\FooterSettingFactory;

class FooterSetting extends Model
{
    use HasFactory;

    /**
     * Explicit allowlist — was `$guarded = []` which auto-mass-assigns any
     * future column. Schema columns: id, logo, footer_text, address, phone,
     * get_in_touch_text, google_play_link, apple_store_link, timestamps.
     */
    protected $fillable = [
        'logo',
        'footer_text',
        'address',
        'phone',
        'get_in_touch_text',
        'google_play_link',
        'apple_store_link',
    ];
    
  
}
