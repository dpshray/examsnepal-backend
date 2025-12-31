<?php

namespace App\Http\Controllers\Corporate\Participant\Exam\Result;

use App\Http\Controllers\Controller;
use App\Http\Resources\Corporate\Exam\Result\CorporateExamResultResource;
use App\Http\Resources\Corporate\Exam\Result\StudentSectionWishDetailResource;
use App\Models\Corporate\CorporateExam;
use App\Models\Corporate\CorporateExamSection;
use App\Models\Corporate\ExamResultToken;
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

        // Get the results array and add tokens
        $results = collect($rankedResults['results'])->map(function ($result) use ($exam) {
            // Get or create token for this participant
            $resultToken = ExamResultToken::getOrCreateToken(
                $exam->id,
                $result['participant_id'],
                $result['email']
            );

            // Add token to result
            $result['result_token'] = is_string($resultToken) ? $resultToken : $resultToken->token;
            return $result;
        });

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
                        'result_token' => $result['result_token'], // Include token
                    ];
                });
            },
            [
                'exam' => [
                    'id' => $rankedResults['exam']['id'],
                    'title' => $rankedResults['exam']['title'],
                    'slug' => $exam->slug,
                    'exam_date' => $rankedResults['exam']['exam_date'],
                    'total_participants' => $rankedResults['exam']['total_participants'],
                ],
                'section_total_marks' => $rankedResults['section_total_marks'],
                'statistics' => $rankedResults['statistics'],
            ]
        );

        return Response::apiSuccess('Exam result list', $response->data);
    }
    function studentExamResultDetail(CorporateExam $exam, $result_token)
    {
        $teacher = Auth::user();
        if ($exam->corporate_id !== $teacher->id) {
            return Response::apiError('Unauthorized access to this exam');
        }
        $participantInfo = ExamResultToken::getParticipantFromToken($exam->id, $result_token);

        if (!$participantInfo) {
            return Response::apiError('Invalid result token');
        }

        // Get all evaluated attempts for this student
        $query = ExamAttempt::where('corporate_exam_id', $exam->id)
            ->where('status', 'evaluated')
            ->with(['section']);

        // Filter by participant (same participant, all sections)
        if ($participantInfo['participant_id']) {
            $query->where('participant_id', $participantInfo['participant_id']);
        } else {
            $query->where('email', $participantInfo['email']);
        }

        $attempts = $query->get();

        if ($attempts->isEmpty()) {
            return Response::apiError('No evaluated results found for this student');
        }
        $studentInfo = [
            'name' => $attempts->first()->name,
            'email' => $attempts->first()->email,
            'phone' => $attempts->first()->phone ?? 'N/A',
            'participant_id' => $attempts->first()->participant_id,
        ];

        // Calculate section-wise results
        $sectionResults = [];
        $totalMarks = 0;
        $totalObtained = 0;

        foreach ($attempts as $attempt) {
            $section = $attempt->section;
            $sectionTotalMarks = $section->questions()->sum('full_marks');
            $percentage = $sectionTotalMarks > 0
                ? round(($attempt->obtained_mark / $sectionTotalMarks) * 100, 2)
                : 0;

            $sectionResults[] = [
                'section_id' => $section->id,
                'section_title' => $section->title,
                'section_slug' => $section->slug,
                'attempt_id' => $attempt->id,
                'obtained_marks' => (float) $attempt->obtained_mark,
                'total_marks' => (float) $sectionTotalMarks,
                'percentage' => $percentage,
                'total_questions' => $section->questions()->count(),
                'submitted_at' => $attempt->submitted_at,
                // 'time_taken' => $this->calculateTimeTaken($attempt),
            ];

            $totalObtained += $attempt->obtained_mark;
            $totalMarks += $sectionTotalMarks;
        }

        // Overall result
        $overallPercentage = $totalMarks > 0
            ? round(($totalObtained / $totalMarks) * 100, 2)
            : 0;

        return Response::apiSuccess('Student result overview', [
            'student' => $studentInfo,
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'exam_date' => $exam->exam_date?->format('Y-m-d'),
            ],
            'overall' => [
                'total_marks' => $totalMarks,
                'obtained_marks' => $totalObtained,
                'percentage' => $overallPercentage,
                // 'grade' => $this->calculateGrade($overallPercentage),
            ],
            'sections' => $sectionResults,
        ]);
    }
    function studentSectionWiseDetail(Request $request, CorporateExam $exam, $result_token, CorporateExamSection $section)
    {
        $teacher = Auth::user();
        if ($exam->corporate_id !== $teacher->id) {
            return Response::apiError('Unauthorized access to this exam');
        }
        if ($section->exam->corporate_id !== $teacher->id) {
            return Response::apiError('Unauthorized access to this exam');
        }
        $participantInfo = ExamResultToken::getParticipantFromToken($exam->id, $result_token);

        if (!$participantInfo) {
            return Response::apiError('Invalid result token');
        }
        $query = ExamAttempt::where('corporate_exam_id', $exam->id)
            ->where('corporate_exam_section_id', $section->id)
            ->where('status', 'evaluated')
            ->with([
                'studentAnswers.question.options',
                'studentAnswers.option'
            ]);

        // Filter by participant
        if ($participantInfo['participant_id']) {
            $query->where('participant_id', $participantInfo['participant_id']);
        } else {
            $query->where('email', $participantInfo['email']);
        }
        $attempt = $query->first();

        if (!$attempt) {
            return Response::apiError('No result found for this section');
        }
        $questionsData = collect($attempt->studentAnswers)->map(function ($answer, $index) {
            return [
                'question_number' => $index + 1,
                'question' => $answer->question,
                'answer' => $answer,
            ];
        });

        // Calculate statistics
        $statistics = $this->calculateStatistics($questionsData);

        // Paginate questions
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $paginatedQuestions = new LengthAwarePaginator(
            $questionsData->forPage($page, $perPage)->values(),
            $questionsData->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );


        $response = $this->setupPagination(
            $paginatedQuestions,
            function ($items) {
                return collect($items)->map(function ($item) {
                    $question = $item['question'];
                    $answer = $item['answer'];
                    $isMcq = in_array($question->question_type, ['mcq', 'objective']);

                    $questionData = [
                        'question_number' => $item['question_number'],
                        'question_id' => $question->id,
                        'question' => $question->question,
                        'description' => $question->description,
                        'question_type' => $question->question_type,
                        'full_marks' => (float) $question->full_marks,
                        'marks_obtained' => (float) ($answer->marks_obtained ?? 0),
                        'is_negative_marking' => (bool) $question->is_negative_marking,
                        'negative_mark' => (float) ($question->negative_mark ?? 0),
                    ];

                    if ($isMcq) {
                        $questionData['options'] = $question->options->map(function ($option) use ($answer) {
                            return [
                                'id' => $option->id,
                                'option' => $option->option,
                                'value' => (bool) $option->value,
                                'is_selected' => $answer->options_id == $option->id,
                            ];
                        })->values();

                        $selectedOption = $answer->option; // This relationship should work if defined
                        $correctOption = $question->options->where('value', true)->first();

                        $questionData['student_answer'] = [
                            'option_id' => $answer->options_id, // ✅ Fixed: was $answer->option_id
                            'selected_option_text' => $selectedOption ? $selectedOption->option : null,
                        ];

                        $questionData['correct_answer'] = $correctOption ? [
                            'option_id' => $correctOption->id,
                            'option_text' => $correctOption->option,
                        ] : null;

                        $questionData['is_correct'] = $answer->options_id == $correctOption?->id;
                    } else {
                        $questionData['subjective_answer'] = $answer->subjective_answer;
                        $questionData['correct_answer'] = null;
                        $questionData['is_correct'] = null;
                    }

                    return $questionData;
                });
            },
            [
                'statistics' => $statistics,
            ]
        );
        return Response::apiSuccess('Section detail with questions', $response->data);
    }
    private function calculateStatistics($questionsData)
    {
        $totalQuestions = $questionsData->count();

        // Calculate correct/wrong only for MCQ questions
        $mcqQuestions = $questionsData->filter(function ($item) {
            return $item['question']->question_type === 'mcq';
        });

        $correctAnswers = $mcqQuestions->filter(function ($item) {
            $answer = $item['answer'];
            $correctOption = $item['question']->options->where('value', true)->first();
            return $answer->options_id == $correctOption?->id;
        })->count();

        $wrongAnswers = $mcqQuestions->count() - $correctAnswers;

        $subjectiveQuestions = $questionsData->filter(function ($item) {
            return $item['question']->question_type === 'subjective';
        })->count();

        return [
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'wrong_answers' => $wrongAnswers,
            'subjective_questions' => $subjectiveQuestions,
        ];
    }
}
