<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\Api\ForumController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\ExamTypeController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\QuestionController;

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
    Route::get('/student/myquestions', [ForumController::class, 'fetchMyQuestions']);
    Route::post('/student/addquestion', [ForumController::class, 'addQuestion']);
    Route::get('/student/questions/{id}', [ForumController::class, 'getQuestionById']);
    Route::put('/student/questions/edit/{id}', [ForumController::class, 'updateQuestion']);
    Route::get('/student/questions/their/{id}', [ForumController::class, 'getQuestionByTheirId']);
    Route::get('/student/questions/{subStream}', [ForumController::class, 'fetchQuestionsBySubstream']);
    Route::delete('/student/questions/{id}', [ForumController::class, 'deleteTheirQuestionCreated']);

    // for exam type
    Route::post('/exam-types', [ExamTypeController::class, 'store']);
    Route::get('/exam-types/{id}', [ExamTypeController::class, 'show']);
    Route::put('/exam-types/{id}', [ExamTypeController::class, 'update']);
    Route::delete('/exam-types/{id}', [ExamTypeController::class, 'destroy']);

    // for Organization
    Route::get('/organization', [OrganizationController::class, 'index']);
    Route::post('/organization', [OrganizationController::class, 'store']);
    Route::get('/organization/{id}', [OrganizationController::class, 'show']);
    Route::put('/organization/{id}', [OrganizationController::class, 'update']);
    Route::delete('/organization/{id}', [OrganizationController::class, 'destroy']);

    // for Exam
    Route::get('/exam', [ExamController::class, 'index']);
    Route::delete('/exam/{id}', [ExamController::class, 'destroy']);
    Route::post('/exam', [ExamController::class, 'store']);
    Route::get('/exam/{id}', [ExamController::class, 'show']);

    // for bookmarks
    Route::get('/bookmarks', [BookmarkController::class, 'index']);
    Route::post('/bookmarks', [BookmarkController::class, 'store']);
    Route::delete('/bookmarks/{id}', [BookmarkController::class, 'destroy']);
    Route::get('/bookmarks/student/{student_id}', [BookmarkController::class, 'getBookmarksByStudent']);
    Route::get('/bookmarks/allmy', [BookmarkController::class, 'getAllMyBookmarks']);

    // for Questions
    Route::post('/questions', [QuestionController::class, 'store']);
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questions/all', [QuestionController::class, 'getAllQuestion']);
    Route::get('/questions/{id}', [QuestionController::class, 'show']);
    Route::put('/questions/{id}', [QuestionController::class, 'update']);
    Route::delete('/questions/{id}', [QuestionController::class, 'destroy']);
    Route::get('/search-questions', [QuestionController::class, 'searchQuestions']);

    // for Doubts
    Route::post('/doubt', [QuestionController::class, 'store']);
    Route::get('/doubts', [QuestionController::class, 'index']);
    Route::get('/doubt/{id}', [QuestionController::class, 'show']);
    Route::put('/doubt/{id}', [QuestionController::class, 'update']);
    Route::delete('/doubt/{id}', [QuestionController::class, 'destroy']);

});

// for exam type
Route::get('/exam-types', [ExamTypeController::class, 'index']);

// Route::get('/documentation', function () {
//     return view('vendor.l5-swagger.index');
// })->withoutMiddleware('auth:api');