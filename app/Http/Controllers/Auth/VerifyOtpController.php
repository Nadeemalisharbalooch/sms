<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\ResponseService;

class VerifyOtpController extends Controller
{
    public function __construct(
        private OtpService $otpService,
    ) {
    }

    public function __invoke(VerifyOtpRequest $request)
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        // Check if already verified
        if ($user->email_verified_at) {
            return ResponseService::error('Email is already verified.', 400);
        }

        // Verify OTP
        $isValid = $this->otpService->verifyOtp($user, $validated['otp'], 'email_verification');

        if (! $isValid) {
            return ResponseService::error('Invalid or expired OTP.', 422);
        }

        // Mark email as verified
        $user->forceFill(['email_verified_at' => now()])->save();

        return ResponseService::success(null, 'Email verified successfully. You can now login.');
    }
}
