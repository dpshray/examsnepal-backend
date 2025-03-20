<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AuthenticateApi; // Import the custom API authentication middleware
use App\Http\Middleware\CheckUserRole; 

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register custom middleware aliases
        $middleware->alias([
            'auth.api' => AuthenticateApi::class,
            'role'     => CheckUserRole::class,
        ]);

        // Ensure API authentication middleware is properly set
        $middleware->append(AuthenticateApi::class);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle global exceptions here (e.g., logging, custom responses)
    })->create();
