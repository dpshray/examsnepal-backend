<?php

namespace App\Http\Controllers\Admin\Notification;

use App\Enums\NotificationTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Notification\AdminNotificationListResource;
use App\Models\ExamType;
use App\Models\StudentNotification;
use App\Services\FCMService;
use App\Traits\NewPaginationTrait;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Http\ResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class AdminNotificationController extends Controller
{
    use NewPaginationTrait, ResponseTrait;
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     security={{"bearerAuth":{}}},
     *     path="/admin/students-notifications",
     *     summary="Get all student notifications(ADMIN)",
     *     description="Get all student notifications(ADMIN)",
     *     operationId="AdminStudentNotifications",
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
    public function index()
    {
        $pagination = StudentNotification::with(['exam:id,exam_name,exam_type_id,status'])->orderBy('id','DESC')->paginate();
        $data = $this->makePaginationResponse($pagination, fn($item) => AdminNotificationListResource::collection($item))->data;
        return Response::apiSuccess('list of notifications send by admin', $data);
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/admin/students-notifications/{id}",
     *     summary="Delete a studet notification(ADMIN)",
     *     description="Delete a studet notification(ADMIN)",
     *     operationId="AdminStudentNotificationDelete",
     *     tags={"Notification"},
     *     security={{"bearerAuth":{}}},
     * 
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of a notification to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     * 
     *     @OA\Response(
     *         response=200,
     *         description="Bank deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Bank deleted successfully")
     *         )
     *     )
     * )
     */
    public function destroy($student_notification_id)
    {
        StudentNotification::findOrFail($student_notification_id)->delete();
        return Response::apiSuccess('Notification successfully deleted');
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
        $FCMObject = (new FCMService(
            $form_data['title'],
            $form_data['body'],
            NotificationTypeEnum::BULK_NOTIFICATION->value,
            $students->pluck('id')->toArray()
        ));
        ['successes' => $successCount, 'failures' => $failureCount] = $FCMObject->notify($students->pluck('fcm_token')->toArray(), $send_and_store);
        return Response::apiSuccess('Notification send with ' . $successCount . ' success and ' . $failureCount . ' failure');
    }
}
