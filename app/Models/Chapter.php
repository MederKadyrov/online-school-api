<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chapter extends Model
{
    protected $fillable = ['module_id','number','position','title'];

    public function module()     { return $this->belongsTo(Module::class); }
    public function paragraphs() { return $this->hasMany(Paragraph::class)->orderBy('position'); }
}
