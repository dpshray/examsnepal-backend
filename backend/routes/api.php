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
use App\Http\Controllers\AnswerSheetController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\TableMigrateController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\Payment\EsewaController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\isStudentSubscribedMiddleware;
use App\Models\Answersheet;
use App\Models\Question;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\SubscriptionTypeController;
use App\Http\Middleware\AuthEitherUser;
use App\Models\SubscriptionType;

// Registration route
Route::post('/register', [AuthController::class, 'register']);
Route::get('/migrate', [MigrationController::class, 'migrateNext']);

// Route::get('migrate-question-option', [MigrationController::class, 'migrateNext']);
Route::get('migrate-question-option', [TableMigrateController::class, 'migrateQuestionOption']);
Route::get('migrate-pools', [TableMigrateController::class, 'migratePool']);
Route::get('password-encryptor', [TableMigrateController::class, 'passwordHasher']);
Route::get('migrate-answersheets', [TableMigrateController::class, 'migrateAnswersheets']);
// Email verification route
Route::get('/email/verify/{id}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

// Student registration
Route::post('/student/register/', [StudentProfileController::class, 'register']);
Route::get('student_email_confirmation/{email}', [StudentProfileController::class, 'verifyStudentEmail'])->name('student_email_confirmation');
#Password Reset
Route::post('student-password-reset', [StudentProfileController::class, 'sendPasswordResetMail']);
Route::post('verify-password-reset-otp', [StudentProfileController::class, 'verifyPasswordReseToken']);
Route::post('handle-password-reset-form', [StudentProfileController::class, 'passwordResetor']);
// Student login
Route::post('/student/login', [AuthController::class, 'loginStudent'])->name('login');
Route::post('/admin/login', [AuthController::class, 'AdminLogin'])->name('loginAdmin');
Route::post('/teacher/login', [AuthController::class, 'teacherLogin'])->name('loginTeacher');

Route::apiResource('blog', BlogController::class)->scoped(['blog' => 'slug']);
#routes accessed by both api and users guards
Route::middleware(AuthEitherUser::class)->group(function(){
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::get('total-exam-count', [QuizController::class,'totalExamCounter']);
});

// Protected Routes (for authenticated students)
Route::middleware(['auth:api','verified'])->group(function () {
    Route::get('test', [BlogController::class,'test']);
    Route::get('auth-student', [AuthController::class, 'studentAuthResponse']);
    Route::post('/student/logout', [AuthController::class, 'logoutStudent']);
    Route::post('/student/refresh', [AuthController::class, 'refreshStudent']);
    Route::get('/student/me', [AuthController::class, 'me']);
    #Student Account Removal
    Route::delete('student-account-removal/{student}', [StudentProfileController::class, 'permanentStudentRemoveAccount']);

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
    Route::get('exam-scorers/{exam}', [ExamController::class, 'examPlayersScoreList']);
    Route::delete('/exam/{id}', [ExamController::class, 'destroy']);
    Route::post('/exam', [ExamController::class, 'store']);
    Route::get('/exam/{id}', [ExamController::class, 'show']);

    // for bookmarks
    Route::get('/bookmarks', [BookmarkController::class, 'index']);
    Route::post('/bookmarks', [BookmarkController::class, 'store']);
    Route::delete('/bookmarks/{question_id}', [BookmarkController::class, 'destroy']);
    Route::get('/bookmarks/student/{student_id}', [BookmarkController::class, 'getBookmarksByStudent']);
    Route::get('/bookmarks/allmy', [BookmarkController::class, 'getAllMyBookmarks']);

    // for Questions
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questions/{id}', [QuestionController::class, 'show']);
    Route::get('/search-questions', [QuestionController::class, 'searchQuestions']);

    // for Doubts
    Route::post('/doubt', [DoubtController::class, 'store']);
    Route::get('/doubt/student/solved', [DoubtController::class, 'fetchAuthStudentDoubtSolved']);
    Route::get('/doubt/student/unsolved', [DoubtController::class, 'fetchAuthStudentDoubtUnsolved']);

    #free quiz
    Route::get('/free-quiz/pending', [QuizController::class, 'getPendingFreeQuiz']);
    Route::get('/free-quiz/completed', [QuizController::class, 'getCompletedFreeQuiz']);

    #sprint quiz
    Route::get('/sprint-quiz/pending', [QuizController::class, 'getPendingSprintQuiz']);
    Route::get('/sprint-quiz/completed', [QuizController::class, 'getCompletedSprintQuiz']);
    
    #mock quiz
    Route::get('/mock-test/pending', [QuizController::class, 'getPendingMockTest']);
    Route::get('/mock-test/completed', [QuizController::class, 'getCompletedMockTest']);

    Route::get('/free-quiz/questions/{exam_id}', [QuestionController::class, 'freeQuizQuestions']);
    Route::middleware(isStudentSubscribedMiddleware::class)->group(function(){
        Route::get('/sprint-quiz/questions/{exam_id}', [QuestionController::class, 'sprintQuizQuestions']);
        Route::get('/mock-test/questions/{exam_id}', [QuestionController::class, 'mockTestQuestions']);
    });
    
    
    Route::post('/submit-answer', [AnswerSheetController::class, 'store']);
    
    # solutions
    Route::get('/view-solutions/{exam_id}', [AnswerSheetController::class, 'getResultsWithExam']);

    Route::get('user-free-exams-status', [QuizController::class, 'getFreeExamStatus']);
    Route::get('user-sprint-exams-status', [QuizController::class, 'getSprintExamStatus']);
    Route::get('user-mock-exams-status', [QuizController::class, 'getMockExamStatus']);


    Route::get('student-profile-fetcher', [StudentProfileController::class,'getStudentProfile']);
    Route::put('update-student-profile', [StudentProfileController::class,'studentProfileUpdater']);

        #pool
    Route::get('get-todays-pool-players', [PoolController::class, 'fetchTodaysPoolPlayers']);
    Route::get('request-pool-question', [PoolController::class, 'getPoolQuestions']);
    Route::post('send-pool-response', [PoolController::class, 'sendPoolQuestionResponse']);

    Route::get('student-exams-stats', [StudentProfileController::class, 'studentProfileExamStats']);

    Route::get('user-subscription-status', [SubscriptionTypeController::class, 'subscribeStat']);
    Route::apiResource('subscription-type', SubscriptionTypeController::class);
    Route::post('esewa/save-transaction', [EsewaController::class, 'storeTransaction']);

    Route::post('verify-promo-code', [PromoCodeController::class, 'checkPromoCodes']);
});

// for exam type
Route::get('/exam-types', [ExamTypeController::class, 'index']);

// Route::get('/documentation', function () {
//     return view('vendor.l5-swagger.index');
// })->withoutMiddleware('auth:api');

Route::middleware(['auth:users', 'role:admin'])->group(function () {

    // for subjects
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
    Route::put('/update-quiz/{exam}',[QuizController::class,'updateExamAsQuiz']);
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
    Route::get('/doubt/{id}', [DoubtController::class, 'show']);
});

Route::middleware(['auth:users', 'role:teacher'])->group(function () {
    // Route::get('/subjects', [SubjectController::class, 'index']);
});