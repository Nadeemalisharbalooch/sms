<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\URL;

class InvoiceGeneratedNotification extends BaseNotification
{
    public function __construct(public SubscriptionInvoice $invoice)
    {
    }

    public function title(): string
    {
        return 'New invoice generated';
    }

    public function summary(): string
    {
        $number = $this->invoice->invoice_number ?? '-';
        $amount = $this->invoice->currency.' '.$this->invoice->amount;

        return "A new invoice {$number} of {$amount} was generated. Please complete the payment to keep your subscription active.";
    }

    public function priority(): string
    {
        return 'standard';
    }

    public function category(): string
    {
        return 'billing';
    }

    public function actionText(): string
    {
        return 'View Invoices';
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
            'amount' => $this->invoice->amount,
        ];
    }
}