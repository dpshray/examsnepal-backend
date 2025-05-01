<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class isStudentSubscribedMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $is_user_not_subscribed = Auth::guard('api')->user()->is_subscripted != 1;
        if ($is_user_not_subscribed) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'user has not been subscribed'
            ], 403);
        }
        return $next($request);
    }
}
