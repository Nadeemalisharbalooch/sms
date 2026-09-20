<?php

namespace App\Console\Commands;

use App\Models\InstituteSubscription;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\SubscriptionExpiringNotification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendSubscriptionAlerts extends Command
{
    protected $signature = 'notifications:send-subscription-alerts';

    protected $description = 'Queue subscription expiring and expired alerts for institute owners';

    public function handle(): int
    {
        $now = now();

        $subscriptions = InstituteSubscription::query()
            ->with(['plan', 'institute.owner'])
            ->whereIn('status', ['trialing', 'active'])
            ->where('blocked', false)
            ->whereNotNull('ends_at')
            ->get();

        $sent = 0;

        foreach ($subscriptions as $subscription) {
            $owner = $subscription->institute?->owner;

            if ($owner === null || $owner->email === null) {
                continue;
            }

            $daysRemaining = (int) ceil($now->diffInSeconds($subscription->ends_at, false) / 86400);

            if ($daysRemaining > 0) {
                if (in_array($daysRemaining, [5, 1], true) && $this->notNotifiedRecently($owner, SubscriptionExpiringNotification::class, $subscription)) {
                    NotificationService::toInstituteOwner(
                        $subscription->institute_id,
                        new SubscriptionExpiringNotification($subscription, $daysRemaining)
                    );
                    $sent++;
                }

                continue;
            }

            // Expired: the subscription has moved past its end date at read time.
            if ($this->notNotifiedRecently($owner, SubscriptionExpiredNotification::class, $subscription, 7)) {
                NotificationService::toInstituteOwner(
                    $subscription->institute_id,
                    new SubscriptionExpiredNotification($subscription)
                );
                $sent++;
            }
        }

        $this->info("Queued {$sent} subscription alert(s).");

        return self::SUCCESS;
    }

    /**
     * Avoid duplicate alerts of the same type for the same owner/subscription.
     */
    private function notNotifiedRecently($owner, string $type, InstituteSubscription $subscription, int $hours = 24): bool
    {
        return $owner->notifications()
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHours($hours))
            ->where('data->data->subscription_id', $subscription->id)
            ->doesntExist();
    }
}