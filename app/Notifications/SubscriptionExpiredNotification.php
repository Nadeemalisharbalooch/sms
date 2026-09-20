<?php

namespace App\Notifications;

use App\Models\InstituteSubscription;
use Illuminate\Support\Facades\URL;

class SubscriptionExpiredNotification extends BaseNotification
{
    public function __construct(public InstituteSubscription $subscription)
    {
    }

    public function title(): string
    {
        return 'Your subscription has expired';
    }

    public function summary(): string
    {
        $institute = $this->subscription->institute?->name ?? 'Your institute';

        return "{$institute}'s subscription has expired and access has been blocked. Please upgrade your plan to restore access immediately.";
    }

    public function priority(): string
    {
        return 'high';
    }

    public function category(): string
    {
        return 'billing';
    }

    public function actionText(): string
    {
        return 'Upgrade Now';
    }

    public function actionUrl(): string
    {
        return URL::route('institutes.plans.index');
    }

    public function payload(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'ends_at' => $this->subscription->ends_at?->toIso8601String(),
        ];
    }
}