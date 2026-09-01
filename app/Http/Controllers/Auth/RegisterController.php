<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\RegisterResource;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\ResponseService;
use Illuminate\Database\QueryException;

class RegisterController extends Controller
{
    public function __construct(
        private OtpService $otpService,
    ) {
    }

    public function __invoke(RegisterRequest $request)
    {
        try {
            $validated = $request->validated();

            // Create user with unverified email
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            // Send OTP to user's email
            $this->otpService->sendOtp($user, 'email_verification');

            $resource = new RegisterResource($user);

            return ResponseService::success($resource, 'Registration successful. Please check your email for OTP verification.', 201);
        } catch (QueryException $e) {
            return ResponseService::error('Database error: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            return ResponseService::error('Registration failed: ' . $e->getMessage(), 500);
        }
    }
}
