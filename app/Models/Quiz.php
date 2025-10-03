<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $table = 'quizzes';
    protected $fillable = [
        'paragraph_id',
        'title',
        'instructions',
        'time_limit_sec',
        'max_attempts',
        'shuffle',
        'status',      // 'draft' | 'published'
        'max_points',  // суммарные баллы по вопросам
    ];

    protected $casts = [
        'shuffle'        => 'boolean',
        'time_limit_sec' => 'integer',
        'max_attempts'   => 'integer',
        'max_points'     => 'integer',
    ];

    /** Параграф, к которому привязан тест (1:1) */
    public function paragraph()
    {
        return $this->belongsTo(Paragraph::class);
    }

    /** Вопросы теста (1:N) */
    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position')->orderBy('id');
    }

    /** Попытки студентов (1:N) — если модель есть */
    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** Удобный скоуп только опубликованных */
    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }
}
