<?php

namespace App\Http\Middleware;
use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApi extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, \Closure $next, ...$guards)
    {
   
        return parent::handle($request, $next, ...$guards);
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }
}