<?php

namespace Modules\MercadoPagoPG\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\MercadoPagoPG\Database\factories\MercadoPagoPGFactory;

class MercadoPagoPG extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['key', 'value'];
    protected $table = 'mercadopagopg';
}
