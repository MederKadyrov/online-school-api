<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizOption extends Model
{
    protected $fillable = [
        'question_id',
        'text',
        'is_correct',
        'position',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'position'   => 'integer',
    ];

    /** Вопрос, к которому принадлежит вариант */
    public function question()
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }
}
