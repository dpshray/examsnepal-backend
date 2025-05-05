<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Events\Registered;
use App\Listeners\SendEmailVerificationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register API routes manually
        Route::prefix('api')
            ->middleware('api')
            ->group(base_path('routes/api.php'));
        // Model::preventLazyLoading(true);
        if (Schema::hasTable('subjects')) {
            Cache::remember('subjects', 3600, function () {
                return DB::table('subjects')
                        ->select('id','name','code','description')
                        ->where('status',1)
                        ->get();
            });
        }        
        if (Schema::hasTable('roles')) {
            Cache::remember('roles', 3600, function () {
                return DB::table('roles')
                        ->select('id','name')
                        ->pluck('name','id');
            });
        }
    }

    public function register()
    {
       
    }
}
