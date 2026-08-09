<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.member' => \App\Http\Middleware\EnsureUserBelongsToCurrentTenant::class,
            'api.key' => \App\Http\Middleware\EnsureValidApiKey::class,
        ]);

        // This is an API-only app with no "login" web route. Laravel's
        // default unauthenticated-redirect calls route('login'), which
        // doesn't exist here and throws before a 401 can even render.
        // Returning null keeps Authenticate::unauthenticated() on its
        // JSON/AuthenticationException path for every request.
        Authenticate::redirectUsing(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        });

        // Laravel's default ValidationException rendering is {message, errors},
        // which doesn't match the {success, message, error} envelope every other
        // API response uses — a trap for integrators who only check `success`.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => $e->errors(),
                ], 422);
            }
        });
    })->create();
