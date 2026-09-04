<?php

namespace App\Http\Controllers\Api\Payment\Esewa;

use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\SubscriptionType;
use App\Services\EsewaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class EsewaController extends Controller
{
    //
    /**
     * @OA\Post(
     *     path="/esewa/init-transaction",
     *     summary="Get logged in student question solved doubts",
     *     tags={"Esewa"},
     *     operationId="transaction_begin_esewa",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"subscription_type_id"},
     *             @OA\Property(property="subscription_type_id", type="integer", example="127181"),
     *             @OA\Property(property="promo_code", type="string", example="DWORK2025"),
     *         )
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
    public function beginTransaction(Request $request)
    {
        // return $request->all();
        $request->validate([
            'subscription_type_id' => 'required|exists:subscription_types,id'
        ]);
        $subscription_type_id = $request->subscription_type_id;
        // return $subscription_type_id;
        $subscription_type = SubscriptionType::find($subscription_type_id);
        if (empty($subscription_type)) {
            return Response::apiError('Invalid subscription type', 404);
        }
        $paid = $price = $subscription_type->price;
        $promo_code_id = null;

        $promo_code = $request->promo_code;
        if ($promo_code) {
            $promo_code_row = PromoCode::select('id', 'code', 'discount_percent', 'detail')
                ->where('status', 1)
                ->firstWhere('code', $promo_code);

            // $promo_code_data = PromoCode::firstWhere('code', $promo_code);
            if (empty($promo_code_row) || $promo_code !== $promo_code_row->code) {
                return Response::apiError('This promo code does not match/exists', null, 404);
            } else {
                $promo_code_id = $promo_code_row->id;
                $discount_percent = $promo_code_row->discount_percent;
                $paid = $price - (($price * $discount_percent) / 100);
            }
        }
        $data = app(EsewaService::class)->initiateTransaction([
            'price' => $price,
            'paid' => $paid,
            'subscription_type_id' => $subscription_type->id,
            'month' => $subscription_type->duration,
            'promo_code_id' => $promo_code_id
        ]);
        return Response::apiSuccess('transaction generated', $data);
    }
    public function successPayment(Request $request)
    {
        $encoded = $request->query('data');

        if (!$encoded) {
            return $this->redirectToApp('failure', ['reason' => 'missing_data']);
        }

        $decoded = json_decode(base64_decode($encoded), true);

        if (!$decoded || empty($decoded['transaction_uuid'])) {
            return $this->redirectToApp('failure', ['reason' => 'malformed_response']);
        }

        $subscriber = DB::table('subscribers')
            ->where('transaction_id', $decoded['transaction_uuid'])
            ->first();

        if (!$subscriber) {
            Log::warning('eSewa success callback for unknown transaction', $decoded);
            return $this->redirectToApp('failure', [
                'reason' => 'unknown_transaction',
                'transaction_uuid' => $decoded['transaction_uuid'],
            ]);
        }

        // 1. Verify signature on the redirect payload itself
        if (!$this->verifySignature($decoded)) {
            $this->markSubscriber($subscriber->id, PaymentStatusEnum::PAYMENT_FAILED->value, $decoded);
            return $this->redirectToApp('failure', [
                'reason' => 'signature_mismatch',
                'transaction_uuid' => $decoded['transaction_uuid'],
            ]);
        }

        // 2. Confirm server-to-server via eSewa's status API (don't trust the browser redirect alone)
        $status = $this->checkStatus(
            $decoded['product_code'],
            $decoded['transaction_uuid'],
            $decoded['total_amount']
        );

        if (!$status || $status['status'] !== 'COMPLETE') {
            $this->markSubscriber($subscriber->id, PaymentStatusEnum::PAYMENT_FAILED->value, $decoded);
            return $this->redirectToApp('failure', [
                'reason' => 'not_complete',
                'transaction_uuid' => $decoded['transaction_uuid'],
            ]);
        }

        // 3. All good — activate subscription
        $this->markSubscriber($subscriber->id, PaymentStatusEnum::PAYMENT_SUCCESS->value, array_merge($decoded, [
            'ref_id' => $status['ref_id'] ?? null,
        ]), activate: true);

        return $this->redirectToApp('success', [
            'transaction_uuid' => $decoded['transaction_uuid'],
        ]);
    }

    /**
     * eSewa redirects here (GET) after a failed/cancelled payment.
     * Typically only carries transaction_uuid (no signed data).
     */
    public function failurePayment(Request $request)
    {
        $transactionUuid = $request->query('transaction_uuid');

        if ($transactionUuid) {
            $subscriber = DB::table('subscribers')
                ->where('transaction_id', $transactionUuid)
                ->first();

            if ($subscriber) {
                $this->markSubscriber($subscriber->id, PaymentStatusEnum::PAYMENT_FAILED->value, $request->query());
            }
        }

        return $this->redirectToApp('failure', [
            'transaction_uuid' => $transactionUuid,
        ]);
    }

    private function markSubscriber(int $id, string $paymentStatus, array $extra = [], bool $activate = false): void
    {
        DB::table('subscribers')->where('id', $id)->update(array_filter([
            'payment_status' => $paymentStatus,
            'status' => $activate ? 1 : 0,
            'data' => json_encode($extra),
        ]));
    }

    private function verifySignature(array $decoded): bool
    {
        if (empty($decoded['signed_field_names']) || empty($decoded['signature'])) {
            return false;
        }

        $fields = explode(',', $decoded['signed_field_names']);
        $parts = [];
        foreach ($fields as $field) {
            $field = trim($field);
            if ($field === 'signature') {
                continue;
            }
            $parts[] = "{$field}=" . ($decoded[$field] ?? '');
        }
        $signatureString = implode(',', $parts);

        $expected = base64_encode(hash_hmac(
            'sha256',
            $signatureString,
            config('esewa.secret_key'),
            true
        ));

        return hash_equals($expected, $decoded['signature']);
    }

    private function checkStatus(string $productCode, string $transactionUuid, $totalAmount): ?array
    {
        try {
            $response = Http::get(config('esewa.status_check_url'), [
                'product_code' => $productCode,
                'total_amount' => $totalAmount,
                'transaction_uuid' => $transactionUuid,
            ]);

            if (!$response->successful()) {
                Log::error('eSewa status check non-2xx', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('eSewa status check failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Redirect to an https URL that is Android-App-Link / iOS-Universal-Link
     * verified, so it opens the app directly (falls back to a normal web
     * page if the app isn't installed).
     */
    private function redirectToApp(string $result, array $params = [])
    {
        $base = config('esewa.app_redirect_base');

        if (str_ends_with($base, '://')) {
            $url = $base . 'payment/' . $result . '?' . http_build_query($params);
        } else {
            $url = rtrim($base, '/') . '/payment/' . $result . '?' . http_build_query($params);
        }

        return redirect()->away($url);
    }
}
