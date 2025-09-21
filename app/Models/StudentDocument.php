<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDocument extends Model
{
    protected $fillable = [
        'student_id',
        'guardian_application_path',
        'birth_certificate_path',
        'student_pin_doc_path',
        'guardian_passport_path',
        'medical_certificate_path',
    ];

    public function student() { return $this->belongsTo(Student::class); }
}
