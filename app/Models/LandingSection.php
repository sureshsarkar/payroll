<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingSection extends Model
{
    use HasFactory;


       protected $fillable = [
        'landing_page_id',
        'section_type',
        'content_json',
        'sort_order'
    ];

    protected $casts = [
        'content_json' => 'array'
    ];

    public function page()
    {
        return $this->belongsTo(CoachLandingPage::class, 'landing_page_id');
    }

}
