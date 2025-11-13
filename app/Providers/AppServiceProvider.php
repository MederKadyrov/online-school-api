<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Регистрация Observers
        // QuizAttemptObserver удален - создание оценок происходит напрямую в QuizController::finish()
        \App\Models\AssignmentSubmission::observe(\App\Observers\AssignmentSubmissionObserver::class);
    }
}
