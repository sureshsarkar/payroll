<?php

namespace Modules\LandingPageMessage\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingPageMessage extends Model
{
    use HasFactory;

    protected $table = 'landing_page_enquiries';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'coach_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'service',
        'status',
        'message',
    ];

    public function coachname()
    {
          return $this->belongsTo(User::class, 'coach_id', 'id');
    }
}
