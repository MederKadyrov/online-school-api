<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = ['lesson_id','student_id','teacher_id','value','comment','graded_at'];

    protected $casts = ['graded_at' => 'datetime'];

    public function lesson(){ return $this->belongsTo(Lesson::class); }
    public function student(){ return $this->belongsTo(Student::class); }
    public function teacher(){ return $this->belongsTo(Teacher::class); }

}
