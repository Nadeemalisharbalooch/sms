<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class ResendOtpController extends Controller
{
    public function __construct(
        private OtpService $otpService,
    ) {
    }

    public function __invoke(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        // Check if already verified
        if ($user->email_verified_at) {
            return ResponseService::error('Email is already verified.', 400);
        }

        // Resend OTP
        $this->otpService->sendOtp($user, 'email_verification');

        return ResponseService::success(null, 'OTP resent successfully. Please check your email.');
    }
}
