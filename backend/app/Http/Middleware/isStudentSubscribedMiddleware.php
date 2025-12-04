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
        $subscribed = Auth::guard('api')->user()->subscribed;
        if (empty($subscribed)) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'You do not have an active subscription.'
            ], 403);
        }
        return $next($request);
    }
}
