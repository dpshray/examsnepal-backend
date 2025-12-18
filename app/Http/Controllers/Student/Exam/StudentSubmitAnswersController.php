<?php

namespace App\Http\Controllers\Student\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\Exam\ExamAnswer\StudentAnswerRequest;
use App\Models\Corporate\CorporateQuestion;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentSubmitAnswersController extends Controller
{
    //
    /**
     * Submit or update answers for an exam attempt.
     *
     * @OA\Post(
     *     path="/submit-answer/{attempt_id}",
     *     summary="Submit answers for an exam attempt",
     *     tags={"Corporate Student Exam Answer"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="attempt_id",
     *         in="path",
     *         required=true,
     *         description="Exam attempt ID",
     *         @OA\Schema(type="integer", example=101)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Answers payload",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="question_id", type="integer", example=55),
     *                 @OA\Property(property="option_id", type="integer", nullable=true, example=3),
     *                 @OA\Property(property="subjective_answer", type="string", nullable=true, example="Polymorphism allows multiple forms")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Answer saved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="your Answer have been save")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized access to this attempt"
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Exam attempt not found or already submitted"
     *     )
     * )
     */

    function Submit_Answer(StudentAnswerRequest $request, $attempt_id)
    {
        $attempt = ExamAttempt::where('id', $attempt_id)
            ->where('status', 'started')
            ->first();

        if (!$attempt) {
            return Response::apiError('Exam attempt not found or already submitted');
        }
        // Verify ownership
        $token = JWTAuth::parseToken()->getPayload();
        $exam = $attempt->exam;

        if ($exam->exam_type === 'public') {
            if ($attempt->email !== $token->get('email')) {
                return Response::apiError('Unauthorized access to this attempt');
            }
        } else {
            $participant = Auth::guard('participant')->user();
            if (!$participant || $attempt->participant_id !== $participant->id) {
                return Response::apiError('Unauthorized access to this attempt');
            }
        }
        $datas = $request->validated()['answer'];
        DB::beginTransaction();
        try {
            foreach ($datas as $data) {
                $question = CorporateQuestion::find($data['question_id']);

                // Check if question belongs to this section
                if ($question->corporate_exam_section_id != $attempt->corporate_exam_section_id) {
                    return Response::apiError('Question does not belong to this section');
                }
                $answer = StudentAnswer::updateOrCreate(
                    [
                        'exam_attempts_id' => $attempt_id,
                        'question_id' => $data['question_id'],
                    ],
                    [
                        'options_id' => $data['option_id'],
                        'subjective_answer' => $data['subjective_answer'],
                    ]
                );
            }
            DB::commit();
            return Response::apiSuccess('your Answer have been save');
        } catch (Exception $e) {
            DB::rollBack();
            Log::info($e->getMessage());
            return Response::apiError('Failed to save answer');
        }
    }
    /**
     * Submit an exam attempt for evaluation.
     *
     * @OA\Post(
     *     path="/submit-exam/{attempt_id}",
     *     summary="Submit exam attempt",
     *     tags={"Corporate Student Exam Answer"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="attempt_id",
     *         in="path",
     *         required=true,
     *         description="Exam attempt ID",
     *         @OA\Schema(type="integer", example=101)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Exam submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Thank you for Submission")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized access to this attempt"
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Exam attempt not found or already submitted"
     *     )
     * )
     */

    function submit_exam(Request $request, $attempt_id)
    {
        $attempt = ExamAttempt::where('id', $attempt_id)
            ->where('status', 'started')
            ->with(['studentAnswers.question', 'studentAnswers.option'])
            ->first();
        if (!$attempt) {
            return Response::apiError('Exam attempt not found or already submitted');
        }
        // Verify ownership
        $token = JWTAuth::parseToken()->getPayload();
        $exam = $attempt->exam;

        if ($exam->exam_type === 'public') {
            if ($attempt->email !== $token->get('email')) {
                return Response::apiError('Unauthorized access to this attempt');
            }
        } else {
            $participant = Auth::guard('participant')->user();
            if (!$participant || $attempt->participant_id !== $participant->id) {
                return Response::apiError('Unauthorized access to this attempt');
            }
        }
        DB::beginTransaction();
        try {
            $totalObtainedMarks = 0;
            // Calculate marks for objective questions
            foreach ($attempt->studentAnswers as $answer) {
                $question = $answer->question;

                if ($question->question_type === 'mcq') {
                    if ($answer->option && $answer->option->value == 1) {
                        // Correct answer
                        $marksObtained = $question->full_marks;
                    } else {
                        // Wrong answer
                        if ($question->is_negative_marking) {
                            $marksObtained = -$question->negative_mark;
                        } else {
                            $marksObtained = 0;
                        }
                    }

                    $answer->marks_obtained = $marksObtained;
                    $answer->save();
                    $totalObtainedMarks += $marksObtained;
                }
            }

            //update attempt
            $attempt->submitted_at = Carbon::now();
            $attempt->obtained_mark = $totalObtainedMarks;
            // Check if there are subjective questions
            $hasSubjective = $attempt->studentAnswers()
                ->whereHas('question', function ($query) {
                    $query->where('question_type', 'subjective');
                })
                ->exists();
            if ($hasSubjective) {
                $attempt->status = 'evaluating';
            } else {
                $attempt->status = 'evaluated';
            }
            $attempt->save();
            DB::commit();
            return Response::apiSuccess('Thank you for Submission');
        } catch (Exception $e) {
            DB::rollBack();
            Log::info("submit exam error", $e->getMessage());
            return Response::apiError('Failed to submit exam');
        }
    }
}
