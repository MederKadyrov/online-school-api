<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $u = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'Admin',
                'middle_name' => null,
                'password' => Hash::make('password'),
                'phone' => '+996700000000',
                'sex' => 'other',
                'pin' => '11112222333344',
                'citizenship' => 'KG',
                'address' => 'Bishkek',
            ]
        );
        if (!$u->hasRole('admin')) {
            $u->assignRole('admin');
        }
    }
}
