<?php

namespace Tests\Support;

use App\Models\InstituteUser;
use App\Services\RolePermissionService;
use Spatie\Permission\Models\Role;

/**
 * In production the institute creator is assigned a fully permissioned
 * 'Admin' role (see InstituteController and AppPermissionsSeeder). Tests
 * create InstituteUser rows directly, so without this observer every
 * institute-scoped permission check would deny access and older suites
 * would keep failing behind the new `permission` middleware.
 */
class GrantTestAdminRoles
{
    public function created(InstituteUser $instituteUser): void
    {
        $user = $instituteUser->user()->first();

        if ($user === null || $user->roles()->where('roles.institute_id', $instituteUser->institute_id)->exists()) {
            return;
        }

        $role = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => RolePermissionService::GUARD_NAME,
            'institute_id' => $instituteUser->institute_id,
        ]);

        $user->assignRole($role);

        RolePermissionService::syncAdminRole($role);
    }
}
