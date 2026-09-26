<?php

namespace App\Http\Controllers\Student\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\StudentMetricsCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

/** In-app banner for the logged-in student, picked by lifecycle stage (config marketing.banners). */
class StudentBannerController extends Controller
{
    /** GET /api/student/marketing/banner -> {stage, banner|null} */
    public function show()
    {
        $studentId = (int) Auth::guard('api')->id();
        (new StudentMetricsCalculator())->refresh([$studentId]); // one student: cheap, and never stale
        $stage = DB::table('student_metrics')->where('student_id', $studentId)->value('lifecycle_stage');
        $banner = config("marketing.banners.{$stage}");

        return Response::apiSuccess('Banner', [
            'stage' => $stage,
            'banner' => $banner ? $banner + ['key' => $stage] : null,
        ]);
    }
}
