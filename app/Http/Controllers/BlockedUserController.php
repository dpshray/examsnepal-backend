<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Models\UserBlocked;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class BlockedUserController extends Controller
{
    //
    /**
     * @OA\Get(
     *     path="/user/blocked",
     *     summary="Get all blocked users",
     *     security={{"bearerAuth": {}}},
     *     tags={"Blocked User"},
     *     @OA\Response(
     *         response=200,
     *         description="Blocked users fetched successfully"
     *     )
     * )
     */
    function getBlockedUsers()
    {
        $user = Auth::id();

        $blockedUsers = UserBlocked::with(['student', 'blocker'])
            ->where('student_id', $user)
            ->latest()
            ->get();

        $data = $blockedUsers->map(function ($blockedUser) {
            return [
                'id' => $blockedUser->id,
                'blocked_id' => $blockedUser->blocked_id,
                'student_name' => StudentProfile::find($blockedUser->student_id)->name,
                'blocked_name' => StudentProfile::find($blockedUser->blocked_id)->name,
            ];
        });

        return Response::apiSuccess(
            'Blocked users fetched successfully',
            $data
        );
    }
    /**
     * @OA\Post(
     *     path="/user/block",
     *     summary="Block a user",
     *     security={{"bearerAuth": {}}},
     *     tags={"Blocked User"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"blocked_id"},
     *             @OA\Property(
     *                 property="blocked_id",
     *                 type="integer",
     *                 example=2
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User blocked successfully"
     *     )
     * )
     */
    function blockUser(Request $request)
    {
        $user = Auth::id();
        $exists = UserBlocked::where('student_id', $user)->where('blocked_id', $request->blocked_id)->first();
        if ($exists) {
            return Response::apiError('User already blocked', $exists);
        }
        $blockedUser = UserBlocked::create([
            'student_id' => $user,
            'blocked_id' => $request->blocked_id,
        ]);
        return Response::apiSuccess('User blocked successfully', $blockedUser);
    }
    /**
     * @OA\Delete(
     *     path="/user/unblock/{id}",
     *     summary="Unblock a user",
     *     security={{"bearerAuth": {}}},
     *     tags={"Blocked User"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user to unblock",
     *         @OA\Schema(
     *             type="integer",
     *             example=2
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User unblocked successfully"
     *     )
     * )
     */
    function unblockUser($id)
    {
        $user = Auth::id();
        $blockedUser = UserBlocked::where('student_id', $user)->where('blocked_id', $id)->first();
        if (!$blockedUser) {
            return Response::apiError('You have not blocked this user', $blockedUser);
        }
        $blockedUser->delete();
        return Response::apiSuccess('User unblocked successfully', $blockedUser);
    }
    /**
     * @OA\Get(
     *     path="/admin/blocked-users",
     *     summary="Get all blocked users",
     *     security={{"bearerAuth": {}}},
     *     tags={"Admin Blocked User"},
     *     @OA\Response(
     *         response=200,
     *         description="Blocked users fetched successfully"
     *     )
     * )
     */
    function adminallblockeduser()
    {
        $blockedUsers = UserBlocked::with(['student', 'blocker'])->latest()->get();
        $data = $blockedUsers->map(function ($blockedUser) {
            return [
                'id' => $blockedUser->id,
                'blocker' => StudentProfile::find($blockedUser->student_id),
                'blocked' => StudentProfile::find($blockedUser->blocked_id),
            ];
        });

        return Response::apiSuccess(
            'Blocked users fetched successfully',
            $data
        );
    }
}
