<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenVersionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $payload = auth()->payload();

        if ($payload->get('token_version') != $user->token_version) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Session expired. Please login again.',
            ], 401);
        }

        return $next($request);
    }
}
