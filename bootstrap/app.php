<?php

use App\Http\Middleware\EnsureActiveInstituteSubscription;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active.institute.subscription' => EnsureActiveInstituteSubscription::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (Request $request, Throwable $e) {
            if ($e instanceof AuthenticationException) {
                if (! $request->expectsJson()) {
                    return redirect()->guest(route('login.page'));
                }

                if ($request->header('X-Inertia') !== 'true') {
                    return redirect()->guest(route('login.page'));
                }
            }

            return null;
        });
    })->create();
