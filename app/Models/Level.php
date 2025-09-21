<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $fillable = ['number','title','active','sort'];

    public function groups()   { return $this->hasMany(Group::class); }
    public function students() { return $this->hasMany(Student::class); }
}
