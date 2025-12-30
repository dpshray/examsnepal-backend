<?php

namespace App\Http\Controllers\Corporate\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Corporate\CorporateExam;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class CorporateDashboardController extends Controller
{
    //
    function dashboard()
    {
        $teacher = Auth::user();
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
        $data = [
            'exam_count' => $exam_count,
            'draft_exam' => $draft_exam,
            'published_exam' => $published_exam,
            'total_submissions' => $totalSubmissions,
            'pending_submissions' => $pendingSubmissions,
        ];
        return Response::apiSuccess('DashBoard Data', $data);
    }
}
