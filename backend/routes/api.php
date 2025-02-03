<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\Api\ForumController;
use Illuminate\Support\Facades\Route;

// Registration route
Route::post('/register', [AuthController::class, 'register']);

// Email verification route
Route::get('/email/verify/{id}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

// Student registration
Route::post('/student/register', [StudentProfileController::class, 'register']);

// Student login
Route::post('/student/login', [AuthController::class, 'loginStudent'])->name('login');

// Protected Routes (for authenticated students)
Route::middleware('auth:api')->group(function () {
    Route::post('/student/logout', [AuthController::class, 'logoutStudent']);
    Route::post('/student/refresh', [AuthController::class, 'refreshStudent']);
    Route::get('/student/me', [AuthController::class, 'me']);
});

Route::middleware(['auth:api'])->group(function () {
    Route::get('/student/questions', [ForumController::class, 'fetchQuestions']);
    Route::post('/student/addquestion', [ForumController::class, 'addQuestion']);
});
