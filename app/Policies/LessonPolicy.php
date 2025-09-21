<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LessonPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $u): bool {
        return $u->hasAnyRole(['admin','staff','teacher','student']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $u, Lesson $lesson): bool {
        if ($u->hasAnyRole(['admin','staff'])) return true;
        if ($u->hasRole('teacher')) return $lesson->teacher_id === ($u->teacher->id ?? 0);
        if ($u->hasRole('student')) {
            $student = $u->student;
            return $student && $lesson->group->students()->whereKey($student->id)->exists();
        }
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $u): bool {
        return $u->hasAnyRole(['admin','staff','teacher']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $u, Lesson $lesson): bool {
        if ($u->hasAnyRole(['admin','staff'])) return true;
        if ($u->hasRole('teacher')) return $lesson->teacher_id === ($u->teacher->id ?? 0);
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $u, Lesson $lesson): bool {
        return $this->update($u, $lesson);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Lesson $lesson): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Lesson $lesson): bool
    {
        return false;
    }
}
