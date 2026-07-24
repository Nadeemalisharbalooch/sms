<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\UserStoreRequest;
use App\Http\Requests\Institute\UserUpdateRequest;
use App\Http\Resources\Institute\UserResource;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with(['roles'])
            ->withTrashed()
            ->where('is_admin', false)
            ->where('id', '!=', Auth::id())
            ->latest()
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
    $userData = $this->userData($validated);
    $userData['is_accept_terms'] = true;

    // Use database transaction
    \DB::beginTransaction();
    try {
        $user = User::create($userData);

        // Get role IDs before creating user
        $roleIds = $this->extractRoleIds($validated);
        if (!empty($roleIds)) {
            $roleNames = Role::query()
                ->whereIn('id', $roleIds)
                ->pluck('name')
                ->all();

            if (empty($roleNames)) {
                throw new \Exception('Invalid role IDs provided');
            }

            $user->syncRoles($roleNames);
        }

        \DB::commit();

        return ResponseService::success(
            new UserResource($user->load('roles')),
            'User created successfully',
            201
        );
    } catch (\Exception $e) {
        \DB::rollBack();
        return ResponseService::error('Failed to create user: ' . $e->getMessage(), 422);
    }
}

private function extractRoleIds(array $validated): array
{
    if (array_key_exists('role_ids', $validated)) {
        return $this->normalizeRoleIds($validated['role_ids']);
    } elseif (array_key_exists('role', $validated)) {
        return $this->normalizeRoleIds($validated['role']);
    }
    return [];
}

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::withTrashed()
            ->with('roles')
            ->where('is_admin', false)
            ->findOrFail($id);

        return ResponseService::success(
            new UserResource($user),
            'User retrieved successfully'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserUpdateRequest $request, string $id)
    {
        if ((int) $id === Auth::id()) {
            return ResponseService::error('You cannot update your own account from here', 403);
        }

        $validated = $request->validated();
        $user = User::where('is_admin', false)->findOrFail($id);
        $userData = $this->userData($validated);

        if (array_key_exists('password', $userData) && blank($userData['password'])) {
            unset($userData['password']);
        }

        if (!empty($userData)) {
            $user->update($userData);
        }

        $this->syncRolesFromRequest($user, $validated);

        return ResponseService::success(
            new UserResource($user->load('roles')),
            'User updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if ((int) $id === Auth::id()) {
            return ResponseService::error('You cannot delete your own account', 403);
        }

        $user = User::where('is_admin', false)->findOrFail($id);
        $user->delete();

        return ResponseService::success(null, 'User deleted successfully');
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore(string $id)
    {
        $user = User::onlyTrashed()
            ->where('is_admin', false)
            ->findOrFail($id);

        $user->restore();

        return ResponseService::success(
            new UserResource($user->load('roles')),
            'User restored successfully'
        );
    }

    /**
     * Permanently delete a user that has already been soft deleted.
     */
    public function forceDestroy(string $id)
    {
        $user = User::onlyTrashed()
            ->where('is_admin', false)
            ->findOrFail($id);

        if ((int) $user->id === Auth::id()) {
            return ResponseService::error('You cannot permanently delete your own account', 403);
        }

        $user->forceDelete();

        return ResponseService::success(null, 'User permanently deleted successfully');
    }

    private function userData(array $validated): array
    {
        unset($validated['role_ids'], $validated['role']);

        return $validated;
    }

    private function syncRolesFromRequest(User $user, array $validated): void
    {
        if (array_key_exists('role_ids', $validated)) {
            $roleIds = $this->normalizeRoleIds($validated['role_ids']);
        } elseif (array_key_exists('role', $validated)) {
            $roleIds = $this->normalizeRoleIds($validated['role']);
        } else {
            return;
        }

        $roleNames = Role::query()
            ->whereIn('id', $roleIds)
            ->pluck('name')
            ->all();

        $user->syncRoles($roleNames);
    }

    private function normalizeRoleIds(mixed $roles): array
    {
        if ($roles === null) {
            return [];
        }

        return collect(is_array($roles) ? $roles : [$roles])
            ->flatMap(function ($item) {
                if (is_string($item) && str_contains($item, ',')) {
                    return array_map('trim', explode(',', $item));
                }

                return [$item];
            })
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }
}
