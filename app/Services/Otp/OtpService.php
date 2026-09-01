<?php

namespace App\Services\Otp;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OtpService
{
    /**
     * Generate a 6-digit OTP.
     */
    public function generateOtp(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create and store OTP for a user.
     */
    public function createOtp(User $user, string $type = 'email_verification'): EmailOtp
    {
        // Invalidate any previous unused OTPs of the same type
        $user->emailOtps()
            ->where('type', $type)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        $otp = $this->generateOtp();

        return EmailOtp::create([
            'user_id' => $user->id,
            'otp' => $otp,
            'type' => $type,
            'expires_at' => now()->addMinutes(10), // OTP valid for 10 minutes
        ]);
    }

    /**
     * Send OTP via email.
     */
    public function sendOtp(User $user, string $type = 'email_verification'): bool
    {
        $emailOtp = $this->createOtp($user, $type);

        Mail::to($user->email)->send(new OtpMail($user, $emailOtp->otp, $type));

        return true;
    }

    /**
     * Verify OTP for a user.
     */
    public function verifyOtp(User $user, string $otp, string $type = 'email_verification'): bool
    {
        $emailOtp = $user->emailOtps()
            ->where('type', $type)
            ->where('otp', $otp)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (! $emailOtp) {
            return false;
        }

        if (! $emailOtp->isValid()) {
            return false;
        }

        // Mark OTP as used
        $emailOtp->update(['is_used' => true]);

        return true;
    }
}
