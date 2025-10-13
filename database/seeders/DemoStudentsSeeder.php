<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\Student;
use Illuminate\Database\Seeder;

class DemoStudentsSeeder extends Seeder
{
    public function run(): void
    {
        // Убедимся, что уровни 5 и 6 существуют
        $levels = Level::whereIn('number', [5, 6])->get()->keyBy('number');

        foreach ([5, 6] as $num) {
            $level = $levels->get($num);
            if (!$level) {
                $level = Level::create([
                    'number' => $num,
                    'title'  => $num.' класс',
                    'active' => true,
                    'sort'   => $num,
                ]);
            }

            // По 25 студентов для каждого уровня (итого 50), у каждого по 2 родителя
            Student::factory()
                ->count(25)
                ->state(fn () => [
                    'level_id' => $level->id,
                ])
                ->withGuardians(2)
                ->create();
        }
    }
}

