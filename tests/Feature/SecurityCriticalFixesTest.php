<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteSubscription;
use App\Models\InstituteUser;
use App\Models\Plan;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityCriticalFixesTest extends TestCase
{
    use RefreshDatabase;

    private function createSubscribedInstitute(string $name = 'School A'): array
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => $name]);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_owner' => true,
            'is_active' => true,
        ]);

        return [$user, $institute];
    }

    private function subscribe(Institute $institute, ?Plan $plan = null): Plan
    {
        $plan ??= Plan::firstOrCreate(
            ['name' => 'Trial'],
            [
                'price' => 0,
                'billing_interval' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
            ]
        );

        InstituteSubscription::create([
            'institute_id' => $institute->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'starts_at' => now(),
            'ends_at' => now()->addDays(14),
        ]);

        return $plan;
    }

    public function test_login_requires_verified_email(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Please verify your email address before logging in.']);
    }

    public function test_login_requires_active_account(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Your account has been deactivated. Please contact support.']);
    }

    public function test_login_succeeds_for_verified_active_user(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['token', 'type', 'id', 'email']]);
    }

    public function test_otp_is_locked_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->unverified()->create();
        $otpService = app(OtpService::class);
        $otpService->createOtp($user, 'email_verification');

        $realOtp = $user->emailOtps()->latest()->value('otp');

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($otpService->verifyOtp($user, '000000', 'email_verification'));
        }

        // Even the correct OTP must be rejected once the lockout kicks in.
        $this->assertFalse($otpService->verifyOtp($user, $realOtp, 'email_verification'));
    }

    public function test_login_rate_limited_after_five_attempts(): void
    {
        $user = User::factory()->create();

        $lastStatus = null;
        for ($i = 0; $i < 6; $i++) {
            $lastStatus = $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->status();
        }

        $this->assertSame(429, $lastStatus);
    }

    public function test_reset_password_revokes_existing_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('old-session')->plainTextToken;
        app(OtpService::class)->createOtp($user, 'password_reset');
        $otp = $user->emailOtps()->latest()->value('otp');

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'otp' => $otp,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => hash('sha256', $token)]);
    }

    public function test_member_can_view_own_institute(): void
    {
        [$user, $institute] = $this->createSubscribedInstitute();
        $this->subscribe($institute);

        Sanctum::actingAs($user);

        $this->getJson("/api/institutes/{$institute->public_id}")
            ->assertOk()
            ->assertJson(['message' => 'Institute fetched successfully']);
    }

    public function test_non_member_cannot_view_or_modify_other_institutes(): void
    {
        [, $foreignInstitute] = $this->createSubscribedInstitute('Foreign School');
        $this->subscribe($foreignInstitute);

        [$userB, $ownInstitute] = $this->createSubscribedInstitute('My School');
        $this->subscribe($ownInstitute);

        Sanctum::actingAs($userB);

        $this->getJson("/api/institutes/{$foreignInstitute->public_id}")->assertNotFound();

        $this->putJson("/api/institutes/{$foreignInstitute->public_id}", [
            'name' => 'Hacked',
            'attendance_mode' => 'class',
        ])->assertStatus(403);

        $this->deleteJson("/api/institutes/{$foreignInstitute->public_id}")->assertStatus(403);
    }

    public function test_trial_plan_can_only_be_started_once(): void
    {
        [$owner, $institute] = $this->createSubscribedInstitute();
        $plan = $this->subscribe($institute); // institute already on the trial plan

        Sanctum::actingAs($owner);

        $this->postJson('/api/institutes/subscription/upgrade', ['plan_id' => $plan->id])
            ->assertStatus(422)
            ->assertJson(['message' => 'The trial plan can only be used once per institute.']);
    }

    public function test_web_portal_pages_require_admin(): void
    {
        $regular = User::factory()->create();

        $this->actingAs($regular)->get('/dashboard')->assertStatus(403);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/dashboard')->assertSuccessful();
    }
}
