<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the default roles, permissions, and role-permission assignments.
 *
 * This is idempotent (uses INSERT … ON CONFLICT DO NOTHING / updateOrInsert)
 * and safe to run on a DB that already has data from the RolePermissionSeeder.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'create_restaurant',
        'edit_restaurant',
        'delete_restaurant',
        'manage_users',
        'manage_products',
        'view_products',
    ];

    // Permissions granted to the 'admin' role
    private const ADMIN_PERMISSIONS = [
        'create_restaurant',
        'edit_restaurant',
        'delete_restaurant',
        'manage_products',
        'view_products',
    ];

    public function up(): void
    {
        $now = now();

        // 1. Ensure roles exist
        foreach (['superadmin', 'admin'] as $roleName) {
            DB::table('roles')->updateOrInsert(
                ['name' => $roleName],
                ['is_global' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // 2. Ensure all permissions exist
        foreach (self::PERMISSIONS as $permName) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permName],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $superadminId = DB::table('roles')->where('name', 'superadmin')->value('id');
        $adminId      = DB::table('roles')->where('name', 'admin')->value('id');

        // 3. Superadmin gets ALL permissions
        $allPermIds = DB::table('permissions')->pluck('id');
        foreach ($allPermIds as $permId) {
            DB::table('role_permission')->updateOrInsert(
                ['role_id' => $superadminId, 'permission_id' => $permId]
            );
        }

        // 4. Admin gets the defined subset
        $adminPermIds = DB::table('permissions')
            ->whereIn('name', self::ADMIN_PERMISSIONS)
            ->pluck('id');

        foreach ($adminPermIds as $permId) {
            DB::table('role_permission')->updateOrInsert(
                ['role_id' => $adminId, 'permission_id' => $permId]
            );
        }
    }

    public function down(): void
    {
        // Remove the role-permission assignments added by this migration only.
        // Roles and permissions themselves are left intact to avoid FK violations
        // with existing users.
        DB::table('role_permission')->delete();
    }
};
