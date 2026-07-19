<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
         $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
    ]);
    })
   ->withExceptions(function (Exceptions $exceptions) {

    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {

        if ($request->is('api/*') || $request->expectsJson()) {

            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Token is required',
                ], 401);
            }

            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

            if ($accessToken && $accessToken->expires_at && $accessToken->expires_at->isPast()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Token has expired',
                ], 401);
            }

            return response()->json([
                'status'  => false,
                'message' => 'Invalid token',
            ], 401);
        }

    });

})->create();