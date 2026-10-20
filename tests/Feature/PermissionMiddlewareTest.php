<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\InstituteSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function createInstituteContext(string $roleName = 'Admin'): array
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'Policy Test School']);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_active' => true,
        ]);

        // The test observer auto-grants an Admin role; replace it with the requested one.
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => RolePermissionService::GUARD_NAME,
            'institute_id' => $institute->id,
        ]);
        $user->roles()
            ->where('roles.institute_id', $institute->id)
            ->get()
            ->each(fn (Role $old) => $user->removeRole($old));
        $user->assignRole($role);

        $plan = Plan::firstOrCreate(['name' => 'Trial'], [
            'price' => 0,
            'billing_interval' => 'monthly',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        InstituteSubscription::create([
            'institute_id' => $institute->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'blocked' => false,
            'starts_at' => now(),
            'ends_at' => now()->addDays(14),
        ]);

        return [$user, $institute, $role];
    }

    public function test_user_with_role_permission_can_access_route(): void
    {
        [$user, , $role] = $this->createInstituteContext();
        $role->givePermissionTo(['academic-sessions.view']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/institutes/academic-sessions');

        $response->assertOk();
    }

    public function test_user_without_role_permission_is_denied(): void
    {
        [$user, , $role] = $this->createInstituteContext('Viewer');
        $role->givePermissionTo(['students.view']); // academic-sessions.view NOT granted
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/institutes/academic-sessions');

        $response->assertForbidden();
        $response->assertJsonPath('data.reason', 'permission_denied');
        $this->assertCount(1, $response->json('data.required_permissions'));
    }

    public function test_institute_admin_role_is_repaired_and_allowed(): void
    {
        [$user] = $this->createInstituteContext();
        // Simulate an Admin role created before the permission seeder ran.
        Role::query()
            ->where('name', 'Admin')
            ->where('guard_name', RolePermissionService::GUARD_NAME)
            ->first()
            ->permissions()
            ->detach();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/institutes/academic-sessions');

        $response->assertOk();
    }

    public function test_global_super_admin_bypasses_permissions(): void
    {
        [$user] = $this->createInstituteContext();
        $user->forceFill(['is_admin' => true])->save();
        Role::query()->where('name', 'Admin')->first()->permissions()->detach();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/institutes/academic-sessions');

        $response->assertOk();
    }
}
