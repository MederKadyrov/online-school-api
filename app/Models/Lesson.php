<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model {
    use HasFactory;
    protected $fillable = [
        'subject_id','teacher_id','group_id','starts_at','ends_at',
        'room','meeting_url','meeting_provider'
    ];
    protected $casts = ['starts_at'=>'datetime','ends_at'=>'datetime'];
    public function subject(){ return $this->belongsTo(Subject::class); }
    public function teacher(){ return $this->belongsTo(Teacher::class); }
    public function group(){ return $this->belongsTo(Group::class); }
}

