<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Models\InstituteSubscription;
use App\Models\InstituteUser;
use App\Models\Plan;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function plans()
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get([
                'id', 'name', 'description', 'price', 'billing_interval', 'trial_days',
                'student_limit', 'teacher_limit', 'class_limit', 'features',
            ]);

        return ResponseService::success($plans, 'Available plans fetched successfully');
    }

    public function current(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if (! $instituteId) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $subscription = InstituteSubscription::query()
            ->with('plan')
            ->where('institute_id', $instituteId)
            ->latest()
            ->first();

        if (! $subscription) {
            return ResponseService::success([
                'subscription' => null,
                'can_access' => false,
                'is_expired' => false,
                'trial_ended' => false,
                'days_remaining' => 0,
            ], 'No subscription is assigned to this institute');
        }

        $wasTrial = $subscription->status === 'trial';
        $isExpired = $subscription->ends_at?->isPast() ?? false;

        if ($isExpired && in_array($subscription->status, ['trial', 'active'], true)) {
            $subscription->update(['status' => 'expired']);
            $subscription->refresh();
        }

        $daysRemaining = $subscription->ends_at
            ? max(0, (int) ceil(now()->diffInSeconds($subscription->ends_at, false) / 86400))
            : null;
        $trialEnded = ($wasTrial && $isExpired)
            || ($subscription->status === 'expired' && ! $subscription->approved_at && $isExpired);

        return ResponseService::success([
            'subscription' => $subscription,
            'can_access' => in_array($subscription->status, ['trial', 'active'], true) && ! $isExpired,
            'is_expired' => $isExpired || $subscription->status === 'expired',
            'trial_ended' => $trialEnded,
            'days_remaining' => $daysRemaining,
        ], 'Current subscription fetched successfully');
    }

    public function upgrade(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $instituteId = $this->activeInstituteId($request);

        if (! $instituteId) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $isOwner = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('institute_id', $instituteId)
            ->where('is_owner', true)
            ->exists();

        if (! $isOwner) {
            return ResponseService::error('Only the institute owner can request a plan upgrade', 403);
        }

        $plan = Plan::query()->where('is_active', true)->find($data['plan_id']);

        if (! $plan) {
            return ResponseService::error('This plan is not available', 422);
        }

        $subscription = InstituteSubscription::create([
            'institute_id' => $instituteId,
            'plan_id' => $plan->id,
            'status' => 'pending',
        ]);

        return ResponseService::success(
            $subscription->load('plan'),
            'Upgrade request submitted and is pending Super Admin approval',
            201,
        );
    }

    private function activeInstituteId(Request $request): ?int
    {
        return InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');
    }
}
