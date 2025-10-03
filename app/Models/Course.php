<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['subject_id','teacher_id','level_id','title','slug','status','published_at'];

    public function subject()   { return $this->belongsTo(Subject::class); }
    public function teacher()   { return $this->belongsTo(Teacher::class); }
    public function level()     { return $this->belongsTo(Level::class); }
    public function modules()   { return $this->hasMany(Module::class)->orderBy('position'); }
    public function groups()    { return $this->belongsToMany(Group::class, 'course_group')->withTimestamps(); }

    protected $casts = [
        'published_at' => 'datetime',
    ];
}

