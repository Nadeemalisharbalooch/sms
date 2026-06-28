<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\StoreRoleRequest;
use App\Http\Requests\Institute\UpdateRoleRequest;
use App\Http\Resources\Institute\RoleResource;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles=Role::all();
    return ResponseService::success(
    RoleResource::collection($roles),
    'All roles including trashed retrieved successfully'
  );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
  public function store(StoreRoleRequest $request)
{

    $validated = $request->validated();
    // Get guard_name from request or use default
    $guardName = $validated['guard_name'] ?? 'sanctum';

    // Create role with guard_name
    $role = Role::create([
        'name' => $validated['name'],
        'guard_name' => $guardName
    ]);

    // Check if permissions are provided in the request
    if (array_key_exists('permissions', $validated)) {
        // Normalize permissions similar to update method
        $permissionIds = $validated['permissions'] ?? [];

        // Normalize to proper array of integer IDs
        $permissionIds = collect($permissionIds)
            ->flatMap(function ($item) {
                if (is_string($item) && str_contains($item, ',')) {
                    return array_map('trim', explode(',', $item));
                }
                return [$item];
            })
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        // Get permission names by their IDs with proper guard
        $permissionNames = \Spatie\Permission\Models\Permission::query()
            ->whereIn('id', $permissionIds)
            ->where('guard_name', $guardName) // Important: match guard
            ->pluck('name')
            ->all();

        // Assign permissions to the new role
        $role->syncPermissions($permissionNames);
    }

    return ResponseService::success(
        new RoleResource($role->load('permissions')),
        'Role created successfully'
    );
}

    /**
     * Display the specified resource.
     */
     public function show(Role $role)
    {
        $role->load('permissions');

        return ResponseService::success(
            new RoleResource($role),
            'Role retrieved successfully'
        );
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {

        $validated = $request->validated();

        $role->update($validated);

        if (array_key_exists('permissions', $validated)) {
            // UpdateRoleRequest validates `permissions.*` as permission IDs.
            // Spatie's syncPermissions expects permission names by default.
            $permissionIds = $validated['permissions'] ?? [];

            // Frontend kabhi-kabhi comma-separated string values bhej deta hai,
            // e.g. ["1,2,3,4"] instead of [1,2,3,4].
            // Normalize karke proper array of integer IDs bana dete hain.
            $permissionIds = collect($permissionIds)
                ->flatMap(function ($item) {
                    if (is_string($item) && str_contains($item, ',')) {
                        return array_map('trim', explode(',', $item));
                    }
                    return [$item];
                })
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();

            // Use the permissions' own guard_name instead of relying on $role->guard_name,
            // because $role->guard_name can be null/incorrect if frontend didn't send it.
            $permissionNames = \Spatie\Permission\Models\Permission::query()
                ->whereIn('id', $permissionIds)
                ->pluck('name')
                ->all();

            $role->syncPermissions($permissionNames);
        }

        return ResponseService::success(
            new RoleResource($role->load('permissions')),
            'Role updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(role $role)
    {
        $role->delete();
        return ResponseService::success(
            new RoleResource($role),
            'Role Deleted successfully',
        );
    }
}
