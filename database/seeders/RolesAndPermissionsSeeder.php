<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
// use Spatie\Permission\Models\Permission; // если нужны права

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Можно чистить кэш ролей, если нужно
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // При необходимости создайте permissions и свяжите с ролями
        // Permission::firstOrCreate(['name' => 'manage users']);

        foreach (['admin','teacher','student','guardian','parent','staff'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
