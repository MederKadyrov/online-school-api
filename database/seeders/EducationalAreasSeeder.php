<?php

namespace Database\Seeders;

use App\Models\EducationalArea;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EducationalAreasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $areas = [
            ['code'=>'math',       'name'=>'Математическая'],
            ['code'=>'science',    'name'=>'Естественнонаучная'],
            ['code'=>'philology',  'name'=>'Филологическая'],
            ['code'=>'social',     'name'=>'Социальная'],
            ['code'=>'arts',       'name'=>'Искусство и технология'],
            ['code'=>'tech',       'name'=>'Технологическая'],
            ['code'=>'health',     'name'=>'Культура здоровья'],

            // добавь свои
        ];
        foreach ($areas as $a) {
            EducationalArea::firstOrCreate(['code'=>$a['code']], ['name'=>$a['name']]);
        }
    }
}
