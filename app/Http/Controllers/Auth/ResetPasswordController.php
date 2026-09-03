<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\ResponseService;

class ResetPasswordController extends Controller
{
    public function __construct(
        private OtpService $otpService,
    ) {
    }

    public function __invoke(ResetPasswordRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return ResponseService::error('No account found with this email address.', 404);
        }

        // Verify OTP
        $isValid = $this->otpService->verifyOtp($user, $validated['otp'], 'password_reset');

        if (! $isValid) {
            return ResponseService::error('Invalid or expired OTP.', 422);
        }

        // Update password
        $user->update(['password' => $validated['password']]);

        return ResponseService::success(null, 'Password has been reset successfully. You can now login.');
    }
}
