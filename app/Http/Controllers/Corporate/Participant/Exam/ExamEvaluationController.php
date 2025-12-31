<?php

namespace App\Http\Controllers\Corporate\Participant\Exam;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\ResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class ExamEvaluationController extends Controller
{
    //
    use ResponseTrait;
    /**
     * @OA\Post(
     *     path="/corporate/exams/evaluate/{attempt}",
     *     summary="Evaluate subjective answers of an exam attempt",
     *     description="Allows a teacher to evaluate subjective answers and assign marks. Objective marks are auto-calculated.",
     *     tags={"Corporate Exam Evaluation"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="attempt",
     *         in="path",
     *         required=true,
     *         description="Exam Attempt ID",
     *         @OA\Schema(type="integer", example=25)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"answers"},
     *             @OA\Property(
     *                 property="answers",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"student_answer_id","marks_obtained"},
     *                     @OA\Property(
     *                         property="student_answer_id",
     *                         type="integer",
     *                         example=101
     *                     ),
     *                     @OA\Property(
     *                         property="marks_obtained",
     *                         type="number",
     *                         format="float",
     *                         example=7.5
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Evaluation completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Evaluation completed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="attempt_id", type="integer", example=25),
     *                 @OA\Property(property="evaluated_count", type="integer", example=3),
     *                 @OA\Property(property="remaining_count", type="integer", example=0),
     *                 @OA\Property(property="status", type="string", example="evaluated"),
     *                 @OA\Property(
     *                     property="marks",
     *                     type="object",
     *                     @OA\Property(property="total_marks", type="number", example=100),
     *                     @OA\Property(property="obtained_marks", type="number", example=82.5),
     *                     @OA\Property(property="percentage", type="number", example=82.5)
     *                 ),
     *                 @OA\Property(
     *                     property="errors",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized access"
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */

    function evaluating(ExamAttempt $attempt, Request $request)
    {
        $teacher = Auth::user();
        if ($attempt->exam->corporate_id !== $teacher->id) {
            return Response::apiError('Unauthorized access to this exam attempt');
        }
        // Check if attempt is in evaluating status
        if ($attempt->status !== 'evaluating' && $attempt->status !== 'submitted') {
            return Response::apiError('This exam cannot be evaluated. Current status: ' . $attempt->status);
        }
        // Validate request
        $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.student_answer_id' => 'required|exists:student_answers,id',
            'answers.*.marks_obtained' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Get all subjective questions that need evaluation
            $subjectiveAnswers = StudentAnswer::where('exam_attempts_id', $attempt->id)
                ->whereHas('question', function ($q) {
                    $q->where('question_type', 'subjective');
                })
                ->with('question')
                ->get();

            // Start with already calculated objective marks
            $totalObtainedMarks = StudentAnswer::where('exam_attempts_id', $attempt->id)
                ->whereHas('question', function ($q) {
                    $q->whereIn('question_type', ['mcq', 'objective']);
                })
                ->sum('marks_obtained') ?? 0;

            $evaluatedCount = 0;
            $errors = [];

            // Evaluate each subjective answer
            foreach ($request->answers as $index => $answerData) {
                $studentAnswer = StudentAnswer::where('id', $answerData['student_answer_id'])
                    ->where('exam_attempts_id', $attempt->id)
                    ->first();

                if (!$studentAnswer) {
                    $errors[] = [
                        'index' => $index,
                        'student_answer_id' => $answerData['student_answer_id'],
                        'error' => 'Answer not found or does not belong to this attempt'
                    ];
                    continue;
                }

                // Check if question is subjective
                if ($studentAnswer->question->question_type !== 'subjective') {
                    $errors[] = [
                        'index' => $index,
                        'student_answer_id' => $answerData['student_answer_id'],
                        'error' => 'This question is not subjective type'
                    ];
                    continue;
                }

                // Validate marks don't exceed full marks
                if ($answerData['marks_obtained'] > $studentAnswer->question->full_marks) {
                    $errors[] = [
                        'index' => $index,
                        'student_answer_id' => $answerData['student_answer_id'],
                        'error' => 'Marks obtained cannot exceed full marks (' . $studentAnswer->question->full_marks . ')'
                    ];
                    continue;
                }

                // Update marks
                $studentAnswer->marks_obtained = $answerData['marks_obtained'];
                $studentAnswer->save();

                $totalObtainedMarks += $answerData['marks_obtained'];
                $evaluatedCount++;
            }

            // Check if all subjective questions are evaluated
            $unevaluatedCount = StudentAnswer::where('exam_attempts_id', $attempt->id)
                ->whereHas('question', function ($q) {
                    $q->where('question_type', 'subjective');
                })
                ->whereNull('marks_obtained')
                ->count();

            // Update attempt
            $attempt->obtained_mark = $totalObtainedMarks;

            if ($unevaluatedCount === 0) {
                $attempt->status = 'evaluated';
            } else {
                $attempt->status = 'evaluating';
            }

            $attempt->save();

            DB::commit();

            $percentage = $attempt->total_mark > 0
                ? round(($totalObtainedMarks / $attempt->total_mark) * 100, 2)
                : 0;

            return Response::apiSuccess('Evaluation completed successfully', [
                'attempt_id' => $attempt->id,
                'evaluated_count' => $evaluatedCount,
                'remaining_count' => $unevaluatedCount,
                'status' => $attempt->status,
                'marks' => [
                    'total_marks' => (float) $attempt->total_mark,
                    'obtained_marks' => (float) $totalObtainedMarks,
                    'percentage' => $percentage
                ],
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::apiError('Failed to evaluate: ' . $e->getMessage());
        }
    }
}
