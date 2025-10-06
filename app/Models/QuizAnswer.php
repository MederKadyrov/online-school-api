<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_option_ids', // для single/multiple
        'text_answer',         // для text
        'auto_score',          // автопроверка (число)
    ];

    protected $casts = [
        'selected_option_ids' => 'array',
        'auto_score'          => 'float',
    ];

    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }
}

