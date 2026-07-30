<?php

namespace Modules\PageTemplateBuilder\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\PageTemplateBuilder\Database\factories\CustomPageFactory;

class PageTemplateCategory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'status'];

 public function scopeActive($q){
    return $q->where('status',1);
 }
 public function templates()
{
    return $this->hasMany(PageTemplateBuilder::class,'category');
}
}
