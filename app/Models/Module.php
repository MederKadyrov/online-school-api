<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['course_id','number','position','title'];

    public function course()   { return $this->belongsTo(Course::class); }
    public function chapters() { return $this->hasMany(Chapter::class)->orderBy('position'); }
    public function assessment(){ return $this->hasOne(ModuleAssessment::class); }
}
