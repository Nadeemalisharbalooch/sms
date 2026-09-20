<?php

namespace App\Notifications;

use App\Models\InstituteSubscription;
use Illuminate\Support\Facades\URL;

class SubscriptionExpiringNotification extends BaseNotification
{
    public function __construct(
        public InstituteSubscription $subscription,
        public int $daysRemaining,
    ) {
    }

    public function title(): string
    {
        return $this->daysRemaining === 1
            ? 'Your subscription expires today'
            : 'Your subscription is expiring soon';
    }

    public function summary(): string
    {
        $plan = $this->subscription->plan?->name ?? 'current plan';
        $date = $this->subscription->ends_at?->format('d M Y');
        $dayLabel = $this->daysRemaining === 1 ? '1 day' : $this->daysRemaining.' days';

        return "Your {$plan} subscription will expire on {$date} ({$dayLabel} left). Please renew your plan to avoid any interruption.";
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
        return 'View Subscription';
    }

    public function actionUrl(): string
    {
        return URL::route('institutes.subscription.current');
    }

    public function payload(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'ends_at' => $this->subscription->ends_at?->toIso8601String(),
            'days_remaining' => $this->daysRemaining,
        ];
    }
}