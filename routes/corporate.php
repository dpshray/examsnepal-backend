<?php

use App\Http\Controllers\Corporate\CorporateAuthController;
use App\Http\Controllers\Corporate\CorporateExamController;
use Illuminate\Support\Facades\Route;

Route::prefix('corporate')->group(function(){
    Route::post('login', [CorporateAuthController::class,'login']);
    Route::post('register', [CorporateAuthController::class,'register']);
    Route::post('logout', [CorporateAuthController::class,'logout'])->middleware('auth:users');
    Route::controller(CorporateAuthController::class)->group(function(){
        Route::post('forgot-password', 'forgotPassword');
        Route::match(['GET', 'POST'], 'password-resetor/{token}', 'paswordResetorFormHandler')->name('password.reset');
    });
    Route::middleware('auth:users')->group(function(){
        Route::apiResource('exam', CorporateExamController::class);
    });
});
