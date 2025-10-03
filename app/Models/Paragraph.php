<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paragraph extends Model
{
    protected $fillable = ['chapter_id','number','position','title','description'];

    public function chapter()   { return $this->belongsTo(Chapter::class); }
    public function resources() { return $this->hasMany(Resource::class)->orderBy('position'); }
    public function assignments(){ return $this->hasMany(Assignment::class); }
    public function quiz()
    {
        return $this->hasOne(Quiz::class);
    }
}
