<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdminPaymentSettingController extends Controller
{
    //
    /** 
     *  Display a listing of payment settings. 
     *  @OA\Get( 
     *      path="/admin/payment-settings", 
     *      tags={"Admin Payment Settings"}, 
     *      summary="Get payment settings", 
     *      security={{"bearerAuth":{}}}, 
     *      @OA\Response( 
     *          response=200, 
     *          description="Payment settings fetched successfully", 
     *          @OA\JsonContent( 
     *              @OA\Property( 
     *                  property="status", 
     *                  type="boolean", 
     *                  example=true 
     *                  ), 
     *                  @OA\Property( 
     *                  property="message", 
     *                  type="string", * example="payment settings fetched" 
     *                  ), 
     *                  @OA\Property( 
     *                  property="data", 
     *                  type="array", 
     *                      @OA\Items( 
     *                          @OA\Property( 
     *                              property="id", 
     *                              type="integer", 
     *                              example=1 
     *                          ), 
     *                          @OA\Property( 
     *                              property="name", 
     *                              type="string", 
     *                              example="Esewa" 
     *                          ), 
     *                          @OA\Property( 
     *                              property="status", 
     *                              type="boolean", 
     *                              example=true 
     *                          ) 
     *                      ) 
     *                  ) 
     *              ) 
     *          ) 
     *      ) 
     */
    function index()
    {
        $payment_settings = PaymentSetting::all();
        return Response::apiSuccess('payment settings fetched', $payment_settings);
    }
    /**
     * Create or update a payment setting. 
     * @OA\Post(
     *  path="/admin/payment-settings",
     *  tags={"Admin Payment Settings"},
     *  summary="Create or update payment setting",
     *  security={{"bearerAuth":{}}},
     *  @OA\RequestBody(
     *  required=true,
     *  @OA\JsonContent(
     *  required={"name", "status"},
     * @OA\Property(
     *  property="name",
     *  type="string",
     *  example="Esewa"
     * ),
     * @OA\Property(
     *  property="status",
     *  type="boolean",
     *  example=true
     * )
     * )
     * ),
     *  @OA\Response(
     *  response=200,
     *  description="Payment setting created/updated successfully",
     *  @OA\JsonContent(
     *  @OA\Property(
     *  property="status",
     *  type="boolean",
     *  example=true
     * ),
     * @OA\Property(
     *  property="message",
     *  type="string",
     *  example="payment settings updated"
     * ),
     *  @OA\Property(
     *  property="data",
     *  type="object",
     * @OA\Property(
     *  property="id",
     *  type="integer",
     *  example=1
     * ),
     * @OA\Property(
     *  property="name",
     *  type="string",
     *  example="Esewa"
     * ),
     * @OA\Property(
     *  property="status",
     *  type="boolean",
     *  example=true
     * )
     * )
     * )
     * ),
     * @OA\Response(
     *  response=422,
     *  description="Validation error"
     * )
     * ) */
    function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'status' => 'required',
        ]);
        $payment_settings = PaymentSetting::updateOrCreate([
            'name' => $request->name,
        ], [
            'status' => $request->status,
        ]);
        return Response::apiSuccess('payment settings updated');
    }
}
