<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class EsewaController extends Controller
{
    /**
     * @OA\Post(
     *     path="/esewa/save-transaction",
     *     operationId="esewaSaveTransaction",
     *     tags={"Esewa"},
     *     summary="Saves student transactions",
     *     description="Saves student transactions.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"student_id","productId","productName","totalAmount","environment","code","merchantName","message","date","status","refId"},
     *             @OA\Property(property="studentId", type="integer", example=12127),
     *             @OA\Property(property="productId", type="integer", example=1),
     *             @OA\Property(property="productName", type="string", example="some name"),
     *             @OA\Property(property="totalAmount", type="string", example="100.50"),
     *             @OA\Property(property="environment", type="string", example="test"),
     *             @OA\Property(property="code", type="string", example="00"),
     *             @OA\Property(property="merchantName", type="string", example="EPAYTEST"),
     *             @OA\Property(property="message", type="string", example="1"),
     *             @OA\Property(property="date", type="string", example="Fri May 30 12:46:57 NPT 2025"),
     *             @OA\Property(property="status", type="string", example="COMPLETED"),
     *             @OA\Property(property="refId", type="string", example="000ASZ5")
     *         )
     *     ),
@OA\Response(
     *         response=200,
     *         description="Transaction saved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="student_profile_id", type="integer", example=12127),
     *                 @OA\Property(property="subscription_type_id", type="integer", example=1),
     *                 @OA\Property(property="transaction_id", type="string", example="000ASZ5"),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2025-05-29T18:15:00.000000Z"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2025-06-29T18:15:00.000000Z"),
     *                 @OA\Property(property="price", type="string", example="110.00"),
     *                 @OA\Property(property="paid", type="string", example="100.50"),
     *                 @OA\Property(property="subscribed_at", type="string", example="2025-05-30 17:03:20"),
     *                 @OA\Property(
     *                     property="data",
     *                     type="object",
     *                     @OA\Property(property="studentId", type="integer", example=12127),
     *                     @OA\Property(property="productId", type="integer", example=1),
     *                     @OA\Property(property="productName", type="string", example="some name"),
     *                     @OA\Property(property="totalAmount", type="string", example="100.50"),
     *                     @OA\Property(property="environment", type="string", example="test"),
     *                     @OA\Property(property="code", type="string", example="00"),
     *                     @OA\Property(property="merchantName", type="string", example="EPAYTEST"),
     *                     @OA\Property(property="message", type="string", example="1"),
     *                     @OA\Property(property="date", type="string", example="Fri May 30 12:46:57 NPT 2025"),
     *                     @OA\Property(property="status", type="string", example="COMPLETED"),
     *                     @OA\Property(property="refId", type="string", example="000ASZ5")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Transaction saved")
     *         )
     *     )
     * )
     */
    public function storeTransaction(Request $request){
        $data = $request->all();
        Log::info($data);
        $subscription_types = DB::table('subscription_types')->where('id', $request->productId)->first();
        $transaction = [
            'student_profile_id' => $request->studentId,
            'subscription_type_id' => $request->productId,
            'transaction_id' => $request->refId,
            'start_date' => today(),
            'end_date' => today()->addMonths($subscription_types->duration),
            'price' => $subscription_types->price,
            'paid' => $request->totalAmount,
            'subscribed_at' => now()->format('Y-m-d H:i:s')
        ];
        try {
            $transaction['data'] = json_encode($data);
            DB::transaction(fn() => DB::table('subscribers')->insert($transaction));
        } catch (\Exception $e) {
            dd($e->getMessage());
            Log::error($e->getMessage());
            Log::info($transaction);
            return Response::apiError('Error while saving transaction',500);
        }
        $transaction['data'] = $data;
        return Response::apiSuccess('Transaction saved', $transaction);
    }
}
