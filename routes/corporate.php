<?php

use App\Http\Controllers\Corporate\CorporateAuthController;
use App\Http\Controllers\Corporate\CorporateExamController;
use Illuminate\Support\Facades\Route;

Route::prefix('corporate')->group(function(){
    Route::post('login', [CorporateAuthController::class,'login']);
    Route::middleware('auth:users')->group(function(){
        Route::apiResource('exam', CorporateExamController::class);
    });
});
