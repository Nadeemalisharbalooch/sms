<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\LoginResource;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            return ResponseService::error('Invalid credentials', 401);
        }
        $user = Auth::user();

        if ($user->email_verified_at === null) {
            Auth::logout();

            return ResponseService::error('Please verify your email address before logging in.', 403);
        }

        if (! $user->is_active) {
            Auth::logout();

            return ResponseService::error('Your account has been deactivated. Please contact support.', 403);
        }

        /** @var User $user */
        $resource = new LoginResource($user);

        // Direct user resource pass karna hai (nested array ke andar 'user' key nahi)
        return ResponseService::success($resource, 'Login successful');
    }
}
