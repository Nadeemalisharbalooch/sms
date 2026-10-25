<?php

namespace App\Services;

use App\Models\PartnerCommission;
use App\Models\PartnerProfile;
use App\Models\SubscriptionInvoice;

class PartnerCommissionService
{
    public static function recordForPaidInvoice(SubscriptionInvoice $invoice): void
    {
        $profile = PartnerProfile::query()
            ->where('referral_code', $invoice->institute?->referral_id)
            ->where('status', 'active')
            ->first();

        if (! $profile || (float) $invoice->amount <= 0) {
            return;
        }

        PartnerCommission::query()->firstOrCreate(
            ['subscription_invoice_id' => $invoice->id],
            [
                'partner_profile_id' => $profile->id,
                'institute_id' => $invoice->institute_id,
                'amount' => round((float) $invoice->amount * (float) $profile->commission_rate / 100, 2),
                'status' => 'pending',
                'clears_at' => now()->addDays(30),
            ]
        );
    }
}
