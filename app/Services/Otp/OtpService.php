<?php

namespace App\Services\Otp;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    /**
     * Number of failed OTP attempts allowed before the verification is locked
     * for the user, per OTP type, for the sliding window below.
     */
    private const MAX_ATTEMPTS = 5;

    private const ATTEMPT_WINDOW_SECONDS = 900; // 15 minutes

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

        // Reset the failed-attempt counter for the freshly issued OTP.
        Cache::forget($this->attemptsKey($user, $type));

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
        $attemptsKey = $this->attemptsKey($user, $type);
        $attempts = (int) Cache::get($attemptsKey, 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $emailOtp = $user->emailOtps()
            ->where('type', $type)
            ->where('otp', $otp)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (! $emailOtp || ! $emailOtp->isValid()) {
            Cache::put($attemptsKey, $attempts + 1, self::ATTEMPT_WINDOW_SECONDS);

            return false;
        }

        // Mark OTP as used
        $emailOtp->update(['is_used' => true]);

        // A successful verification clears the failed-attempt counter.
        Cache::forget($attemptsKey);

        return true;
    }

    private function attemptsKey(User $user, string $type): string
    {
        return "otp_attempts:{$user->id}:{$type}";
    }
}
