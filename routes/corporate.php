<?php

use App\Http\Controllers\Corporate\Classroom\ClassAssignmentController;
use App\Http\Controllers\Corporate\Classroom\ClassAssignmentSubmissionController;
use App\Http\Controllers\Corporate\Classroom\ClassDiscussionController;
use App\Http\Controllers\Corporate\Classroom\ClassExamController;
use App\Http\Controllers\Corporate\Classroom\ClassExamQuestionController;
use App\Http\Controllers\Corporate\Classroom\ClassExamSectionController;
use App\Http\Controllers\Corporate\Classroom\ClassExamSubmissionController;
use App\Http\Controllers\Corporate\Classroom\ClassMeetingLinkController;
use App\Http\Controllers\Corporate\Classroom\ClassNoteController;
use App\Http\Controllers\Corporate\Classroom\ClassroomController;
use App\Http\Controllers\Corporate\Classroom\ClassStudentController;
use App\Http\Controllers\Corporate\Classroom\ClassSyllabusTopicController;
use App\Http\Controllers\Corporate\CorporateApiKeyController;
use App\Http\Controllers\Corporate\CorporateAuthController;
use App\Http\Controllers\Corporate\CorporateExamController;
use App\Http\Controllers\Corporate\CorporateExamSectionController;
use App\Http\Controllers\Corporate\CorporateProfileController;
use App\Http\Controllers\Corporate\CorporateQuestionController;
use App\Http\Controllers\Corporate\Dashboard\CorporateDashboardController;
use App\Http\Controllers\Corporate\Exam\AddParticipantToExamController;
use App\Http\Controllers\Corporate\Participant\CorporateParticipantController;
use App\Http\Controllers\Corporate\Participant\Exam\ExamEvaluationController;
use App\Http\Controllers\Corporate\Participant\Exam\ParticipantExamSubmitController;
use App\Http\Controllers\Corporate\Participant\Exam\Result\CorporateResultController;
use App\Http\Controllers\Corporate\Participant\Exam\Result\ExamResultController;
use App\Http\Controllers\Corporate\Participant_Group\CorporateGroupController;
use App\Http\Controllers\Corporate\Participant_Group\CorporateParticipantGroupController;
use Illuminate\Support\Facades\Route;

