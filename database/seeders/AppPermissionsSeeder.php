<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
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
                    'guard_name' => 'web',
                ]);
            }
        }

        // Seed common roles if they don't exist.
        $roles = ['admin', 'user'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        // Assign all permissions to admin (optional but typical).
        // IMPORTANT: syncPermissions expects the permission names that exist for the admin role's guard.
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $permissionNames = Permission::query()
                ->where('guard_name', 'web')
                ->pluck('name')
                ->all();

            $adminRole->syncPermissions($permissionNames);
        }
    }
}

