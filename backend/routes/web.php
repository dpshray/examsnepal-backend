<?php

use App\Models\PromoCode;
use App\Services\ConnectIPSService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Route::get('cips', function(){});

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