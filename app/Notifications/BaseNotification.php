<?php

namespace App\Notifications;

use App\Mail\NotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Short human readable title shown in the dashboard tray.
     */
    abstract public function title(): string;

    /**
     * One or two sentence summary shown in the dashboard tray.
     */
    abstract public function summary(): string;

    /**
     * Delivery priority: 'high' (email + dashboard) or 'standard' (dashboard only).
     */
    abstract public function priority(): string;

    /**
     * Grouping used by clients, e.g. 'billing', 'account', 'academics'.
     */
    abstract public function category(): string;

    public function actionText(): ?string
    {
        return null;
    }

    public function actionUrl(): ?string
    {
        return null;
    }

    /**
     * Extra machine readable payload stored with the notification.
     */
    public function payload(): array
    {
        return [];
    }

    public function via(object $notifiable): array
    {
        return $this->priority() === 'high'
            ? ['database', 'mail']
            : ['database'];
    }

    /**
     * Short snake case identifier used by clients, e.g. "payment_verified".
     */
    public function typeName(): string
    {
        return Str::snake(Str::replaceLast('Notification', '', class_basename($this)));
    }

    /**
     * Data stored in the notifications table for the dashboard tray.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->summary(),
            'priority' => $this->priority(),
            'category' => $this->category(),
            'type' => $this->typeName(),
            'action_text' => $this->actionText(),
            'action_url' => $this->actionUrl(),
            'data' => $this->payload(),
        ];
    }

    public function mailSubject(): string
    {
        return $this->title();
    }

    public function mailGreeting(): string
    {
        return $this->title();
    }

    public function mailLines(): array
    {
        return [$this->summary()];
    }

    /**
     * Build the reusable {@see NotificationMail} for this notification.
     */
    public function email(): NotificationMail
    {
        return new NotificationMail(
            subject: $this->mailSubject(),
            heading: $this->mailGreeting(),
            lines: $this->mailLines(),
            actionText: $this->actionText(),
            actionUrl: $this->actionUrl(),
        );
    }

    /**
     * Mail channel delivery. The mail message is sent inside the queued
     * notification job, so no extra queueing is needed here.
     */
    public function toMail($notifiable): NotificationMail
    {
        return $this->email()->to($notifiable->email ?? null);
    }
}