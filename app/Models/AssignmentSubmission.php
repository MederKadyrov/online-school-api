<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentSubmission extends Model
{
    protected $fillable = ['assignment_id','student_id','text_answer','file_path','submitted_at','score','grade_5','status','teacher_comment'];
    protected $casts = ['submitted_at'=>'datetime'];
    public function assignment(){ return $this->belongsTo(Assignment::class); }
    public function student(){ return $this->belongsTo(Student::class); }
}
