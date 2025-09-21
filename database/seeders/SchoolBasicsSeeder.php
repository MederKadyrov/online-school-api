<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SchoolBasicsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Предметы
        foreach ([
                     ['Math','MATH'], ['Geometry','GEOM'], ['Russian','RUS'],
                     ['Literature','LIT'], ['Informatics','INFO']
                 ] as [$name,$code]) {
            \App\Models\Subject::firstOrCreate(['code'=>$code], ['name'=>$name]);
        }

        // Пример группы
        \App\Models\Group::firstOrCreate(['name'=>'7А'], ['grade'=>7,'class_letter'=>'А']);

        // Пример учителя (привяжем любого пользователя-админа/сотрудника к роли teacher для теста)
        $u = \App\Models\User::where('email','admin@example.com')->first();
        if ($u && !$u->teacher) {
            $u->assignRole('teacher'); // временно для теста
            $t = \App\Models\Teacher::create(['user_id'=>$u->id]);
            $math = \App\Models\Subject::where('code','MATH')->first();
            if ($math) { $t->subjects()->syncWithoutDetaching([$math->id]); }
        }
    }
}
