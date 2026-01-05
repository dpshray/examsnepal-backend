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
     *     summary="Get all student notifications(STUDENT)",
     *     description="Get all student notifications(STUDENT)",
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
        $notifications = Auth::user()
            ->notificationReads()
            ->with(['studentNotification'])
            ->orderBy('id', 'desc')
            ->get();
        $notifications = new StudentExamNotificationCollection($notifications);
        return Response::apiSuccess('User '. $student->name.' notifications', $notifications);
    }
}
