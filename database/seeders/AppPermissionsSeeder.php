<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Services\RolePermissionService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $config = config('app-permissions', []);

        foreach ($config as $module => $actions) {
            foreach ($actions as $action) {
                // Convention: module.action (e.g. users.list)
                $name = sprintf('%s.%s', $module, $action);

                Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => RolePermissionService::GUARD_NAME,
                ]);
            }
        }

        // Seed common roles if they don't exist.
        $roles = ['admin', 'user'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => RolePermissionService::GUARD_NAME,
            ]);
        }

        // Assign all permissions to admin (optional but typical).
        // syncPermissions expects names created for the role's guard.
        $adminRole = Role::where('name', 'admin')->where('guard_name', RolePermissionService::GUARD_NAME)->first();
        if ($adminRole) {
            $permissionNames = Permission::query()
                ->where('guard_name', RolePermissionService::GUARD_NAME)
                ->pluck('name')
                ->all();

            $adminRole->syncPermissions($permissionNames);
        }

        // Keep every institute Admin role fully permissioned, including roles created before this seeder ran.
        Role::query()
            ->where('name', 'Admin')
            ->where('guard_name', RolePermissionService::GUARD_NAME)
            ->get()
            ->each(fn (Role $role) => RolePermissionService::syncAdminRole($role));
    }
}

