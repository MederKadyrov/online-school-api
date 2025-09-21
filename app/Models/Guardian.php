<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Guardian extends Model
{
    use HasFactory;

    protected $fillable = ['user_id'];

    public function user() { return $this->belongsTo(User::class); }
    public function students() { return $this->belongsToMany(Student::class); }
}
