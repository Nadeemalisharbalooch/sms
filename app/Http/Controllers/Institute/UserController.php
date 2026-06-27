<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\UserStoreRequest;
use App\Http\Requests\Institute\UserUpdateRequest;
use App\Http\Resources\Institute\UserResource;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        /*  $this->authorizePermission('users.list'); */
         $users = User::withTrashed()
            ->where('is_admin', false)
            ->where('id', '!=', Auth::id())
            ->paginate(10);

        return ResponseService::success(
            UserResource::collection($users),
            'Users retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(UserStoreRequest $request)
    {
         $validated = $request->validated();
        $user = User::create($validated);

        return ResponseService::success(
            new UserResource($user),
            'User created successfully'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserUpdateRequest $req, string $id)
    {
        $validated = $req->validated();

        $user = User::findOrFail($id);

        // Spatie role assign. If request has `role` (role name), sync it.
        if (array_key_exists('role', $validated) && !empty($validated['role'])) {
            // Remove role from attributes before updating, because `role` isn't a column.
            $roleName = $validated['role'];
            unset($validated['role']);

            // Spatie role assignment requires HasRoles trait on User model.
            // For now, call helper via app('...') is not available, so we can only proceed
            // if traits are present. Assign role by name:
            // If frontend sends a single role name: "admin"
            // If frontend sends multiple role names: ["admin","user"] or "admin,user"
            $roleNames = $roleName;
            if (is_string($roleNames) && str_contains($roleNames, ',')) {
                $roleNames = array_values(array_filter(array_map('trim', explode(',', $roleNames))));
            }

            if (is_array($roleNames)) {
                $roleNames = array_values($roleNames);
            } else {
                $roleNames = [$roleNames];
            }

            $user->syncRoles($roleNames);
        }

        // Update user fields (name/email/password/is_admin/is_active)
        if (!empty($validated)) {
            $user->update($validated);
        }

        // Eager load roles so UserResource can return them
        $user->load('roles');

        return ResponseService::success(
            new UserResource($user),
            'user updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
