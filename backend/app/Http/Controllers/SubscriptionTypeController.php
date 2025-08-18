<?php

namespace App\Http\Controllers;

use App\Http\Resources\StudentSubscriptionResource;
use App\Http\Resources\Subscription\SubscriptionTypeResource;
use App\Models\SubscriptionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};
use Illuminate\Support\Facades\Response;

class SubscriptionTypeController extends Controller
{
    /**
     * @OA\Get(
     *     security={{"bearerAuth": {}}},
     *     path="/user-subscription-status",
     *     summary="Fetch student subscription",
     *     description="Fetch student subscription",
     *     operationId="SubscriptionStatus",
     *     tags={"Subscription"},
     *     @OA\Response(
     *     response=200,
     *     description="User subscription status response",
     *     @OA\JsonContent(
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(
     *             property="data",
     *             type="object",
     *             @OA\Property(property="price", type="string", example="101.00"),
     *             @OA\Property(property="paid", type="string", example="100.50"),
     *             @OA\Property(property="student_profile_id", type="integer", example=12127),
     *             @OA\Property(property="starts_at", type="string", format="date", example="2025-06-02"),
     *             @OA\Property(property="ends_at", type="string", format="date", example="2026-02-02"),
     *             @OA\Property(property="subscribed_at", type="string", format="date-time", example="2025-06-04 10:55:59"),
     *             @OA\Property(
     *                 property="subscription",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="exam_type_id", type="integer", example=1),
     *                 @OA\Property(property="duration", type="integer", example=1),
     *                 @OA\Property(property="price", type="string", example="101.00"),
     *                 @OA\Property(property="status", type="integer", example=1)
     *             )
     *         ),
     *         @OA\Property(property="message", type="string", example="User subscription status")
     *     )
     * )
     * )
     */
    public function subscribeStat(){
        $subscription = Auth::user()
                        ->subscribed()
                        ->with('subscriptionType')
                        ->select(
                            'price',
                            'paid',
                            'subscribers.student_profile_id',
                            DB::raw("DATE_FORMAT(subscribers.start_date, '%Y-%m-%d') as starts_at"),
                            DB::raw("DATE_FORMAT(subscribers.end_date, '%Y-%m-%d') as ends_at"),
                            'subscribed_at',
                            'subscription_type_id'
                        )
                        ->first();
        
        $data = $subscription ? new StudentSubscriptionResource($subscription) : null;
        return Response::apiSuccess('User subscription status', $data);
    }
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     security={{"bearerAuth": {}}},
     *     path="/subscription-type",
     *     summary="Fetch all subscription list",
     *     description="Fetch all subscription list",
     *     operationId="SubscriptionList",
     *     tags={"Subscription"},
     *     @OA\Response(
     *         response=200,
     *         description="Active subscription package list",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="subscription_type_id", type="integer", example=1),
     *                     @OA\Property(property="duration", type="integer", example=1),
     *                     @OA\Property(property="price", type="string", example="101.00")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Active package list")
     *         )
     *     )
     * )
     */
    public function index()
    {
        $user = Auth::user();
        $rows = SubscriptionType::select('id as subscription_type_id','duration', 'price')
            ->where('status', 1)
            ->where('exam_type_id',$user->exam_type_id)
            ->get();
        $data = SubscriptionTypeResource::collection($rows);
        return Response::apiSuccess('Active package list', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
