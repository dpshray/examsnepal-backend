<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentProfileController;

// Registration route
Route::post('/register', [AuthController::class, 'register']);

// Email verification route
Route::get('/email/verify/{id}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

Route::post('/student/register', [StudentProfileController::class, 'register']);
