<?php

use App\Http\Controllers\Institute\Classroom\StudentClassAssignmentController;
use App\Http\Controllers\Institute\Classroom\StudentClassDiscussionController;
use App\Http\Controllers\Institute\Classroom\StudentClassExamController;
use App\Http\Controllers\Institute\Classroom\StudentClassMeetingLinkController;
use App\Http\Controllers\Institute\Classroom\StudentClassNoteController;
use App\Http\Controllers\Institute\ClassEsewaController;
use App\Http\Controllers\Institute\InstitutePublicProfileController;
use App\Http\Controllers\Institute\InstituteReviewController;
use App\Http\Controllers\Institute\InstituteStudentAuthController;
use App\Http\Controllers\Institute\InstituteStudentProfileController;
use App\Http\Controllers\Institute\StudentClassController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Resolves the {institute} route parameter by slug or username, restricted to corporate accounts.
// This is what an institute's public landing page URL (examsnepal.com/institute/{slug}) uses.
Route::bind('institute', function (string $slug) {
    return User::where(function ($query) use ($slug) {
            $query->where('slug', $slug)
                ->orWhere('username', $slug)
                ->orWhereRaw('LOWER(slug) = ?', [strtolower($slug)])
                ->orWhereRaw('LOWER(username) = ?', [strtolower($slug)]);
        })
        ->whereHas('role', fn ($q) => $q->where('name', 'corporate'))
        ->firstOrFail();
});

Route::get('institutes', [InstitutePublicProfileController::class, 'index']);

Route::prefix('institute/{institute}')->group(function () {
    Route::get('/', [InstitutePublicProfileController::class, 'show']);
    Route::get('reviews', [InstituteReviewController::class, 'index']);
    Route::post('students/register', [InstituteStudentAuthController::class, 'register']);
    Route::post('students/login', [InstituteStudentAuthController::class, 'login']);
});

Route::prefix('institute/students')->middleware('auth:institute_student')->group(function () {
    Route::post('logout', [InstituteStudentAuthController::class, 'logout']);
    Route::get('profile', [InstituteStudentProfileController::class, 'show']);
    Route::put('profile', [InstituteStudentProfileController::class, 'update']);
    Route::get('reviews/mine', [InstituteReviewController::class, 'mine']);
    Route::post('reviews', [InstituteReviewController::class, 'store']);
    Route::get('classes', [StudentClassController::class, 'index']);
    Route::get('classes/{slug}', [StudentClassController::class, 'show']);
    Route::post('classes/{slug}/apply', [StudentClassController::class, 'apply']);
    Route::post('classes/{slug}/pay/init-transaction', [ClassEsewaController::class, 'beginTransaction']);

    Route::prefix('classes/{slug}')->group(function () {
        Route::get('notes', [StudentClassNoteController::class, 'index']);
        Route::get('meeting-links', [StudentClassMeetingLinkController::class, 'index']);

        Route::get('exams', [StudentClassExamController::class, 'index']);
        Route::get('exams/{exam}/questions', [StudentClassExamController::class, 'questions']);
        Route::post('exams/{exam}/answers', [StudentClassExamController::class, 'submitAnswers']);
        Route::get('exams/{exam}/result', [StudentClassExamController::class, 'result']);

        // Sectioned class-exam flow (is_class_exam = true)
        Route::get('exams/{exam}/sections', [StudentClassExamController::class, 'sections']);
        Route::post('exams/{exam}/section-answer', [StudentClassExamController::class, 'answerSectionQuestion']);
        Route::post('exams/{exam}/section-complete', [StudentClassExamController::class, 'completeSectionExam']);
        Route::get('exams/{exam}/section-result', [StudentClassExamController::class, 'sectionResult']);

        Route::get('assignments', [StudentClassAssignmentController::class, 'index']);
        Route::post('assignments/{assignment}/submit', [StudentClassAssignmentController::class, 'submit']);

        Route::get('discussions', [StudentClassDiscussionController::class, 'index']);
        Route::post('discussions', [StudentClassDiscussionController::class, 'store']);
        Route::delete('discussions/{post}', [StudentClassDiscussionController::class, 'destroy']);
        Route::post('discussions/{post}/replies', [StudentClassDiscussionController::class, 'storeReply']);
        Route::delete('discussions/{post}/replies/{reply}', [StudentClassDiscussionController::class, 'destroyReply']);
    });
});

// eSewa itself hits these via browser GET redirect — must stay public (no JWT on that request).
Route::controller(ClassEsewaController::class)
    ->prefix('institute/students/classes-payment/esewa')
    ->group(function () {
        Route::get('success', 'successPayment')->name('api.esewa.class.success');
        Route::get('failure', 'failurePayment')->name('api.esewa.class.failure');
    });
