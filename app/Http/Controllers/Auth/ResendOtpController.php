<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\ResponseService;

class ResendOtpController extends Controller
{
    public function __construct(
        private OtpService $otpService,
    ) {
    }

    public function __invoke(ResendOtpRequest $request)
    {
        $validated = $request->validated();

        // Find user by email
        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return ResponseService::error('User not found.', 404);
        }

        // Check if already verified
        if ($user->email_verified_at) {
            return ResponseService::error('Email is already verified.', 400);
        }

        // Resend OTP
        $this->otpService->sendOtp($user, 'email_verification');

        return ResponseService::success(null, 'OTP resent successfully. Please check your email.');
    }
}
