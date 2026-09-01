<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public string $otp,
        public string $type,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->type) {
            'email_verification' => 'Verify Your Email Address',
            'password_reset' => 'Reset Your Password',
            default => 'Your OTP Code',
        };

        return new Envelope(
            subject: $subject,
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
     * Build the HTML content for the OTP email.
     */
    private function buildHtml(): string
    {
        $greeting = match ($this->type) {
            'email_verification' => 'Verify Your Email',
            'password_reset' => 'Reset Your Password',
            default => 'Your OTP Code',
        };

        $message = match ($this->type) {
            'email_verification' => 'Thank you for registering! Please use the following OTP to verify your email address:',
            'password_reset' => 'You have requested to reset your password. Please use the following OTP:',
            default => 'Please use the following OTP:',
        };

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background-color: #f8f9fa; border-radius: 8px; padding: 30px; text-align: center;">
                <h1 style="color: #333; margin-bottom: 20px;">{$greeting}</h1>
                <p style="color: #555; font-size: 16px; line-height: 1.6;">
                    Hello <strong>{$this->user->name}</strong>,
                </p>
                <p style="color: #555; font-size: 16px; line-height: 1.6;">
                    {$message}
                </p>
                <div style="background-color: #007bff; color: #ffffff; font-size: 32px; font-weight: bold;
                            letter-spacing: 8px; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    {$this->otp}
                </div>
                <p style="color: #999; font-size: 14px;">
                    This OTP will expire in <strong>10 minutes</strong>.
                </p>
                <p style="color: #999; font-size: 14px;">
                    If you did not request this, please ignore this email.
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