Route::prefix('corporate')->group(function () {
    Route::post('login', [CorporateAuthController::class, 'login']);
    Route::post('register', [CorporateAuthController::class, 'register']);
    Route::post('logout', [CorporateAuthController::class, 'logout'])->middleware('auth:users');
    Route::controller(CorporateAuthController::class)->group(function () {
        Route::post('forgot-password', 'forgotPassword');
        Route::match(['GET', 'POST'], 'password-resetor/{token}', 'paswordResetorFormHandler')->name('password.reset');
    });
    Route::middleware('auth:users')->group(function () {
        Route::apiResource('exam', CorporateExamController::class)->scoped(['exam'=>'slug'])->names('corporate.exam');
        Route::apiResource('exam.section', CorporateExamSectionController::class)->scoped(['exam'=>'slug','section'=>'slug']);
        Route::apiResource('exam/section.questions', CorporateQuestionController::class)->scoped(['section'=>'slug']);
        Route::post('exam/section/{section}/questions/bulk-import', [CorporateQuestionController::class, 'bulkImport']);
        Route::post('exam/section/{section}/questions/bulk-import/publish', [CorporateQuestionController::class, 'publishBulkImport']);
        Route::apiResource('exam/{exam}/participants', CorporateParticipantController::class);
        Route::post('participants/import', [CorporateParticipantController::class, 'store_from_excel']);
        Route::post('/exam/{exam}/participants/bulk-delete', [CorporateParticipantController::class, 'bulk_delete']);
        Route::controller(AddParticipantToExamController::class)->group(function () {
            Route::get('exams/{exam}/participants','index');
            Route::Post('exams/{exam}/participants','store');
            Route::delete('exams/participants','destroy');
            Route::post('exams/{exam}/bulk-upload-participants','bulk_upload_in_exam');
        });
        Route::post('/exam-publish/{exam}',[CorporateExamController::class,'published_exam']);
        Route::post('/exam/{exam}/group-participants',[CorporateExamController::class,'upload_group']);
        // Route::apiResource('/exam/submission',ParticipantExamSubmitController::class)->only(['index','show']);
        Route::controller(ParticipantExamSubmitController::class)->group(function (){
            Route::get('/exams/{exam}/submitted-exams','index');
            Route::get('/exams/submitted-exams/{attempts}','show');
        });
        Route::controller(ExamEvaluationController::class)->group(function (){
            Route::post('/exams/evaluate/{attempt}','evaluating');
        });
        Route::controller(CorporateDashboardController::class)->group(function (){
            Route::get('/dashboard','dashboard');
        });
        Route::controller(CorporateProfileController::class)->prefix('profile')->group(function () {
            Route::get('/', 'show');
            Route::post('/', 'update');
        });
        Route::get('api-key', [CorporateApiKeyController::class, 'show']);
        Route::post('api-key/regenerate', [CorporateApiKeyController::class, 'regenerate']);
        Route::get('/exams/{exam}/download-results', [ExamResultController::class, 'downloadExamResults']);
        Route::get('/exam/{exam}/get-participant',[CorporateExamSectionController::class,'participantList']);
        Route::controller(CorporateResultController::class)->group(function(){
            Route::get('/exams/{exam}/results','ExamResultList');
            Route::get('/exams/{exam}/results/detail/{result_token}','studentExamResultDetail');
            Route::get('/exams/{exam}/results/section-detail/{result_token}/{section}','studentSectionWiseDetail');
        });
        Route::apiResource('groups',CorporateGroupController::class)->scoped(['group'=>'slug']);
        Route::apiResource('groups.members',CorporateParticipantGroupController::class)->scoped(['group'=>'slug','members'=>'id']);
        Route::post('/groups/{group}/members/bulk-delete',[CorporateParticipantGroupController::class,'bulk_delete']);
        Route::post('/groups/{group}/members/bulk-upload',[CorporateParticipantGroupController::class,'bulk_upload']);
        Route::post('exams/{exam}/send-invitations', [CorporateExamController::class, 'send_email']);

        Route::apiResource('classes', ClassroomController::class)->scoped(['class' => 'slug']);
        Route::prefix('classes/{class}')->group(function () {
            Route::get('notes', [ClassNoteController::class, 'index']);
            Route::post('notes', [ClassNoteController::class, 'store']);
            Route::post('notes/{note}', [ClassNoteController::class, 'update']);
            Route::delete('notes/{note}', [ClassNoteController::class, 'destroy']);

            Route::get('exams', [ClassExamController::class, 'index']);
            Route::get('available-exams', [ClassExamController::class, 'available']);
            Route::post('exams', [ClassExamController::class, 'store']);
            Route::post('exams/create', [ClassExamController::class, 'createNew']);
            Route::delete('exams/{exam}', [ClassExamController::class, 'destroy']);

            Route::prefix('exams/{exam}')->group(function () {
                Route::get('sections', [ClassExamSectionController::class, 'index']);
                Route::post('sections', [ClassExamSectionController::class, 'store']);
                Route::put('sections/{section}', [ClassExamSectionController::class, 'update']);
                Route::delete('sections/{section}', [ClassExamSectionController::class, 'destroy']);

                Route::get('sections/{section}/questions', [ClassExamQuestionController::class, 'index']);
                Route::post('sections/{section}/questions', [ClassExamQuestionController::class, 'store']);
                // These two literal routes must be registered before the {question}
                // wildcard routes below, otherwise "bulk-import" gets matched as a
                // {question} route-model-binding value and 404s with a confusing
                // "No query results for model [ClassExamQuestion] bulk-import" error.
                Route::post('sections/{section}/questions/bulk-import', [ClassExamQuestionController::class, 'bulkImport']);
                Route::post('sections/{section}/questions/bulk-import/publish', [ClassExamQuestionController::class, 'publishBulkImport']);
                Route::post('sections/{section}/questions/{question}', [ClassExamQuestionController::class, 'update']);
                Route::delete('sections/{section}/questions/{question}', [ClassExamQuestionController::class, 'destroy']);

                Route::get('submissions', [ClassExamSubmissionController::class, 'index']);
                Route::get('submissions/{studentExam}', [ClassExamSubmissionController::class, 'show']);
                Route::post('submissions/answers/{answer}/grade', [ClassExamSubmissionController::class, 'gradeAnswer']);
            });

            Route::get('students', [ClassStudentController::class, 'index']);
            Route::post('students', [ClassStudentController::class, 'store']);
            Route::patch('students/{student}', [ClassStudentController::class, 'update']);
            Route::delete('students/{student}', [ClassStudentController::class, 'destroy']);
            Route::post('students/bulk-upload', [ClassStudentController::class, 'bulk_upload']);
            Route::post('students/bulk-delete', [ClassStudentController::class, 'bulk_delete']);

            Route::get('meeting-links', [ClassMeetingLinkController::class, 'index']);
            Route::post('meeting-links', [ClassMeetingLinkController::class, 'store']);
            Route::put('meeting-links/{meetingLink}', [ClassMeetingLinkController::class, 'update']);
            Route::delete('meeting-links/{meetingLink}', [ClassMeetingLinkController::class, 'destroy']);

            Route::get('syllabus-topics', [ClassSyllabusTopicController::class, 'index']);
            Route::post('syllabus-topics', [ClassSyllabusTopicController::class, 'store']);
            Route::put('syllabus-topics/{topic}', [ClassSyllabusTopicController::class, 'update']);
            Route::delete('syllabus-topics/{topic}', [ClassSyllabusTopicController::class, 'destroy']);

            Route::get('assignments', [ClassAssignmentController::class, 'index']);
            Route::post('assignments', [ClassAssignmentController::class, 'store']);
            Route::post('assignments/{assignment}', [ClassAssignmentController::class, 'update']);
            Route::delete('assignments/{assignment}', [ClassAssignmentController::class, 'destroy']);

            Route::get('assignments/{assignment}/submissions', [ClassAssignmentSubmissionController::class, 'index']);
            Route::get('assignments/{assignment}/submissions/export', [ClassAssignmentSubmissionController::class, 'export']);
            Route::post('assignments/{assignment}/submissions/{submission}/grade', [ClassAssignmentSubmissionController::class, 'grade']);
            Route::post('assignments/{assignment}/submissions/{submission}/annotate', [ClassAssignmentSubmissionController::class, 'annotate']);
            Route::get('assignments/{assignment}/submissions/{submission}/original-file', [ClassAssignmentSubmissionController::class, 'downloadOriginal']);

            Route::get('discussions', [ClassDiscussionController::class, 'index']);
            Route::post('discussions', [ClassDiscussionController::class, 'store']);
            Route::delete('discussions/{post}', [ClassDiscussionController::class, 'destroy']);
            Route::post('discussions/{post}/replies', [ClassDiscussionController::class, 'storeReply']);
            Route::delete('discussions/{post}/replies/{reply}', [ClassDiscussionController::class, 'destroyReply']);
        });
    });
});
