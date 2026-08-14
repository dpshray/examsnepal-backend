<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AuthenticateApi; // Import the custom API authentication middleware
use App\Http\Middleware\CheckUserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
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
        // $exceptions->render(function (TransportException $e, Request $request) {
        //     if ($request->expectsJson()) {
        //         return sendJsonError('Connection could not be established');
        //     }
        // });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('corporate/*') || $request->is('institute/*')) {
                return Response::apiError($e->getMessage() ?: 'Resource Not Found', null, 404);
            }
        });
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('corporate/*') || $request->is('institute/*')) {
                $flattenedErrors = collect($e->errors())->map(function ($messages) {
                    return $messages[0]; // get first error message per field
                });
                $firstMessage = collect($e->errors())->flatten()->first() ?: 'Validation error occurred';
                return Response::apiError($firstMessage, $flattenedErrors, 422);
            }
        });
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('corporate/*') || $request->is('institute/*')) {
                return Response::apiError($e->getMessage() ?: 'An error occurred', null, $e->getStatusCode());
            }
        });
    })->create();
