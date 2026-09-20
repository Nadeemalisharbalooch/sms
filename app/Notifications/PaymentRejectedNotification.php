<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\URL;

class PaymentRejectedNotification extends BaseNotification
{
    public function __construct(
        public SubscriptionInvoice $invoice,
        public ?string $reason = null,
    ) {
    }

    public function title(): string
    {
        return 'Payment proof rejected';
    }

    public function summary(): string
    {
        $number = $this->invoice->invoice_number ?? '-';
        $summary = "We could not verify the payment proof for invoice {$number}. Please upload a valid receipt to continue.";

        if ($this->reason) {
            $summary .= ' Reason: '.$this->reason.'.';
        }

        return $summary;
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
        return 'Re-upload Receipt';
    }

    public function actionUrl(): string
    {
        return URL::route('institutes.subscription.invoices.index');
    }

    public function payload(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'reason' => $this->reason,
        ];
    }
}