<?php

namespace App\Providers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class MacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Response::macro('apiSuccess', function(String $message = 'SUCCESS', $data = null, Int $code = 200){
            return Response::json([
                'status' => true,
                'data' => $data,
                'message' => $message
            ], $code);
        });
        Response::macro('apiError', function(String $message = 'Error', $data = null, Int $code = 422){
            return Response::json([
                'status' => false,
                'data' => $data,
                'message' => $message
            ], $code);
        });
    }
}
