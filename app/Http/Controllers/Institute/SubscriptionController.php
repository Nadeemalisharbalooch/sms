<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Models\InstituteSubscription;
use App\Models\InstituteUser;
use App\Models\Plan;
use App\Models\Student;
use App\Models\SubscriptionInvoice;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function plans(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get([
                'id', 'name', 'description', 'price', 'billing_interval', 'trial_days',
                'student_limit', 'teacher_limit', 'class_limit', 'features',
            ]);

        $totalStudents = $instituteId === null
            ? 0
            : Student::query()->where('institute_id', $instituteId)->count();

        return ResponseService::success(
            $plans->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->price,
                'billing_interval' => $plan->billing_interval,
                'trial_days' => $plan->trial_days,
                'student_limit' => $plan->student_limit,
                'teacher_limit' => $plan->teacher_limit,
                'class_limit' => $plan->class_limit,
                'features' => $plan->features,
                // This is the requesting institute's usage, included so the
                // client can compare each plan limit without another request.
                'total_students' => $totalStudents,
            ]),
            'Available plans fetched successfully'
        );
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
                'blocked' => true,
                'block_reason' => 'no_subscription',
            ], 'No subscription is assigned to this institute');
        }

        $isExpired = $subscription->ends_at?->isPast() ?? false;
        $status = $isExpired && in_array($subscription->status, ['trialing', 'active'], true)
            ? 'expired'
            : $subscription->status;

        $daysRemaining = $subscription->ends_at
            ? max(0, (int) ceil(now()->diffInSeconds($subscription->ends_at, false) / 86400))
            : null;
        $canAccess = ! $subscription->blocked && in_array($status, ['trialing', 'active'], true) && ! $isExpired;

        return ResponseService::success([
            'subscription' => [
                'id' => $subscription->id,
                'institute_id' => $subscription->institute_id,
                'status' => $status,
                'blocked' => $subscription->blocked || ! $canAccess,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'days_remaining' => $daysRemaining,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'student_limit' => $subscription->plan->student_limit,
                ] : null,
                'usage' => ['total_students' => Student::where('institute_id', $instituteId)->count()],
            ],
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

        $invoice = DB::transaction(function () use ($instituteId, $plan) {
            $invoice = SubscriptionInvoice::create([
                'institute_id' => $instituteId,
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'billing_interval' => $plan->billing_interval,
                'due_date' => now()->addDays(7)->toDateString(),
            ]);
            $invoice->update(['invoice_number' => 'SUB-'.now()->format('Ymd').'-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT)]);

            return $invoice->fresh();
        });

        return ResponseService::success(
            ['invoice' => $this->invoicePayload($invoice)],
            'Invoice generated. Please complete manual payment.',
        );
    }

    public function submitPayment(Request $request, SubscriptionInvoice $invoice)
    {
        $data = $request->validate([
            'payment_method' => ['required', 'in:bank,easypaisa,jazzcash,cash,other'],
            'payment_reference' => ['required', 'string', 'max:255'],
            'payment_screenshot' => ['required', 'image', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $instituteId = $this->activeInstituteId($request);

        if (! $instituteId || $invoice->institute_id !== $instituteId) {
            return ResponseService::notFound('Invoice not found');
        }

        $isOwner = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('institute_id', $instituteId)
            ->where('is_owner', true)
            ->exists();

        if (! $isOwner) {
            return ResponseService::error('Only the institute owner can submit payment details', 403);
        }

        if ($invoice->status !== 'open') {
            return ResponseService::error('Payment can only be submitted for an open invoice', 422);
        }

        $invoice->update([
            ...collect($data)->except('payment_screenshot')->all(),
            'payment_screenshot' => $request->hasFile('payment_screenshot')
                ? $request->file('payment_screenshot')->store('subscription-payment-proofs', 'public')
                : null,
            'status' => 'verification_pending',
            'payment_submitted_at' => now(),
        ]);

        return ResponseService::success(
            $this->invoicePayload($invoice->fresh(), true),
            'Payment details submitted and are pending Super Admin verification',
        );
    }

    public function invoices(Request $request)
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
            return ResponseService::error('Only the institute owner can view subscription invoices', 403);
        }

        $invoices = SubscriptionInvoice::query()
            ->where('institute_id', $instituteId)
            ->with([
                'plan:id,name,price,billing_interval',
                'subscription:id,institute_id,plan_id,status,starts_at,ends_at',
            ])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ResponseService::success([
            'invoices' => collect($invoices->items())->map(fn (SubscriptionInvoice $invoice) => $this->invoicePayload($invoice)),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ], 'Subscription invoices retrieved successfully');
    }

    private function activeInstituteId(Request $request): ?int
    {
        return InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');
    }

    private function invoicePayload(SubscriptionInvoice $invoice, bool $includePaymentDetails = false): array
    {
        $payload = [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => $invoice->status,
            'plan_id' => $invoice->plan_id,
            'due_date' => $invoice->due_date,
            'created_at' => $invoice->created_at,
        ];

        if ($invoice->relationLoaded('plan') && $invoice->plan) {
            $payload['plan'] = ['id' => $invoice->plan->id, 'name' => $invoice->plan->name];
        }

        if ($includePaymentDetails) {
            $payload += [
                'payment_reference' => $invoice->payment_reference,
                'payment_submitted_at' => $invoice->payment_submitted_at,
                'payment_screenshot_url' => $invoice->payment_screenshot_url,
            ];
        }

        return $payload;
    }
}
