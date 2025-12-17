<?php

namespace App\Http\Controllers;

use App\Http\Resources\StudentExamNotificationCollection;
use App\Models\ExamType;
use App\Models\StudentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Models\StudentProfile;
use App\Services\FCMService;

class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     security={{"bearerAuth":{}}},
     *     path="/notification",
     *     summary="Get all student notifications",
     *     description="Get all student notifications",
     *     operationId="StudentNotifications",
     *     tags={"Notification"},
     *
     * @OA\Response(
     *     response=200,
     *     description="User notifications",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(
     *             property="data",
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="title", type="string", example="some title here"),
     *                 @OA\Property(property="body", type="string", example="some body here"),
     *                 @OA\Property(property="notified_at", type="string", format="date-time", example="2025-06-05 16:45:52")
     *             )
     *         ),
     *         @OA\Property(property="message", type="string", example="User anonymous user notifications")
     *     )
     * )
     * )
     */
    public function getUserNotifications(){
        $student = Auth::user();
        $notifications = StudentNotification::where('student_profile_id', $student->id)
            ->orderBy('id', 'desc')
            ->get();
        $notifications = new StudentExamNotificationCollection($notifications);
        return Response::apiSuccess('User '. $student->name.' notifications', $notifications);
    }

    /**
     * @OA\Post(
     *     path="/students/notifications",
     *     summary="Send notification to student(verified) based on ther exam type.",
     *     description="Send notification to student(verified) based on ther exam type.",
     *     operationId="BulkNotification",
     *     tags={"Notification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "body"},
     *             @OA\Property(property="title", type="string", example="This is a title."),
     *             @OA\Property(property="body", type="string", example="This is a description."),
     *             @OA\Property(property="exam_type_id", type="integer", example=0),
     *             @OA\Property(property="send_and_store", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification response data",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="data", type="null", example=null),
     *             @OA\Property(property="message", type="string", example="Notification send with 1 success and 0 failure")
     *         )
     *     )
     * )
     */
    public function sendBulkPushNotification(Request $request)
    {

        $form_data = $request->validate([
            'title' => 'required|max:255',
            'body' => 'required',
            'exam_type_id' => 'required|exists:exam_types,id',
            'send_and_store' => 'sometimes|nullable|boolean'
        ]);
        // return $form_data;
        $is_exam_not_active = ExamType::active()->where('id', $form_data['exam_type_id'])->doesntExist();
        if ($is_exam_not_active) {
            return Response::apiSuccess("This exam type is not active at the moment.");
        }
        $send_and_store = (array_key_exists('send_and_store', $form_data) && filter_var($form_data['send_and_store'], FILTER_VALIDATE_BOOLEAN) == true) ? true : false;

        // Get valid FCM tokens
        // $students = DB::table('student_profiles')->where('email','like', 'rabin@fivermail.com')->get();
        $students = DB::table('student_profiles')
            ->where('exam_type_id', $form_data['exam_type_id'])
            ->whereNotNull('email_verified_at')
            ->whereNotNull('fcm_token')
            ->distinct()
            // ->pluck('fcm_token')
            ->get();
        
        ['successes' => $successCount, 'failures' => $failureCount] = (new FCMService(
            $form_data['title'],
            $form_data['body'],'Notification',
            $students->pluck('id')->toArray()
        ))->notify($students->pluck('fcm_token')->toArray(), $send_and_store);
        return Response::apiSuccess('Notification send with '. $successCount.' success and '. $failureCount.' failure');
    }

}
