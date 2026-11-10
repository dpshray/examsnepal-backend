<?php

use App\Enums\ExamTypeEnum;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPayoutController;
use App\Http\Controllers\Admin\Blog\AdminBlogCategoryController;
use App\Http\Controllers\Admin\Blog\AdminBlogController;
use App\Http\Controllers\Admin\Blog\AdminBlogTagController;
use App\Http\Controllers\Admin\Exam\Type\AdminExamTypeController;
use App\Http\Controllers\Admin\ExamTag\AdminExamTagController;
use App\Http\Controllers\Admin\ForumQuestionReport\AdminForumQuestionReportController;
use App\Http\Controllers\Admin\Notification\AdminNotificationController;
use App\Http\Controllers\Admin\Payment\AdminPaymentSettingController;
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
use App\Http\Controllers\Admin\AdminInstituteController;
use App\Http\Controllers\BankQuestionController;
use App\Http\Controllers\DoubtController;
use App\Http\Controllers\AnswerSheetController;
use App\Http\Controllers\BlockedUserController;
use App\Http\Controllers\Contact\ContactController;
use App\Http\Controllers\Corporate\CorporateExamController;
use App\Http\Controllers\ExamCategoryController;
use App\Http\Controllers\Frontend\ExamGuideController;
use App\Http\Controllers\Frontend\NoticeController;
use App\Http\Controllers\Admin\Notice\AdminNoticeController;
use App\Http\Controllers\Admin\Notice\AdminNoticeReportController;
use App\Http\Controllers\Admin\Notice\AdminNoticeSourceController;
use App\Http\Controllers\Frontend\FrontendController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\Participant\ParticipantController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\isStudentSubscribedMiddleware;
use App\Models\Answersheet;
use App\Models\Question;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\Student\Exam\StudentExamController;
use App\Http\Controllers\Student\Exam\StudentSubmitAnswersController;
use App\Http\Controllers\Student\ExamRegister\StudentExamRegisterController;
use App\Http\Controllers\SubscriptionTypeController;
use App\Http\Controllers\Admin\Marketing\AdminInsightsController;
use App\Http\Controllers\Admin\Marketing\AdminMarketingController;
use App\Http\Controllers\Admin\Marketing\AdminMessagingController;
use App\Http\Middleware\AuthEitherUser;
use App\Http\Middleware\CheckTokenVersionMiddleware;
use App\Models\Exam;
use App\Models\SubscriptionType;
use Illuminate\Http\Request;
use App\Models\PromoCode;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/corporate.php';
require __DIR__ . '/institute.php';
require __DIR__ . '/payment.php';
require __DIR__ . '/teacher.php';
require __DIR__ . '/testing.php';


// The one-off legacy data migration endpoints (migrate*, password-encryptor) and
// the unused POST /register were public and are removed; run migrations from the CLI.

