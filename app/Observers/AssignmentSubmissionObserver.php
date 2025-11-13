<?php

namespace App\Observers;

use App\Models\AssignmentSubmission;
use App\Models\Grade;

class AssignmentSubmissionObserver
{
    /**
     * Handle the AssignmentSubmission "updated" event.
     *
     * Примечание: Создание оценки теперь происходит напрямую в контроллере
     * через модель Grade. Observer оставлен для возможного использования в будущем.
     */
    public function updated(AssignmentSubmission $submission): void
    {
        // Оценка создаётся напрямую в TeacherAssignmentController::grade()
        // Observer больше не нужен для создания оценок
    }
}
