<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\LoginResource;
use App\Services\ResponseService;
use Illuminate\Http\Request;
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


       /*  $statusError = $user->checkStatus();
        if ($statusError !== null) {
            return ResponseService::error($statusError, 403);
        } */

        /** @var \App\Models\User $user */
        $resource = new LoginResource($user);

        // Direct user resource pass karna hai (nested array ke andar 'user' key nahi)
        return ResponseService::success($resource, 'Login successful');
    }
}
