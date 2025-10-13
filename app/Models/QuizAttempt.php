<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    protected $fillable = [
        'quiz_id',
        'student_id',
        'grade_id',
        'started_at',
        'finished_at',
        'status',       // in_progress | submitted | graded
        'score',        // суммарный балл
        'autograded',   // bool
        'grade_5',      // 2..5
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at'=> 'datetime',
        'score'      => 'float',
        'autograded' => 'boolean',
        'grade_5'    => 'integer',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function answers()
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }
}

