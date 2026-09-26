<?php

namespace App\Http\Controllers\Student\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\EventTracker;
use App\Services\Marketing\TemplateRenderer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

/**
 * 2-step onboarding (docs/marketing.md, Phase 8):
 *  1. Which exam are you preparing for? (prefilled from signup)
 *  2. When is your exam? (month/year, or "not sure")
 * then straight into the first free quiz.
 */
class StudentOnboardingController extends Controller
{
    public const NEXT_PATH = '/student/exams/free-quiz';

    /** GET /api/student/onboarding */
    public function show()
    {
        $student = Auth::guard('api')->user();

        return Response::apiSuccess('Onboarding', [
            'needs_onboarding' => self::needsOnboarding($student),
            'exam_type_id' => $student->exam_type_id,
            'exam_locked' => $this->hasActivePlan($student->id),
            'target_exam_date' => $student->target_exam_date?->toDateString(),
            'exam_types' => DB::table('exam_types')->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'next_path' => self::NEXT_PATH,
        ]);
    }

    /** POST /api/student/onboarding {exam_type_id, exam_month: "YYYY-MM"|null, not_sure?: bool, skip?: bool} */
    public function store(Request $request)
    {
        $student = Auth::guard('api')->user();

        if ($request->boolean('skip')) {
            $student->forceFill(['onboarded_at' => now()])->save();
            app(EventTracker::class)->track($student->id, 'onboarding_skipped');
            return Response::apiSuccess('Onboarding skipped', ['next_path' => '/student/dashboard']);
        }

        $data = $request->validate([
            'exam_type_id' => ['required', 'integer', Rule::exists('exam_types', 'id')->where('is_active', 1)],
            'not_sure' => 'sometimes|boolean',
            'exam_month' => ['nullable', 'required_unless:not_sure,true,1', 'date_format:Y-m',
                'after_or_equal:' . now()->format('Y-m'), 'before_or_equal:' . now()->addYears(3)->format('Y-m')],
        ], [
            'exam_month.required_unless' => 'Pick your exam month, or choose "Not sure yet".',
        ]);

        $changes = ['onboarded_at' => now()];
        if ((int) $data['exam_type_id'] !== (int) $student->exam_type_id) {
            abort_if($this->hasActivePlan($student->id), 422, 'Your active plan is for your current exam, so it cannot be changed here. Contact us if you need to switch.');
            $changes['exam_type_id'] = (int) $data['exam_type_id'];
        }
        // Month precision: the 1st, so countdowns err early rather than late.
        $changes['target_exam_date'] = empty($data['exam_month']) || $request->boolean('not_sure')
            ? null
            : Carbon::createFromFormat('Y-m-d', $data['exam_month'] . '-01')->toDateString();

        $student->forceFill($changes)->save();
        app(EventTracker::class)->track($student->id, 'onboarding_completed', [
            'exam_type_id' => (int) ($changes['exam_type_id'] ?? $student->exam_type_id),
            'has_exam_date' => $changes['target_exam_date'] !== null,
        ]);

        return Response::apiSuccess('Onboarding saved', [
            'next_path' => self::NEXT_PATH,
            'target_exam_date' => $changes['target_exam_date'],
        ]);
    }

    public static function needsOnboarding(object $student): bool
    {
        return $student->onboarded_at === null && $student->target_exam_date === null;
    }

    private function hasActivePlan(int $studentId): bool
    {
        return DB::table('subscribers')->where('student_profile_id', $studentId)
            ->where('payment_status', 'PAYMENT_SUCCESS')->where('status', 1)
            ->whereDate('end_date', '>=', today())->exists();
    }
}
