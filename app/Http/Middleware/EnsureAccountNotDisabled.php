<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAccountNotDisabled
{
    /**
     * Rejects requests from accounts an admin has disabled, so tokens issued
     * before the account was disabled stop working immediately.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('users')->user();

        if ($user && $user->is_disabled) {
            return response()->json([
                'status' => false,
                'message' => 'Your account has been disabled. Please contact ExamsNepal support.',
            ], 403);
        }

        return $next($request);
    }
}
