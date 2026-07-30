<?php

namespace Modules\PageTemplateBuilder\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\PageTemplateBuilder\Database\factories\CustomPageFactory;

class PageTemplateBuilder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['category','image','file', 'status'];

 
    public function categoryname(){
        return $this->belongsTo(PageTemplateCategory::class,'category');
    }
}
