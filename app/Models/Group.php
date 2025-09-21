<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model {
    use HasFactory;
    protected $fillable = ['level_id','class_letter', 'homeroom_teacher_id'];
    protected $appends = ['display_name'];

    public function students() { return $this->hasMany(\App\Models\Student::class); }
    public function level() { return $this->belongsTo(Level::class); }

    public function getDisplayNameAttribute(): string
    {
        $num = $this->level?->number;
        $letter = $this->class_letter;
        return trim($num . ($letter ? "-$letter" : ''));
    }

    public function homeroomTeacher()
    {
        return $this->belongsTo(\App\Models\Teacher::class, 'homeroom_teacher_id');
    }

    // Для удобства можно добавить «ФИО классрука»
    public function getHomeroomTeacherNameAttribute(): ?string
    {
        $u = $this->homeroomTeacher?->user;
        if (!$u) return null;
        return trim(implode(' ', array_filter([$u->last_name, $u->first_name, $u->middle_name])));
    }

}

