<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model {
    use HasFactory;
    protected $fillable = ['user_id', 'subjects'];
    public function user(){ return $this->belongsTo(User::class); }
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_subject', 'teacher_id', 'subject_id');
    }
    public function lessons(){ return $this->hasMany(Lesson::class); }

    public function groupsAsHomeroom()
    {
        return $this->hasMany(\App\Models\Group::class, 'homeroom_teacher_id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

}
