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
         $role = Role::create($validated);
         return ResponseService::success(
            new RoleResource($role),
            'Role created successfully'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {

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

            $permissionNames = \Spatie\Permission\Models\Permission::query()
                ->where('guard_name', $role->guard_name)
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
