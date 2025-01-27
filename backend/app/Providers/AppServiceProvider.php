<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Events\Registered;
use App\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register API routes manually
        Route::prefix('api')
            ->middleware('api')
            ->group(base_path('routes/api.php'));  
    }

    public function register()
    {
       
    }
}
