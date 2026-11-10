<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doubt;
use App\Models\Exam;
use App\Models\ForumQuestionAnswerReport;
use App\Models\Notice;
use App\Models\NoticeFetchLog;
use App\Models\NoticeReport;
use App\Models\StudentExam;
use App\Models\StudentProfile;
use App\Models\TeacherPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

/**
 * Admin landing page: platform totals and the work waiting on an admin.
 * Trend figures come from admin/marketing/overview.
 */
class AdminDashboardController extends Controller
{
    /** Doubts stay status 1 until an admin resolves them (AdminController::resolve sets 0). */
    private const DOUBT_OPEN = 1;

    /** GET admin/students/stats - active means took an exam in the last 30 days. */
    public function studentStats()
    {
        $total = StudentProfile::count();
        $active = DB::table('student_metrics')->where('attempts_last_30d', '>', 0)->count();

        return Response::apiSuccess('Student stats', [
            'total' => $total,
            'active' => $active,
            'inactive' => max($total - $active, 0),
            'new_this_month' => StudentProfile::where('created_at', '>=', now()->startOfMonth())->count(),
        ]);
    }

    /** GET admin/submissions/stats - covers every row in the admin submissions list. */
    public function submissionStats()
    {
        $total = $this->listedSubmissions()->count();
        $completed = $this->listedSubmissions()->where('is_exam_completed', 1)->count();

        return Response::apiSuccess('Submission stats', [
            'total' => $total,
            'completed' => $completed,
            'not_completed' => $total - $completed,
            'completed_last_30_days' => $this->listedSubmissions()->where('is_exam_completed', 1)
                ->where('submitted_at', '>=', now()->subDays(30))
                ->count(),
        ]);
    }

    /** Same scope as AdminController::submissionsList: platform students only, not class exams. */
    private function listedSubmissions()
    {
        return StudentExam::has('student');
    }

    /** GET admin/doubts/stats */
    public function doubtStats()
    {
        $total = $this->listedDoubts()->count();
        $pending = $this->listedDoubts()->where('status', self::DOUBT_OPEN)->count();

        return Response::apiSuccess('Doubt stats', [
            'total' => $total,
            'resolved' => $total - $pending,
            'pending' => $pending,
        ]);
    }

    /** Same scope as the admin doubts list, so counts match that page. */
    private function listedDoubts()
    {
        return Doubt::whereHas('question', fn ($q) => $q->has('exam'))->has('student');
    }

    /** GET admin/dashboard/summary */
    public function summary()
    {
        $pendingPayouts = TeacherPayout::where('status', 'pending');

        return Response::apiSuccess('Dashboard summary', [
            'totals' => [
                'students' => DB::table('student_profiles')->where('is_hidden', 0)->count(),
                'active_subscribers' => DB::table('student_metrics')->whereIn('subscription_status', ['active', 'expiring_soon'])->count(),
                'expiring_soon' => DB::table('student_metrics')->where('subscription_status', 'expiring_soon')->count(),
                'active_exams' => Exam::where('is_active', 1)->count(),
                'questions' => DB::table('questions')->count(),
            ],
            'queues' => [
                'open_doubts' => $this->listedDoubts()->where('status', self::DOUBT_OPEN)->count(),
                'forum_reports' => ForumQuestionAnswerReport::count(),
                'pending_notices' => Notice::where('status', 'pending')->count(),
                'notice_reports' => NoticeReport::where('is_resolved', false)->count(),
                'pending_payouts' => (clone $pendingPayouts)->count(),
                'pending_payouts_amount' => (float) (clone $pendingPayouts)->sum('payout_amount'),
                'failed_fetches_24h' => NoticeFetchLog::where('status', 'failed')->where('started_at', '>=', now()->subDay())->count(),
            ],
        ]);
    }
}
