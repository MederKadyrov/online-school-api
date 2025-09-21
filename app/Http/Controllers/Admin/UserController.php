<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Teacher;

class UserController extends Controller
{
    public function makeTeacher(User $user)
    {
        $user->assignRole('teacher');
        if (!$user->teacher) {
            $t = Teacher::create(['user_id' => $user->id]);
        }
        return response()->json([
            'user_id' => $user->id,
            'teacher_id' => $user->teacher->id,
        ], 201);
    }
}
