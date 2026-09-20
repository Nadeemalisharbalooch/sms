<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  string  $subject  Email subject line.
     * @param  string  $heading  Bold heading shown at the top of the body.
     * @param  array<int, string>  $lines  Paragraphs shown in the email body.
     * @param  string|null  $actionText  Label of the call-to-action button.
     * @param  string|null  $actionUrl  URL the call-to-action button opens.
     * @param  string|null  $recipientName  Optional greeting name.
     */
    public function __construct(
        string $subject,
        public string $heading,
        public array $lines = [],
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public ?string $recipientName = null,
    ) {
        $this->subject = $subject;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    /**
     * Build the inline HTML body for the notification email.
     */
    private function buildHtml(): string
    {
        $greeting = $this->recipientName
            ? 'Hello <strong>'.e($this->recipientName).'</strong>,'
            : 'Hello,';

        $paragraphs = collect($this->lines)
            ->map(fn (string $line) => '<p style="color: #555; font-size: 15px; line-height: 1.7; margin: 0 0 16px;">'.e($line).'</p>')
            ->implode("\n");

        $action = $this->actionText && $this->actionUrl
            ? '<div style="margin: 24px 0;">
                <a href="'.e($this->actionUrl).'" style="background-color: #007bff; color: #ffffff; text-decoration: none;
                    font-size: 15px; font-weight: 600; padding: 12px 24px; border-radius: 8px; display: inline-block;">
                    '.e($this->actionText).'
                </a>
              </div>'
            : '';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background-color: #f8f9fa; border-radius: 8px; padding: 30px;">
                <h1 style="color: #333; margin: 0 0 20px; font-size: 22px;">{$this->heading}</h1>
                {$greeting}
                <div style="height: 12px;"></div>
                {$paragraphs}
                {$action}
                <p style="color: #999; font-size: 13px; margin: 16px 0 0;">
                    If you did not expect this email, you can safely ignore it.
                </p>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}