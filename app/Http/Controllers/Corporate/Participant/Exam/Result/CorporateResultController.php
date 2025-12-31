<?php

namespace App\Http\Controllers\Corporate\Participant\Exam\Result;

use App\Http\Controllers\Controller;
use App\Http\Resources\Corporate\Exam\Result\CorporateExamResultResource;
use App\Models\Corporate\CorporateExam;
use App\Models\ExamAttempt;
use App\Services\CorporateExamResultService;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class CorporateResultController extends Controller
{
    //
    use PaginatorTrait;
    function ExamResultList(Request $request, CorporateExam $exam)
    {
        $teacher = Auth::user();
        if ($exam->corporate_id !== $teacher->id) {
            return Response::apiError('Unauthorized access to this exam');
        }

        // Get all evaluated attempts
        $attempts = ExamAttempt::where('corporate_exam_id', $exam->id)
            ->where('status', 'evaluated')
            ->with(['section'])
            ->get();

        if ($attempts->isEmpty()) {
            return Response::apiError('No evaluated results found for this exam');
        }

        // Process results
        $resultProcessor = new CorporateExamResultService($exam, $attempts);
        $rankedResults = $resultProcessor->getRankedResults();

        // Get the results array
        $results = collect($rankedResults['results']);

        // Apply search filter
        $search = $request->input('search');
        if ($search) {
            $results = $results->filter(function ($result) use ($search) {
                return stripos($result['name'], $search) !== false ||
                    stripos($result['email'], $search) !== false;
            });
        }

        // Paginate results
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $paginatedResults = new LengthAwarePaginator(
            $results->forPage($page, $perPage)->values(),
            $results->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Setup pagination with resource
        $response = $this->setupPagination(
            $paginatedResults,
            function ($items) {
                return collect($items)->map(function ($result) {
                    return [
                        'rank' => $result['rank'],
                        'participant_id' => $result['participant_id'],
                        'name' => $result['name'],
                        'email' => $result['email'],
                        'phone' => $result['phone'],
                        'section_wise_marks' => $result['section_wise_marks'],
                        'total_marks' => $result['total_marks'],
                    ];
                });
            },
            [
                'exam' => [
                    'id' => $rankedResults['exam']['id'],
                    'title' => $rankedResults['exam']['title'],
                    'exam_date' => $rankedResults['exam']['exam_date'],
                    'total_participants' => $rankedResults['exam']['total_participants'],
                ],
                'section_total_marks' => $rankedResults['section_total_marks'],
                'statistics' => $rankedResults['statistics'],
            ]
        );

        return Response::apiSuccess('Exam result list', $response->data);
    }
}
