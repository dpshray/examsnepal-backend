<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Teacher\TeacherExamController;
use App\Http\Controllers\Teacher\TeacherQuestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('teacher')->group(function(){
    Route::post('login', [AuthController::class, 'teacherLogin']);
    Route::middleware('auth:users')
        ->controller(TeacherExamController::class)
        ->group(function(){
            Route::apiResource('exam', TeacherExamController::class);
            Route::resource('exam.question', TeacherQuestionController::class)->shallow()->except(['create','edit']);
        });
});
