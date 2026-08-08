<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Teacher\TeacherDoubtController;
use App\Http\Controllers\Teacher\TeacherEarningsController;
use App\Http\Controllers\Teacher\TeacherExamController;
use App\Http\Controllers\Teacher\TeacherExamSubmissionController;
use App\Http\Controllers\Teacher\TeacherQuestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('teacher')->group(function(){
    Route::post('login', [AuthController::class, 'teacherLogin']);
    Route::middleware('auth:users')
        ->controller(TeacherExamController::class)
        ->group(function(){
            Route::apiResource('exam', TeacherExamController::class)->names('teacher.exam');
            Route::resource('exam.question', TeacherQuestionController::class)->shallow()->except(['create','edit']);
            Route::get('question/{question}', [TeacherQuestionController::class, 'show']);
            Route::post('exam/{exam}/questions/bulk-import', [TeacherQuestionController::class, 'bulkImport']);
            Route::get('exam/{exam}/submissions/export', [TeacherExamSubmissionController::class, 'export']);
            Route::get('exam/{exam}/submissions', [TeacherExamSubmissionController::class, 'index']);
            Route::get('exam/{exam}/submissions/{studentExam}', [TeacherExamSubmissionController::class, 'show']);
            Route::get('doubts', [TeacherDoubtController::class, 'index']);
            Route::post('doubts/{doubt}/resolve', [TeacherDoubtController::class, 'resolve']);
            Route::get('earnings/current-estimate', [TeacherEarningsController::class, 'currentEstimate']);
            Route::get('earnings', [TeacherEarningsController::class, 'index']);
            Route::get('earnings/{payout}', [TeacherEarningsController::class, 'show']);
        });
});
