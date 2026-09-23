<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;

class PaymentProofSubmittedNotification extends BaseNotification
{
    public function __construct(public SubscriptionInvoice $invoice)
    {
    }

    public function title(): string
    {
        return 'New payment proof submitted';
    }

    public function summary(): string
    {
        $institute = $this->invoice->institute?->name ?? 'An institute';
        $number = $this->invoice->invoice_number ?? '-';

        return "{$institute} uploaded a payment receipt for invoice {$number}. Please verify the payment.";
    }

    public function priority(): string
    {
        return 'high';
    }

    public function category(): string
    {
        return 'billing';
    }

    public function via(object $notifiable): array
    {
        // Super admins are notified through the dashboard tray. If a dedicated
        // super admin inbox is configured, the email channel is added as well.
        return config('services.super_admin_email')
            ? ['database', 'broadcast', 'mail']
            : ['database', 'broadcast'];
    }

    public function actionText(): string
    {
        return 'Review Invoice';
    }

    public function actionUrl(): string
    {
        return url('subscription-invoices');
    }

    public function payload(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'institute_id' => $this->invoice->institute_id,
            'amount' => $this->invoice->amount,
        ];
    }
}