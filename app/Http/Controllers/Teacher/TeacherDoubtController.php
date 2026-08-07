<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\NotificationTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Teacher\TeacherDoubtResource;
use App\Models\Doubt;
use App\Services\FCMService;
use App\Traits\PaginatorTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Throwable;

class TeacherDoubtController extends Controller
{
    use PaginatorTrait;

    /**
     * List doubts raised on questions belonging to the logged-in teacher's own exams.
     */
    /**
     * @OA\Get(
     *     path="/teacher/doubts",
     *     summary="List doubts for the teacher's own exams",
     *     description="Fetches student doubts raised on questions belonging to exams created by the logged-in teacher. Unlike the admin listing, this is scoped to only that teacher's exams.",
     *     operationId="teacher_doubts_list",
     *     tags={"TeacherDoubt"},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Parameter(name="search", in="query", required=false, description="filter by student name or doubt text", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="1=unresolved, 0=resolved", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Doubt list")
     * )
     */
    public function index(Request $request)
    {
        $teacher = Auth::guard('users')->user();
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $status = $request->input('status');

        $doubts = Doubt::whereHas('question.exam', fn ($q) => $q->where('user_id', $teacher->id))
            ->has('student')
            ->with([
                'question:id,exam_id,question,explanation',
                'question.options',
                'student:id,name',
                'question.exam',
            ])
            ->when($search, fn ($q) => $q->where('doubt', 'like', "%{$search}%")
                ->orWhereHas('student', fn ($sq) => $sq->where('name', 'like', "%{$search}%")))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->orderBy('id', 'DESC')
            ->paginate($perPage);

        $data = $this->setupPagination($doubts, fn ($items) => TeacherDoubtResource::collection($items))->data;

        return Response::apiSuccess('doubt list', $data);
    }

    /**
     * Resolve a doubt by updating the underlying question/options and leaving a remark.
     */
    /**
     * @OA\Post(
     *     path="/teacher/doubts/{doubt}/resolve",
     *     summary="Resolve a doubt",
     *     description="Updates the question/explanation/options the doubt was raised on and marks the doubt resolved with an optional remark shown to the student. Only the exam's owner (or an admin) may resolve it.",
     *     operationId="teacher_doubt_resolve",
     *     tags={"TeacherDoubt"},
     *     @OA\Parameter(name="doubt", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Doubt resolved")
     * )
     */
    public function resolve(Doubt $doubt, Request $request)
    {
        $this->isDoubtOwner($doubt);

        $formData = $request->validate([
            'question' => 'required',
            'explanation' => 'required',
            'option_a_id' => 'required|exists:option_questions,id',
            'option_b_id' => 'required|exists:option_questions,id',
            'option_c_id' => 'required|exists:option_questions,id',
            'option_d_id' => 'required|exists:option_questions,id',
            'option_a' => 'required|max:255',
            'option_b' => 'required|max:255',
            'option_c' => 'required|max:255',
            'option_d' => 'required|max:255',
            'option_a_is_true' => 'required|boolean',
            'option_b_is_true' => 'required|boolean',
            'option_c_is_true' => 'required|boolean',
            'option_d_is_true' => 'required|boolean',
            'remark' => 'nullable|string|max:250',
        ]);

        $question = $doubt->question;
        $submittedOptionIdsDontMatch = $question->options
            ->pluck('id')
            ->diff($request->only(['option_a_id', 'option_b_id', 'option_c_id', 'option_d_id']))
            ->isNotEmpty();

        if ($submittedOptionIdsDontMatch) {
            return Response::apiError('The selected option does not belong to this question.');
        }

        DB::transaction(function () use ($doubt, $question, $formData) {
            $question->update([
                'question' => $formData['question'],
                'explanation' => $formData['explanation'],
            ]);

            $options = [
                ['option_id' => $formData['option_a_id'], 'option' => $formData['option_a'], 'value' => $formData['option_a_is_true']],
                ['option_id' => $formData['option_b_id'], 'option' => $formData['option_b'], 'value' => $formData['option_b_is_true']],
                ['option_id' => $formData['option_c_id'], 'option' => $formData['option_c'], 'value' => $formData['option_c_is_true']],
                ['option_id' => $formData['option_d_id'], 'option' => $formData['option_d'], 'value' => $formData['option_d_is_true']],
            ];
            foreach ($options as $option) {
                $question->options()->where('id', $option['option_id'])
                    ->update(['option' => $option['option'], 'value' => $option['value']]);
            }

            $doubt->update([
                'status' => 0,
                'remark' => $formData['remark'] ?? null,
                'user_id' => Auth::guard('users')->id(),
            ]);

            if ($doubt->student && $doubt->student->fcm_token) {
                try {
                    $fcmService = new FCMService(
                        'Doubt Resolved',
                        'Your doubt for question ID ' . $doubt->question_id . ' has been resolved.',
                        NotificationTypeEnum::DOUBT_RESOLVED->value,
                        [$doubt->student->id]
                    );
                    $fcmService->notify([$doubt->student->fcm_token]);
                } catch (Throwable $e) {
                    Log::error('Failed to send doubt-resolved FCM notification: ' . $e->getMessage(), [
                        'doubt_id' => $doubt->id,
                    ]);
                }
            }
        });

        return Response::apiSuccess('Doubt resolved successfully.');
    }

    private function isDoubtOwner(Doubt $doubt)
    {
        $examOwnerId = $doubt->question->exam->user_id ?? null;
        throw_if(
            !Auth::user()->isAdmin() && (int) $examOwnerId !== Auth::guard('users')->id(),
            AuthorizationException::class,
            'You are not the owner'
        );
    }
}
