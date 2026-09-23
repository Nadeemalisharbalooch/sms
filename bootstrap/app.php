<?php

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
            'active.institute.subscription' => \App\Http\Middleware\EnsureActiveInstituteSubscription::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Illuminate\Http\Request $request, \Throwable $e) {
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
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
