<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProgress extends Model
{
    protected $table = 'student_progress';

    protected $fillable = [
        'student_id',
        'paragraph_id',
        'status',
        'last_visited_at',
        'completed_at',
    ];

    protected $casts = [
        'last_visited_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function paragraph(): BelongsTo
    {
        return $this->belongsTo(Paragraph::class);
    }
}
