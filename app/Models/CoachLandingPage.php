<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\PageTemplateBuilder\app\Models\PageTemplateBuilder;
use function PHPUnit\Framework\returnArgument;

class CoachLandingPage extends Model
{
    use HasFactory;


       protected $fillable = [
        'added_by',
        'template_id',
        'product_ids',
        'website_name',
        'subdomain',
        'title',
        'slug',
        'theme',
        'html_content',
        'css_content',
        'json_content',
        'full_html',
        'is_published',
        // Theme phase 1 (audit 2026-05-25)
        'theme_id',
        'theme_applied_at',
        'theme_version',
    ];

 protected $casts  = [
    'product_ids'=> 'array'
 ];
    public function sections()
    {
        return $this->hasMany(LandingSection::class, 'landing_page_id')->orderBy('sort_order');
    }

    public function coach()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function getTemplate(){
        return $this->belongsTo(PageTemplateBuilder::class,'template_id');
    }

}
