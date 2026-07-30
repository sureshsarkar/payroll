<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuizQuestionAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'correct',
        'question_id'
    ];
    function question(): BelongsTo {
        return $this->belongsTo(QuizQuestion::class, 'question_id', 'id');
    }
}
