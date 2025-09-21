<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Student extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','birth_date','grade','level_id', 'class_letter'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function guardians()
    {
        return $this->belongsToMany(Guardian::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function group()  { return $this->belongsTo(\App\Models\Group::class); }


}
