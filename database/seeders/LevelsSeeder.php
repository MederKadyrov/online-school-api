<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LevelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = [];
        for ($i = 1; $i <= 12; $i++) {
            $rows[] = [
                'number' => $i,
                'title' => "{$i} класс",
                'sort' => $i,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('levels')->upsert($rows, ['number']);
    }
}
