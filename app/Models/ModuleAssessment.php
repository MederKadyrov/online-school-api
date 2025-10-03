<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleAssessment extends Model
{
    public function module()      { return $this->belongsTo(Module::class); }
    public function assessable()  { return $this->morphTo(); } // quiz, assignment, etc.
}
