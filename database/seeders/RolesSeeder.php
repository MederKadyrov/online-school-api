<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin','teacher','student','guardian','staff'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
