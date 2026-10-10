<?php

namespace App\Http\Middleware;

use App\Services\ResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->email_verified_at === null) {
            return ResponseService::error('Please verify your email address before continuing.', 403, null, [
                'verified' => false,
                'reason' => 'email_not_verified',
            ]);
        }

        return $next($request);
    }
}
