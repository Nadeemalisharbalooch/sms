<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionService
{
    public const GUARD_NAME = 'sanctum';

    /** Ensure configured permissions exist for the application's roles guard. */
    public static function allNamesForGuard(): array
    {
        foreach (config('app-permissions', []) as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => sprintf('%s.%s', $module, $action),
                    'guard_name' => self::GUARD_NAME,
                ]);
            }
        }

        return Permission::query()
            ->where('guard_name', self::GUARD_NAME)
            ->pluck('name')
            ->all();
    }

    public static function syncAdminRole(Role $role): void
    {
        $role->syncPermissions(self::allNamesForGuard());
    }
}
