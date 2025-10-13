<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ModuleGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'module_id',
        'course_id',
        'teacher_id',
        'grade_5',
        'teacher_comment',
        'graded_at'
    ];

    protected $casts = [
        'graded_at' => 'datetime',
        'grade_5' => 'integer'
    ];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
