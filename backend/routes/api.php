<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\Api\ForumController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\ExamTypeController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\BankQuestionController;
use App\Http\Controllers\DoubtController;




use Illuminate\Support\Facades\Route;

// Registration route
Route::post('/register', [AuthController::class, 'register']);

// Email verification route
Route::get('/email/verify/{id}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

// Student registration
Route::post('/student/register', [StudentProfileController::class, 'register']);

// Student login
Route::post('/student/login', [AuthController::class, 'loginStudent'])->name('login');
Route::post('/admin/login', [AuthController::class, 'AdminLogin'])->name('loginAdmin');
Route::post('/teacher/login', [AuthController::class, 'teacherLogin'])->name('loginTeacher');



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

    Route::post('/student/answers', [ForumController::class, 'addAnswer']);


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
    // Route::get('/exam', [ExamController::class, 'index']);
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
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questions/{id}', [QuestionController::class, 'show']);
    Route::get('/search-questions', [QuestionController::class, 'searchQuestions']);

    // for Doubts
    Route::post('/doubt', [QuestionController::class, 'store']);
    Route::get('/doubt/{id}', [QuestionController::class, 'show']);
    Route::put('/doubt/{id}', [QuestionController::class, 'update']);
    Route::delete('/doubt/{id}', [QuestionController::class, 'destroy']);

    

    Route::get('/free-quiz', [QuizController::class, 'getFreeQuiz']);
    Route::get('/sprint-quiz', [QuizController::class, 'getSprintQuiz']);
    Route::get('/mock-test', [QuizController::class, 'getMockTest']);

    Route::get('/free-quiz/questions/{exam_id}', [QuestionController::class, 'freeQuizQuestions']);
    Route::get('/sprint-quiz/questions/{exam_id}', [QuestionController::class, 'sprintQuizQuestions']);
    Route::get('/mock-test/questions/{exam_id}', [QuestionController::class, 'mockTestQuestions']);






});

// for exam type
Route::get('/exam-types', [ExamTypeController::class, 'index']);

// Route::get('/documentation', function () {
//     return view('vendor.l5-swagger.index');
// })->withoutMiddleware('auth:api');

Route::middleware(['auth:users', 'role:admin'])->group(function () {

    // for subjects
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::post('/subject', [SubjectController::class, 'store']);
    Route::get('/subject/{id}', [SubjectController::class, 'show']);
    Route::put('/subject/{id}', [SubjectController::class, 'update']);
    Route::delete('/subject/{id}', [SubjectController::class, 'destroy']);

    Route::get('/all-students', [StudentProfileController::class, 'allStudents']);

    Route::get('/teachers', [TeacherController::class, 'index']);
    Route::get('/all-question-banks', [BankQuestionController::class, 'index']);
    Route::post('/question-bank',[BankQuestionController::class,'store']);
    Route::get('/question-bank/{id}', [BankQuestionController::class, 'show']);
    Route::put('/question-bank/{id}',[BankQuestionController::class,'update']);
    Route::delete('/question-bank/{id}',[BankQuestionController::class,'destroy']);


    Route::post('/create-quiz',[QuizController::class,'examAsQuizStore']);
    Route::get('/quiz/{id}',[QuizController::class,'show']);
    Route::put('/update-quiz',[QuizController::class,'updateExamAsQuiz']);
    Route::delete('/quiz/{id}',[QuizController::class,'destroy']);

     // for Questions
    //  Route::post('/questions', [QuestionController::class, 'store']);
    //  Route::get('/questions', [QuestionController::class, 'index']);
    //  Route::get('/questions/all', [QuestionController::class, 'getAllQuestion']);
    //  Route::get('/questions/{id}', [QuestionController::class, 'show']);
    //  Route::put('/questions/{id}', [QuestionController::class, 'update']);
    //  Route::delete('/questions/{id}', [QuestionController::class, 'destroy']);
    //  Route::get('/search-questions', [QuestionController::class, 'searchQuestions']);

    //  for question bank question
    Route::post('/question-bank/questions', [QuestionController::class, 'storeOnQuestionBank']);

    // for doubts
    Route::get('/doubts', [DoubtController::class, 'index']);




    

});

Route::middleware(['auth:users', 'role:teacher'])->group(function () {
    // Route::get('/subjects', [SubjectController::class, 'index']);
});