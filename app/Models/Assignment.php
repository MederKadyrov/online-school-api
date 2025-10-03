<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $fillable = ['paragraph_id','title','instructions','due_at','max_points','attachments_path','status'];
    protected $casts = ['due_at'=>'datetime'];
    public function paragraph(){ return $this->belongsTo(Paragraph::class); }
    public function submissions(){ return $this->hasMany(AssignmentSubmission::class); }

}
