<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\UserStoreRequest;
use App\Http\Requests\Institute\UserUpdateRequest;
use App\Http\Resources\Institute\UserResource;
use App\Models\InstituteUser;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Return the authenticated user's roles and permissions for the active institute.
     */
    public function currentPermissions(Request $request)
    {
        $instituteId = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $roles = $request->user()
            ->roles()
            ->where('roles.institute_id', $instituteId)
            ->with('permissions:id,name,guard_name')
            ->get(['roles.id', 'roles.name', 'roles.guard_name']);

        $permissions = $roles
            ->pluck('permissions')
            ->flatten()
            ->unique('id')
            ->values()
            ->map(fn ($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
            ]);

        return ResponseService::success([
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'roles' => $roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
            ])->values(),
            'permissions' => $permissions,
        ], 'Current user permissions retrieved successfully');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $users = User::query()
            ->with(['roles' => fn ($query) => $query->where('roles.institute_id', $instituteId)])
            ->withTrashed()
            ->where('is_admin', false)
            ->where('id', '!=', Auth::id())
            ->whereHas('instituteUsers', fn ($query) => $query->where('institute_id', $instituteId))
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
    $instituteId = $this->activeInstituteId($request);

    if ($instituteId === null) {
        return ResponseService::error('No active institute is associated with this user', 422);
    }

    try {
        $user = \DB::transaction(function () use ($userData, $validated, $instituteId) {
            $user = User::create($userData);

            InstituteUser::create([
                'institute_id' => $instituteId,
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            $roles = $this->rolesForActiveInstitute($this->extractRoleIds($validated), $instituteId);

            if ($roles->count() !== count($this->extractRoleIds($validated))) {
                throw new \Exception('Invalid role IDs provided');
            }

            if ($roles->isNotEmpty()) {
                $user->syncRoles($roles);
            }

            return $user;
        });

        return ResponseService::success(
            new UserResource($user->load(['roles' => fn ($query) => $query->where('roles.institute_id', $instituteId)])),
            'User created successfully',
            201
        );
    } catch (\Exception $e) {
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
    public function show(Request $request, string $id)
    {
        $instituteId = $this->activeInstituteId($request);
        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $user = $this->findInstituteUser($id, $instituteId, true);
        $user->load(['roles' => fn ($query) => $query->where('roles.institute_id', $instituteId)]);

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

        $instituteId = $this->activeInstituteId($request);
        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $user = $this->findInstituteUser($id, $instituteId);
        $userData = $this->userData($validated);

        if (array_key_exists('password', $userData) && blank($userData['password'])) {
            unset($userData['password']);
        }

        \DB::transaction(function () use ($user, $userData, $validated, $instituteId) {
            if (!empty($userData)) {
                $user->update($userData);
            }

            $this->syncRolesFromRequest($user, $validated, $instituteId);
        });

        return ResponseService::success(
            new UserResource($user->load(['roles' => fn ($query) => $query->where('roles.institute_id', $instituteId)])),
            'User updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        if ((int) $id === Auth::id()) {
            return ResponseService::error('You cannot delete your own account', 403);
        }

        $instituteId = $this->activeInstituteId($request);
        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $user = $this->findInstituteUser($id, $instituteId);
        $user->delete();

        return ResponseService::success(null, 'User deleted successfully');
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore(Request $request, string $id)
    {
        $instituteId = $this->activeInstituteId($request);
        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $user = $this->findInstituteUser($id, $instituteId, true, true);

        $user->restore();

        return ResponseService::success(
            new UserResource($user->load(['roles' => fn ($query) => $query->where('roles.institute_id', $instituteId)])),
            'User restored successfully'
        );
    }

    /**
     * Permanently delete a user that has already been soft deleted.
     */
    public function forceDestroy(Request $request, string $id)
    {
        $instituteId = $this->activeInstituteId($request);
        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $user = $this->findInstituteUser($id, $instituteId, true, true);

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

    private function syncRolesFromRequest(User $user, array $validated, int $instituteId): void
    {
        if (array_key_exists('role_ids', $validated)) {
            $roleIds = $this->normalizeRoleIds($validated['role_ids']);
        } elseif (array_key_exists('role', $validated)) {
            $roleIds = $this->normalizeRoleIds($validated['role']);
        } else {
            return;
        }

        $roles = $this->rolesForActiveInstitute($roleIds, $instituteId);
        if ($roles->count() !== count($roleIds)) {
            throw new \Exception('Invalid role IDs provided');
        }

        // Do not use syncRoles here: it would remove roles the user holds in
        // other institutes. Replace only roles belonging to this institute.
        $user->roles()
            ->where('roles.institute_id', $instituteId)
            ->get()
            ->each(fn (Role $role) => $user->removeRole($role));

        if ($roles->isNotEmpty()) {
            $user->assignRole($roles);
        }
    }

    private function activeInstituteId(Request $request): ?int
    {
        $instituteId = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');

        return $instituteId === null ? null : (int) $instituteId;
    }

    private function findInstituteUser(string $id, int $instituteId, bool $withTrashed = false, bool $onlyTrashed = false): User
    {
        $query = User::query()
            ->where('is_admin', false)
            ->whereHas('instituteUsers', fn ($query) => $query->where('institute_id', $instituteId));

        if ($onlyTrashed) {
            $query->onlyTrashed();
        } elseif ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($id);
    }

    private function rolesForActiveInstitute(array $roleIds, int $instituteId)
    {
        return Role::query()
            ->where('institute_id', $instituteId)
            ->whereIn('id', $roleIds)
            ->get();
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
