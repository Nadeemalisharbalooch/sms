<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionService
{
    /** Ensure configured permissions exist for a guard and return their names. */
    public static function allNamesForGuard(string $guardName): array
    {
        foreach (config('app-permissions', []) as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => sprintf('%s.%s', $module, $action),
                    'guard_name' => $guardName,
                ]);
            }
        }

        return Permission::query()
            ->where('guard_name', $guardName)
            ->pluck('name')
            ->all();
    }

    public static function syncAdminRole(Role $role): void
    {
        $role->syncPermissions(self::allNamesForGuard($role->guard_name));
    }
}
