<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'teacher_id',
        'gradeable_type',
        'gradeable_id',
        'grade_5',
        'max_points',
        'title',
        'teacher_comment',
        'graded_at'
    ];

    protected $casts = [
        'graded_at' => 'datetime',
        'grade_5' => 'integer',
        'max_points' => 'integer'
    ];

    // Основные связи
    public function student() { return $this->belongsTo(Student::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }

    // Polymorphic связь с источником оценки
    public function gradeable() { return $this->morphTo(); }

    // Scopes для фильтрации по типу источника
    public function scopeQuizzes($query) {
        return $query->where('gradeable_type', QuizAttempt::class);
    }

    public function scopeAssignments($query) {
        return $query->where('gradeable_type', AssignmentSubmission::class);
    }

    public function scopeModules($query) {
        return $query->where('gradeable_type', Module::class);
    }

    public function scopeLessons($query) {
        return $query->where('gradeable_type', Lesson::class);
    }

    // Helper для получения всех оценок студента по курсу
    public function scopeForStudentCourse($query, $studentId, $courseId) {
        return $query->where('student_id', $studentId)
                     ->where('course_id', $courseId)
                     ->orderBy('graded_at', 'desc');
    }
}
