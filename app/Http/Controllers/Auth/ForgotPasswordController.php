<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\ResponseService;

class ForgotPasswordController extends Controller
{
    public function __construct(
        private OtpService $otpService,
    ) {
    }

    public function __invoke(ForgotPasswordRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user) {
            return ResponseService::error('No account found with this email address.', 404);
        }

        // Send password reset OTP
        $this->otpService->sendOtp($user, 'password_reset');

        return ResponseService::success(null, 'Password reset OTP has been sent to your email.');
    }
}
