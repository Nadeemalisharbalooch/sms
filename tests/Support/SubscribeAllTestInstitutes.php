<?php

namespace Tests\Support;

use App\Models\Institute;
use App\Models\InstituteSubscription;
use App\Models\Plan;

/**
 * Auto-subscribes every institute created during a test to the shared Trial plan.
 *
 * Newer suites create the subscription manually; older suites predate the
 * `active.institute.subscription` middleware and get 403s without it. Registering
 * this observer in Tests\TestCase covers all suites in one place. Suites that
 * create their own subscription keep working because the middleware reads the
 * latest subscription, which is then the manually created one.
 */
class SubscribeAllTestInstitutes
{
    public function created(Institute $institute): void
    {
        $plan = Plan::firstOrCreate(
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
            'blocked' => false,
            'starts_at' => now(),
            'ends_at' => now()->addDays(14),
        ]);
    }
}
