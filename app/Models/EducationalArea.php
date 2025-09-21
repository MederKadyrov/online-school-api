<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EducationalArea extends Model
{
    protected $fillable = ['name','code'];

    public function subjects() {
        return $this->hasMany(Subject::class, 'area_id');
    }
}
