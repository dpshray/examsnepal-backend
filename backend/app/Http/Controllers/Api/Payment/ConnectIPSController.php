<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionType;
use App\Services\ConnectIPSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class ConnectIPSController extends Controller
{
    /**
     * @OA\Get(
     *     path="/connectips/init-transaction/{subscription_type}",
     *     summary="Get logged in student question solved doubts",
     *     tags={"ConnectIPS"},
     *     operationId="init_transaction",
     *     @OA\Parameter(
     *         name="subscription_type",
     *         in="path",
     *         required=true,
     *         description="Subscription type ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="merchant_id", type="string", example="1122"),
     *                 @OA\Property(property="app_id", type="string", example="AME-3541-API-2"),
     *                 @OA\Property(property="app_name", type="string", example="TESTER Sol"),
     *                 @OA\Property(property="transaction_id", type="string", example="TXN202508181158374473"),
     *                 @OA\Property(property="transaction_date", type="string", example="2025-08-18"),
     *                 @OA\Property(property="ref_id", type="string", example="REF-68a2c4914c0f1"),
     *                 @OA\Property(property="remarks", type="string", example="RMKS-68a2c4914c0f4"),
     *                 @OA\Property(property="particular", type="string", example="PART-68a2c4914c0f5"),
     *                 @OA\Property(property="price", type="string", example="101.00"),
     *                 @OA\Property(property="currency", type="string", example="NPR"),
     *                 @OA\Property(
     *                     property="token",
     *                     type="string",
     *                     example="vHsFl9jS95G3BnQak1hd203AJs1H2ZWdqhP5W1wJYeVIv67VNg4VieqP8d4aZlKZ93gwf04qBF1lClsenNKRujlRmwj0Xgmh9UlcDR6ri2kufbVeBcslnQnlQCPhQydI7dR+6FYgdzOml6KGxL+iAekRlpvOUeWv5f14n9f6Jj8="
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="transaction generated")
     *         )
     *     )
     *  )
     */
    public function beginTransaction(Request $request, SubscriptionType $subscription_type){
        $price = $subscription_type->price;
        $data = app(ConnectIPSService::class)->initiateTransaction([
            'price' => $price,
            'subscription_type_id' => $subscription_type->id,
            'month' => $subscription_type->duration
        ]);
        return Response::apiSuccess('transaction generated', $data);
    }


    /**
     * @OA\Get(
     *     path="/connectips/transaction-successfull/{transaction_id}",
     *     summary="Get logged in student question solved doubts",
     *     tags={"ConnectIPS"},
     *     operationId="validate_transaction",
     *     @OA\Parameter(
     *         name="transaction_id",
     *         in="path",
     *         required=true,
     *         description="Transaction ID",
     *         @OA\Schema(type="string", example="TXN39595")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction validation response",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="merchantId", type="integer", example=2288),
     *                 @OA\Property(property="appId", type="string", example="MER-3155-API-5"),
     *                 @OA\Property(property="referenceId", type="string", example="TXN39595"),
     *                 @OA\Property(property="txnAmt", type="string", example="100100"),
     *                 @OA\Property(property="token", type="string", nullable=true, example=null),
     *                 @OA\Property(property="status", type="string", example="SUCCESS"),
     *                 @OA\Property(property="statusDesc", type="string", example="TRANSACTION SUCCESSFUL")
     *             ),
     *             @OA\Property(property="message", type="string", example="transaction validation response")
     *         )
     *     )
     *  )
     */
    public function successPayment(Request $request, $transaction_id){
        Log::info('inside successPayment');
        Log::channel('payment')->debug('HERE', ["inside successfull payment"]);
        try {
            $data = app(ConnectIPSService::class)->transactionSuccessfull([
                'transaction_id' => $transaction_id
            ]);
            return Response::apiSuccess('transaction validation response', $data);
        } catch (\Exception $e) {
            return Response::apiError($e->getMessage());
        }
    }

    // public function transactionStore(Request $request){
    //    // {"merchantId":3166,"appId":"MER-3166-APP-1","referenceId":"TXN39595","txnAmt":"100100","token":null,"status":"SUCCESS","statusDesc":"TRANSACTION SUCCESSFUL"}  
    // }
}
