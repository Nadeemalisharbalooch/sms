<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\URL;

class PaymentVerifiedNotification extends BaseNotification
{
    public function __construct(public SubscriptionInvoice $invoice)
    {
    }

    public function title(): string
    {
        return 'Payment verified';
    }

    public function summary(): string
    {
        $amount = $this->invoice->currency.' '.$this->invoice->amount;
        $number = $this->invoice->invoice_number ?? '-';
        $until = $this->invoice->subscription?->ends_at?->format('d M Y');

        $summary = "Your payment of {$amount} for invoice {$number} has been verified.";

        return $until
            ? $summary." Your plan is now active until {$until}."
            : $summary.' Your plan is now active.';
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
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'ends_at' => $this->invoice->subscription?->ends_at?->toIso8601String(),
        ];
    }
}