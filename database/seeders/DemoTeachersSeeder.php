<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class DemoTeachersSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = Teacher::factory()->count(10)->create();

        $subjectIds = Subject::query()->pluck('id')->all();
        if ($subjectIds) {
            foreach ($teachers as $t) {
                $pick = collect($subjectIds)->shuffle()->take(rand(1, 3))->values()->all();
                if ($pick) {
                    $t->subjects()->syncWithoutDetaching($pick);
                }
            }
        }
    }
}

