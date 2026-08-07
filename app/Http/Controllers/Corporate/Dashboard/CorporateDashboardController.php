<?php

namespace App\Http\Controllers\Corporate\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Corporate\CorporateExam;
use App\Models\Corporate\ParticipantGroup;
use App\Models\Doubt;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\StudentExam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class CorporateDashboardController extends Controller
{
    //
    function dashboard()
    {
        $teacher = Auth::user();

        // Corporate (recruitment) exams
        $exam_count = CorporateExam::where('corporate_id', $teacher->id)->count();
        $draft_exam = CorporateExam::where('corporate_id', $teacher->id)->where('is_published', false)->count();
        $published_exam = CorporateExam::where('corporate_id', $teacher->id)->where('is_published', true)->count();
        $totalSubmissions = ExamAttempt::whereHas('exam', function ($q) use ($teacher) {
            $q->where('corporate_id', $teacher->id);
        })
            ->whereIn('status', ['submitted', 'evaluating', 'evaluated'])
            ->count();

        $pendingSubmissions = ExamAttempt::whereHas('exam', function ($q) use ($teacher) {
            $q->where('corporate_id', $teacher->id);
        })
            ->where('status', 'evaluating')
            ->count();

        // Student exams (visible to students in the app)
        $studentExamCount = Exam::where('user_id', $teacher->id)->count();
        $liveStudentExamCount = Exam::where('user_id', $teacher->id)->where('live', 1)->count();
        $studentCompletionsThisMonth = StudentExam::whereHas('exam', fn ($q) => $q->where('user_id', $teacher->id))
            ->where('is_exam_completed', 1)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Doubts raised on the teacher's own student exams
        $pendingDoubtsCount = Doubt::whereHas('question.exam', fn ($q) => $q->where('user_id', $teacher->id))
            ->where('status', 1)
            ->count();

        // Participant groups (recruitment)
        $groupCount = ParticipantGroup::where('Corporate_id', $teacher->id)->count();

        // Recent activity: latest completed student-exam attempts
        $recentActivity = StudentExam::whereHas('exam', fn ($q) => $q->where('user_id', $teacher->id))
            ->where('is_exam_completed', 1)
            ->with(['student:id,name', 'exam:id,exam_name'])
            ->orderByDesc('created_at')
            ->take(6)
            ->get()
            ->map(fn ($studentExam) => [
                'student_name' => $studentExam->student->name ?? 'Unknown',
                'exam_name' => $studentExam->exam->exam_name ?? 'Unknown',
                'completed_at' => $studentExam->created_at,
            ]);

        $data = [
            'exam_count' => $exam_count,
            'draft_exam' => $draft_exam,
            'published_exam' => $published_exam,
            'total_submissions' => $totalSubmissions,
            'pending_submissions' => $pendingSubmissions,
            'student_exam_count' => $studentExamCount,
            'live_student_exam_count' => $liveStudentExamCount,
            'student_completions_this_month' => $studentCompletionsThisMonth,
            'pending_doubts_count' => $pendingDoubtsCount,
            'group_count' => $groupCount,
            'recent_activity' => $recentActivity,
        ];
        return Response::apiSuccess('DashBoard Data', $data);
    }
}
