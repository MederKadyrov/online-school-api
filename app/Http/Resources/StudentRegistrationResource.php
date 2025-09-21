<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $student = $this; // App\Models\Student
        $studentUser = $student->user;
        $guardians = $student->guardians()->with('user')->get();

        $mask = fn(string $pin) => substr($pin, 0, 4).'****'.substr($pin, -4);

        return [
            'student' => [
                'id'           => $student->id,
                'user_id'      => $studentUser->id,
                'name'         => $studentUser->name,
                'email'        => $studentUser->email,
                'phone'        => $studentUser->phone,
                'sex'          => $studentUser->sex,
                'pin_masked'   => $mask($studentUser->pin),
                'citizenship'  => $studentUser->citizenship,
                'birth_date'   => $student->birth_date,
                'grade'        => $student->grade,
                'class_letter' => $student->class_letter,
            ],
            'guardians' => $guardians->map(function ($g) use ($mask) {
                return [
                    'id'          => $g->id,
                    'user_id'     => $g->user->id,
                    'name'        => $g->user->name,
                    'email'       => $g->user->email,
                    'phone'       => $g->user->phone,
                    'sex'         => $g->user->sex,
                    'pin_masked'  => $mask($g->user->pin),
                    'citizenship' => $g->user->citizenship,
                    'address'     => $g->user->address,
                ];
            }),
        ];
    }
}

