<?php

namespace App\Http\Middleware;

use App\Models\InstituteUser;
use App\Services\ResponseService;
use App\Services\RolePermissionService;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate an API route behind Spatie permissions (module.action, see
 * config/app-permissions.php). Permissions are resolved only through roles
 * that belong to the user's ACTIVE institute, so a teacher with elevated
 * roles in another institute can never gain access here.
 *
 * Usage (any of the alternatives grants access):
 *   ->middleware('permission:students.view')
 *   ->middleware('permission:students.update|students.delete')
 *   ->middleware('permission:students.view', 'students.manage')
 */
class EnsureHasPermission
{
    /** Role names that are repaired on demand so they always hold every permission. */
    private const MANAGED_ADMIN_ROLES = ['Admin'];

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ResponseService::error('Unauthenticated.', 401);
        }
        
        // Global Super Admins bypass module permissions entirely.
        if ($user->is_admin) {
            return $next($request);
        }

        // "a|b" inside one argument = alternatives; extra arguments = more alternatives.
        $required = collect($permissions)
            ->flatMap(static fn (string $chunk): array => explode('|', $chunk))
            ->map(static fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($required === []) {
            return $next($request);
        }

        $institute = $request->attributes->get('active_institute');
        $instituteId = $institute?->id ?? InstituteUser::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->value('institute_id');

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422, null, [
                'blocked' => true,
                'reason' => 'no_institute',
            ]);
        }

        // Permissions must come from roles of this institute only.
        $roles = $user->roles()
            ->where('roles.institute_id', $instituteId)
            ->with('permissions:id,name,guard_name')
            ->get(['roles.id', 'roles.name', 'roles.guard_name']);

        $owned = $roles->pluck('permissions')->flatten()->pluck('name')->unique()->values();

        $managedAdminRole = $roles->first(static fn (Role $role): bool => self::isManagedAdminRole($role->name));

        // Institute Admin roles must always hold every configured permission
        // (older institutes created before the seeder ran may lack them).
        if ($managedAdminRole !== null && $owned->intersect($required)->isEmpty()) {
            RolePermissionService::syncAdminRole($managedAdminRole);
            $owned = $managedAdminRole->permissions()->pluck('name');
        }

        if ($owned->intersect($required)->isEmpty()) {
            return ResponseService::error(
                'You do not have permission to perform this action.',
                403,
                null,
                [
                    'blocked' => true,
                    'reason' => 'permission_denied',
                    'required_permissions' => $required,
                ]
            );
        }

        return $next($request);
    }

    private static function isManagedAdminRole(string $name): bool
    {
        foreach (self::MANAGED_ADMIN_ROLES as $managed) {
            if (strcasecmp($name, $managed) === 0) {
                return true;
            }
        }

        return false;
    }
}
