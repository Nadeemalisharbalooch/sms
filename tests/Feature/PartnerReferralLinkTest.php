<?php

namespace Tests\Feature;

use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerReferralLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_frontend_url_and_keeps_existing_response_fields(): void
    {
        config(['app.frontend_url' => 'https://edu-sync-coral.vercel.app/']);
        $user = User::factory()->create();
        PartnerProfile::create([
            'user_id' => $user->id,
            'company_name' => 'Edu Partner',
            'phone' => '1234567890',
            'promotion_methods' => ['social'],
            'tax_id_or_cnic' => 'test-id',
            'referral_code' => 'EXORA12280',
            'status' => 'active',
            'commission_rate' => 10,
            'terms_agreed_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/partners/dashboard')
            ->assertOk()
            ->assertJsonPath('data.referral_link', 'https://edu-sync-coral.vercel.app/ref/EXORA12280')
            ->assertJsonPath('data.referral_code', 'EXORA12280')
            ->assertJsonStructure(['data' => ['has_payout_details', 'metrics' => ['total_clicks', 'total_signups', 'active_subscriptions'], 'wallet' => ['pending_clearance', 'available_balance', 'total_withdrawn']]]);
    }

    public function test_tracking_rejects_invalid_referral_codes(): void
    {
        $this->getJson('/api/partners/track?ref_code=UNKNOWN')
            ->assertNotFound();
    }

    public function test_tracking_deduplicates_same_ip_on_same_day(): void
    {
        $user = User::factory()->create();
        $profile = PartnerProfile::create([
            'user_id' => $user->id,
            'company_name' => 'Edu Partner',
            'phone' => '1234567890',
            'promotion_methods' => ['social'],
            'tax_id_or_cnic' => 'test-id',
            'referral_code' => 'EXORA12280',
            'status' => 'active',
            'commission_rate' => 10,
            'terms_agreed_at' => now(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->getJson('/api/partners/track?ref_code=EXORA12280')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->getJson('/api/partners/track?ref_code=EXORA12280')->assertOk();

        $this->assertDatabaseCount('partner_clicks', 1);
    }
}
