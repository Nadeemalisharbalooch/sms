<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Models\EmailOtp;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailChangeController extends Controller
{
    public function requestOtp(Request $request, OtpService $otpService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ]);

        $otpService->sendEmailChangeOtp($request->user(), $validated['email']);

        return ResponseService::success(null, 'Email change OTP sent to the new email address.');
    }

    public function verifyOtp(Request $request, OtpService $otpService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $pendingOtp = EmailOtp::query()
            ->where('user_id', $user->id)
            ->where('type', 'email_change')
            ->where('destination_email', $validated['email'])
            ->where('otp', $validated['otp'])
            ->where('is_used', false)
            ->latest()
            ->first();

        if (! $pendingOtp || ! $otpService->verifyOtp($user, $validated['otp'], 'email_change', $validated['email'])) {
            return ResponseService::error('Invalid or expired OTP for this email address.', 422);
        }

        // Recheck uniqueness in case another account claimed the address after OTP was sent.
        if (User::query()->where('email', $validated['email'])->where('id', '!=', $user->id)->exists()) {
            return ResponseService::error('This email address is already in use.', 422);
        }

        $user->forceFill([
            'email' => $validated['email'],
            'email_verified_at' => now(),
        ])->save();

        return ResponseService::success(null, 'Email address updated successfully.');
    }
}
