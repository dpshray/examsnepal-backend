<?php

use App\Models\PromoCode;
use App\Services\ConnectIPSService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/doc', function () {
    Artisan::call('l5-swagger:generate');
    return redirect('/api/documentation');
});

Route::get('/doc-2', function () {
    Artisan::call('l5:generate');
    return redirect('/api/documentation');
});
// Marketing email tracking (docs/marketing.md). Signed, public.
Route::controller(\App\Http\Controllers\Marketing\EmailTrackingController::class)->middleware('signed')->group(function () {
    Route::get('/e/o/{send}', 'open')->name('marketing.open');
    Route::get('/e/c/{send}', 'click')->name('marketing.click');
    Route::get('/e/u/{send}', 'unsubscribe')->name('marketing.unsubscribe');
    Route::post('/e/u/{send}', 'unsubscribePost');
    Route::post('/e/r/{send}', 'resubscribe')->name('marketing.resubscribe');
});
