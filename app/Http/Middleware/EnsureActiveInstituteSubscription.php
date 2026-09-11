<?php

namespace App\Http\Middleware;

use App\Models\Institute;
use App\Models\InstituteSubscription;
use App\Models\InstituteUser;
use App\Services\ResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveInstituteSubscription
{
    /**
     * Routes required to view plans or complete an upgrade must remain available
     * even when a subscription has ended.
     */
    private const EXEMPT_ROUTES = [
        'institutes.current',
        'institutes.plans.index',
        'institutes.subscription.current',
        'institutes.subscription.upgrade',
        'institutes.subscription.invoices.index',
        'institutes.subscription.invoices.payment',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->route()?->getName(), self::EXEMPT_ROUTES, true)) {
            return $next($request);
        }

        $membership = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if ($membership === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $institute = Institute::query()
            ->whereKey($membership->institute_id)
            ->where('is_active', true)
            ->first();

        if ($institute === null) {
            return ResponseService::error('This institute is inactive. Please contact the Super Admin.', 403);
        }

        $subscription = InstituteSubscription::query()
            ->where('institute_id', $institute->id)
            ->latest()
            ->first();

        if ($subscription === null) {
            return ResponseService::error('No active plan is assigned to this institute. Please upgrade your plan.', 403);
        }

        if ($subscription->ends_at?->isPast() && in_array($subscription->status, ['trial', 'active'], true)) {
            $subscription->update(['status' => 'expired']);
            $subscription->refresh();
        }

        if (! in_array($subscription->status, ['trial', 'active'], true)) {
            $message = $subscription->status === 'expired'
                ? 'Your trial or plan has expired. Please upgrade your plan.'
                : 'Your subscription is '.$subscription->status.'. Please contact the Super Admin.';

            return ResponseService::error($message, 403);
        }

        $request->attributes->set('active_institute', $institute);
        $request->attributes->set('active_subscription', $subscription);

        return $next($request);
    }
}
