<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = Role::firstOrCreate(['name' => 'superadmin'], ['is_global' => true]);
        $admin = Role::firstOrCreate(['name' => 'admin'], ['is_global' => true]);

        $permissions = [
            'create_restaurant',
            'edit_restaurant',
            'delete_restaurant',
            'manage_users',
            'manage_products',
            'view_products',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // Superadmin gets all permissions
        $superadmin->permissions()->sync(Permission::all());

        // Admin gets all permissions
        $admin->permissions()->sync(Permission::whereIn('name', [
            'create_restaurant',
            'edit_restaurant',
            'delete_restaurant',
            'manage_products',
            'view_products',
        ])->get());
    }
}
