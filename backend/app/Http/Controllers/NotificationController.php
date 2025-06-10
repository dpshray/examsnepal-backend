<?php

namespace App\Http\Controllers;

use App\Http\Resources\StudentExamNotificationCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Models\StudentProfile;

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
        $notifications = DB::table('notifications')
                            ->select('data','notified_at')
                            ->where('model_type', StudentProfile::class)
                            ->where('model_id', $student->id)
                            ->get();
        $notifications = new StudentExamNotificationCollection($notifications);
        return Response::apiSuccess('User '. $student->name.' notifications', $notifications);
    }
}