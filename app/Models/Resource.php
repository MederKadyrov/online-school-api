<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    protected $fillable = ['paragraph_id','type','title','url','path','mime','size_bytes','external_provider','text_content','duration_sec','position'];
    public function paragraph(){ return $this->belongsTo(Paragraph::class); }

}