// Email verification route
Route::get('/email/verify/{id}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

// Student registration
Route::post('/student/register/', [StudentProfileController::class, 'register'])->middleware('throttle:auth');
Route::get('student_email_confirmation/{email}', [StudentProfileController::class, 'verifyStudentEmail'])->name('student_email_confirmation');
#Password Reset
Route::post('student-password-reset', [StudentProfileController::class, 'sendPasswordResetMail'])->middleware('throttle:auth');
Route::post('verify-password-reset-otp', [StudentProfileController::class, 'verifyPasswordReseToken'])->middleware('throttle:otp');
Route::post('handle-password-reset-form', [StudentProfileController::class, 'passwordResetor'])->middleware('throttle:otp');
// Student login
Route::post('/student/login', [AuthController::class, 'loginStudent'])->name('login')->middleware('throttle:auth');
Route::post('/admin/login', [AuthController::class, 'AdminLogin'])->name('loginAdmin')->middleware('throttle:auth');
Route::post('student-google-login', [StudentProfileController::class, 'googleLogin']);
Route::post('/student/resend-email-verification', [AuthController::class, 'resendStudentEmailVerification'])->middleware('throttle:auth');

#routes accessed by both api and users guards
Route::middleware(AuthEitherUser::class)->group(function () {
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::get('total-exam-count', [QuizController::class, 'totalExamCounter']);
});

// Protected Routes (for authenticated students)
Route::middleware(['auth:api', 'verified', CheckTokenVersionMiddleware::class])->group(function () {
    Route::get('/student/marketing/banner', [\App\Http\Controllers\Student\Marketing\StudentBannerController::class, 'show']);
    Route::get('/student/onboarding', [\App\Http\Controllers\Student\Marketing\StudentOnboardingController::class, 'show']);
    Route::post('/student/onboarding', [\App\Http\Controllers\Student\Marketing\StudentOnboardingController::class, 'store']);
    Route::get('auth-student', [AuthController::class, 'studentAuthResponse']);
    Route::post('/student/logout', [AuthController::class, 'logoutStudent']);
    Route::post('/student/refresh', [AuthController::class, 'refreshStudent']);
    Route::get('/student/me', [AuthController::class, 'me']);
    #Student Account Removal
    Route::delete('student-account-removal/{student}', [StudentProfileController::class, 'permanentStudentRemoveAccount']);

    Route::get('get-student-performance-data', [StudentProfileController::class, 'studentPerformanceReport']);
    Route::get('/students/me/exams/scores', [StudentProfileController::class, 'myExamScores']);
    Route::get('/students/get-my-exams-score/{exam}', [StudentProfileController::class, 'myAnExamScore']);

    Route::get('/student/questions', [ForumController::class, 'fetchQuestions']);
    Route::get('/student/questions/answer/{id}', [ForumController::class, 'question_answer']);
    Route::get('/student/myquestions', [ForumController::class, 'fetchMyQuestions']);
    Route::post('/student/addquestion', [ForumController::class, 'addQuestion']);
    Route::get('/student/questions/{id}', [ForumController::class, 'getQuestionById']);
    Route::put('/student/questions/edit/{id}', [ForumController::class, 'updateQuestion']);
    Route::get('/student/questions/their/{id}', [ForumController::class, 'getQuestionByTheirId']);
    Route::get('/student/questions/{subStream}', [ForumController::class, 'fetchQuestionsBySubstream']);
    Route::delete('/student/questions/{id}', [ForumController::class, 'deleteTheirQuestionCreated']);
    Route::get('/forum-question-view-increment/{id}', [ForumController::class, 'incrementForumQuestionView']);

    Route::post('/student/answers', [ForumController::class, 'addAnswer']);


    // Students only read these. The create/update/delete routes for exam types,
    // organizations and exams were reachable by any logged-in student and are
    // removed; admins manage them through the /admin routes.
    Route::get('/exam-types/{id}', [ExamTypeController::class, 'show']);

    Route::get('exam-scorers/{exam}', [ExamController::class, 'examPlayersScoreList']);
    Route::get('/exam/{id}', [ExamController::class, 'show']);

    // for bookmarks (the per-student list exposed other students' bookmarks and is removed)
    Route::get('/bookmarks', [BookmarkController::class, 'index']);
    Route::post('/bookmarks', [BookmarkController::class, 'store']);
    Route::delete('/bookmarks/{question_id}', [BookmarkController::class, 'destroy']);
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
    Route::middleware(isStudentSubscribedMiddleware::class)->group(function () {
        Route::get('/sprint-quiz/questions/{exam_id}', [QuestionController::class, 'sprintQuizQuestions']);
        Route::get('/mock-test/questions/{exam_id}', [QuestionController::class, 'mockTestQuestions']);
    });


    Route::post('/submit-answer', [AnswerSheetController::class, 'store']);

    # solutions
    Route::get('/view-solutions/{exam_id}', [AnswerSheetController::class, 'getResultsWithExam']);

    Route::get('user-free-exams-status', [QuizController::class, 'getFreeExamStatus']);
    Route::get('user-sprint-exams-status', [QuizController::class, 'getSprintExamStatus']);
    Route::get('user-mock-exams-status', [QuizController::class, 'getMockExamStatus']);


    Route::get('student-profile-fetcher', [StudentProfileController::class, 'getStudentProfile']);
    Route::put('update-student-profile', [StudentProfileController::class, 'studentProfileUpdater']);
    Route::put('student-profile/visibility-toggle', [StudentProfileController::class, 'studentProfileVisibility']);

    #pool
    Route::get('get-todays-pool-players', [PoolController::class, 'fetchTodaysPoolPlayers']);
    Route::get('request-pool-question', [PoolController::class, 'getPoolQuestions']);
    Route::post('send-pool-response', [PoolController::class, 'sendPoolQuestionResponse']);

    Route::get('student-exams-stats', [StudentProfileController::class, 'studentProfileExamStats']);

    Route::get('user-subscription-status', [SubscriptionTypeController::class, 'subscribeStat']);
    // Read-only for students: prices are managed through admin/subscription-type.
    Route::apiResource('subscription-type', SubscriptionTypeController::class)->only(['index', 'show'])->names('student.subscription-type');


    Route::post('verify-promo-code', [PromoCodeController::class, 'checkPromoCodes']);
    Route::get('notification', [NotificationController::class, 'getUserNotifications']);

    Route::post('forum-report', [ForumController::class, 'reportForumQuestion']);
    Route::delete('forum-answer-delete/{id}', [ForumController::class, 'deleteAnswer']);

    Route::get('/user/blocked', [BlockedUserController::class, 'getBlockedUsers']);
    Route::post('/user/block', [BlockedUserController::class, 'blockUser']);
    Route::delete('/user/unblock/{id}', [BlockedUserController::class, 'unblockUser']);
});

// for exam type
Route::get('/exam-types', [ExamTypeController::class, 'index']);
Route::get('/category-types', ExamCategoryController::class);
Route::get('/exam-tag/{examTypeId}', [QuizController::class, 'getTagsByExamType']);

//blog
Route::apiResource('user/blogs', AdminBlogController::class)->scoped(['blog' => 'slug'])->only(['index', 'show'])->names('user.blogs');
// Route::get('/documentation', function () {
//     return view('vendor.l5-swagger.index');
// })->withoutMiddleware('auth:api');

Route::middleware(['auth:users', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard/summary', [AdminDashboardController::class, 'summary']);
    Route::get('/admin/doubts/stats', [AdminDashboardController::class, 'doubtStats']);
    Route::get('/admin/students/stats', [AdminDashboardController::class, 'studentStats']);
    Route::get('/admin/submissions/stats', [AdminDashboardController::class, 'submissionStats']);

    Route::get('/admin/forum-question-report', [AdminForumQuestionReportController::class, 'index']);
    Route::delete('/admin/forum-question-report/{id}', [AdminForumQuestionReportController::class, 'delete']);

    Route::get('/admin/blocked-users', [BlockedUserController::class, 'adminallblockeduser']);
    // for subjects
    Route::post('/subject', [SubjectController::class, 'store']);
    Route::get('/subject/{id}', [SubjectController::class, 'show']);
    Route::put('/subject/{id}', [SubjectController::class, 'update']);
    Route::delete('/subject/{id}', [SubjectController::class, 'destroy']);

    Route::get('/all-students', [StudentProfileController::class, 'allStudents']);

    Route::get('/teachers', [TeacherController::class, 'index']);
    Route::get('/admin/institutes', [AdminInstituteController::class, 'index']);
    Route::post('/admin/institutes/{user}/toggle-status', [AdminInstituteController::class, 'toggleStatus']);
    Route::get('/all-question-banks', [BankQuestionController::class, 'index']);
    Route::post('/question-bank', [BankQuestionController::class, 'store']);
    Route::get('/question-bank/{id}', [BankQuestionController::class, 'show']);
    Route::put('/question-bank/{id}', [BankQuestionController::class, 'update']);
    Route::delete('/question-bank/{id}', [BankQuestionController::class, 'destroy']);

    //added subscriber to user by admin
    Route::post('/add-subscriber/{studentId}', [AdminController::class, 'addorupdate']);
    Route::get('/subtype/{student}', [AdminController::class, 'subtype']);
    Route::get('/submissionslist', [AdminController::class, 'submissionsList']);
    Route::post('/logout', [AdminController::class, 'logoutadmin']);

    // exam type
    Route::apiResource('admin/examtype', AdminExamTypeController::class);
    //added doubt list
    Route::get('/doubtslist', [AdminController::class, 'doubtslist']);
    Route::post('/doubtsresolve/{doubt}', [AdminController::class, 'resolve']);

    // teacher revenue-share payout approval
    Route::get('/admin/payouts/summary', [AdminPayoutController::class, 'summary']);
    Route::get('/admin/payouts', [AdminPayoutController::class, 'index']);
    Route::get('/admin/payouts/{payout}', [AdminPayoutController::class, 'show']);
    Route::post('/admin/payouts/{payout}/approve', [AdminPayoutController::class, 'approve']);
    Route::post('/admin/payouts/{payout}/mark-paid', [AdminPayoutController::class, 'markPaid']);
    Route::post('/admin/payouts/{payout}/revert', [AdminPayoutController::class, 'revert']);

    Route::post('/create-quiz', [QuizController::class, 'examAsQuizStore']);
    Route::get('/quiz/{id}', [QuizController::class, 'show']);
    Route::put('/update-quiz/{exam}', [QuizController::class, 'updateExamAsQuiz']);
    Route::delete('/quiz/{id}', [QuizController::class, 'destroy']);

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


    Route::post('students/notifications', [AdminNotificationController::class, 'sendBulkPushNotification']);
    Route::apiResource('admin/students-notifications', AdminNotificationController::class);
    // Route::post('/students/notification/{student_notification}', [NotificationController::class , 'deleteNotification']);

    Route::get('/admin/manual-student-email-verify/{student_profile_id}', [AuthController::class, 'manualStudentEmailVerifier']);
    Route::post('/admin/manual-student-email-revoke/{student_profile_id}', [AuthController::class, 'manualStudentEmailRevoker']);

    //subscription type
    Route::apiResource('admin/subscription-type', SubscriptionTypeController::class)->except(['index'])->names('admin.subscription-type');
    Route::get('admin/subscription-type-list', [SubscriptionTypeController::class, 'subscriptionlist']);
    Route::apiResource('admin/exam-tags', AdminExamTagController::class);

    //blog
    Route::apiResource('admin/blog/category', AdminBlogCategoryController::class)->scoped(['category' => 'slug']);
    Route::apiResource('admin/blog/tag', AdminBlogTagController::class)->scoped(['tag' => 'slug']);
    Route::apiResource('admin/blogs', AdminBlogController::class)->scoped(['blog' => 'slug'])->names('admin.blogs');
    Route::post('admin/payment-settings', [AdminPaymentSettingController::class, 'store']);
    //Promocode 
    Route::apiResource('admin/promo-code', PromoCodeController::class);

    // Notices (docs/notices-plan.md)
    Route::get('admin/notices/stats', [AdminNoticeController::class, 'stats']);
    Route::get('admin/notices/meta', [AdminNoticeController::class, 'meta']);
    Route::post('admin/notices/{notice}/approve', [AdminNoticeController::class, 'approve']);
    Route::post('admin/notices/{notice}/facebook', [AdminNoticeController::class, 'postToFacebook']);
    Route::post('admin/notices/{notice}/reject', [AdminNoticeController::class, 'reject']);
    Route::post('admin/notices/{notice}/feature', [AdminNoticeController::class, 'toggleFeature']);
    Route::post('admin/notices/{notice}/re-enrich', [AdminNoticeController::class, 'reEnrich']);
    Route::apiResource('admin/notices', AdminNoticeController::class)->names('admin.notices');
    Route::post('admin/notice-sources/test-fetch', [AdminNoticeSourceController::class, 'testFetch']);
    Route::post('admin/notice-sources/{noticeSource}/test-fetch', [AdminNoticeSourceController::class, 'testFetch']);
    Route::post('admin/notice-sources/{noticeSource}/fetch-now', [AdminNoticeSourceController::class, 'fetchNow']);
    Route::apiResource('admin/notice-sources', AdminNoticeSourceController::class)->names('admin.notice-sources');
    Route::get('admin/notice-fetch-logs', [AdminNoticeSourceController::class, 'logs']);
    Route::get('admin/notice-reports', [AdminNoticeReportController::class, 'index']);
    Route::post('admin/notice-reports/{noticeReport}/resolve', [AdminNoticeReportController::class, 'resolve']);

    // Marketing dashboard (docs/marketing.md)
    Route::controller(AdminMarketingController::class)->prefix('admin/marketing')->group(function () {
        Route::get('overview', 'overview');
        Route::get('cohorts', 'cohorts');
        Route::get('meta', 'meta');
        Route::get('students', 'students');
        Route::get('students/export', 'export');
        Route::post('students/tags', 'tags');
        Route::get('students/{id}', 'student')->whereNumber('id');
        Route::get('segments', 'segments');
        Route::post('segments', 'storeSegment');
        Route::delete('segments/{id}', 'destroySegment')->whereNumber('id');
    });
    Route::controller(AdminInsightsController::class)->prefix('admin/marketing/insights')->group(function () {
        Route::get('summary', 'summary');
        Route::post('brief', 'brief')->middleware('throttle:10,1');
        Route::get('{source}', 'source')->whereIn('source', ['search', 'analytics', 'facebook']);
    });
    Route::controller(AdminMessagingController::class)->prefix('admin/marketing')->group(function () {
        Route::get('messaging/status', 'status');
        Route::post('messaging/pause', 'pause');
        Route::get('automations', 'automations');
        Route::patch('automations/{automation}', 'updateAutomation');
        Route::get('automations/{automation}/preview', 'preview');
        Route::get('sends', 'sends');
        Route::get('suppressions', 'suppressions');
        Route::post('suppressions', 'suppress');
        Route::delete('suppressions/{id}', 'unsuppress')->whereNumber('id');
        Route::post('automations/{automation}/promote', 'promote');
        Route::get('templates', 'templates');
        Route::post('templates', 'storeTemplate');
        Route::post('templates/draft-preview', 'draftPreview');
        Route::put('templates/{template:key}', 'updateTemplate');
        Route::delete('templates/{template:key}', 'destroyTemplate');
        Route::get('templates/{template:key}/preview', 'renderTemplate');
        Route::post('templates/{template:key}/test', 'sendTest');
        Route::get('broadcasts', 'broadcasts');
        Route::post('broadcasts/preview', 'previewBroadcast');
        Route::post('broadcasts', 'storeBroadcast');
        Route::post('broadcasts/{broadcast}/cancel', 'cancelBroadcast');
    });
});
Route::get('admin/payment-settings', [AdminPaymentSettingController::class, 'index']);

// Were public; no app calls them, so they are admin-only.
Route::middleware(['auth:users', 'role:admin'])->controller(ParticipantController::class)->group(function () {
    Route::post('/store-participant', 'store');
    Route::post('/excel-participant', 'store_from_excel');
    Route::post('/update-participant/{participant}', 'update');
    Route::delete('/delete-participant/{participant}', 'destroy');
});
// Route::get('/all-students', [StudentProfileController::class, 'allStudents']);
Route::post('/contact', [ContactController::class, 'store']);

//corparate student
Route::controller(StudentExamController::class)->group(function () {
    Route::get('exams/{exams}/examsdetail', 'examIntro');
    Route::get('exam-type/{exams}', 'examtype');
    Route::post('/exam/{exam}/section/{section}/startexam', 'startsectionexam');
    Route::get('/get-question/{attempt_id}', 'getquestion');
});
Route::controller(StudentExamRegisterController::class)->group(function () {
    Route::post('/exam/{exam}/private-login', 'login')->middleware('throttle:auth');
    Route::post('/exam/{exam}/register-public', 'registerStudent_public_exam');
    Route::post('/auth/logout', 'logout')->middleware('auth:sanctum');
});
Route::controller(StudentSubmitAnswersController::class)->group(function () {
    Route::post('/submit-answer/{attempt_id}', 'Submit_Answer');
    Route::post('/submit-exam/{attempt_id}', 'submit_exam');
});
Route::controller(FrontendController::class)->group(function () {
    Route::get('/dashboard', 'dashboard');
    Route::get('free/search-questions', 'searchQuestions');
    Route::get('free/mcq/{slug}', 'showQuestionBySlug');
    Route::get('free/mcq/{slug}/comments', 'questionComments');
    Route::post('free/mcq/{slug}/view', 'recordQuestionView')->middleware('throttle:60,1');
    Route::post('free/mcq/{slug}/comments', 'storeQuestionComment')->middleware('throttle:20,1');
    Route::get('free/exam-categories', 'examCategories');
    Route::get('free/exam-categories/{categorySlug}/questions', 'questionsByCategory');
    Route::get('free/mcq-topics', 'mcqTopics');
    Route::get('free/mcq-topics/{categorySlug}/{subjectSlug}', 'mcqTopic');
    Route::get('sitemap/mcq-meta', 'sitemapMcqMeta');
    Route::get('sitemap/mcqs', 'sitemapMcqs');
});

// Exam-guide hub-and-spoke SEO pages (/exams/*) - distinct from the
// MCQ-browsing "free/exam-categories" above, which is ExamType-based.
Route::controller(ExamGuideController::class)->group(function () {
    Route::get('free/exam-guides/categories', 'categories');
    Route::get('free/exam-guides/menu', 'menu');
    Route::get('free/exam-guides/categories/{categorySlug}', 'category');
    Route::get('free/exam-guides/{categorySlug}/{examSlug}', 'guide');
    Route::get('free/stats', 'stats');
});

// Official notices (Loksewa / entrance / license) - public, used by the
// website and the mobile apps.
Route::controller(NoticeController::class)->group(function () {
    Route::get('free/notices', 'index');
    Route::get('free/notices/home', 'home');
    Route::get('free/notices/meta', 'meta');
    Route::get('free/notices/sitemap', 'sitemap');
    Route::post('free/notices/subscribe', 'subscribe')->middleware('throttle:5,1');
    Route::get('free/notices/unsubscribe/{token}', 'unsubscribe');
    Route::get('free/notices/{slug}', 'show');
    Route::post('free/notices/{slug}/report', 'report')->middleware('throttle:5,1');
    Route::post('notices/subscribe-push', 'subscribePush')->middleware(['auth:api', CheckTokenVersionMiddleware::class]);
});
