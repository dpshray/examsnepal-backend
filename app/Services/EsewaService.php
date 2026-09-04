<?php

namespace App\Services;

use App\Enums\PaymentStatusEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EsewaService
{
    private string $merchant_code;
    private string $secret_key;
    private string $payment_url;
    private string $verify_url;

    public function __construct()
    {
        $this->merchant_code = config('esewa.merchant_code');
        $this->secret_key = config('esewa.secret_key');
        $this->payment_url = config('esewa.payment_url');
        $this->verify_url = config('esewa.verify_url');
    }
    public function initiateTransaction(array $data)
    {
        $transactionID = 'TXN' . rand(10000, 99999);
        $transactionDateCarbon = now();
        // $transactionDate = $transactionDateCarbon->format('d-m-Y');
        // $REF_ID = uniqid('REF-');
        $price = $data['price'];
        $paid = $data['paid'];
        $paid_in_paisa = $paid * 100;
        // $REMARKS = uniqid('RMKS-');
        // $PARTICULAR = uniqid('PART-');
        // $currency = 'NPR';
        $payload = [
            'amount' => (int) $paid,
            'tax_amount' => 0,
            'total_amount' => (int) $paid,
            'transaction_uuid' => $transactionID,
            'product_code' => $this->merchant_code,
            'product_service_charge' => 0,
            'product_delivery_charge' => 0,
            'success_url' => route('api.esewa.success'),
            'failure_url' => route('api.esewa.failure'),
        ];
        $student = Auth::user();
        $subscribe = $student->subscribed;
        // $price = $data['price'];
        // $paid = $data['paid'];

        $start_date = $transactionDateCarbon; #DEFAULT
        $end_date = $transactionDateCarbon->copy()->addMonths($data['month']); #DEFAULT
        if ($subscribe) { #if has previous active subscribe
            $start_date = $subscribe->start_date;
            $end_date = $subscribe->end_date->copy()->addMonths($data['month']);
        }
        DB::table('subscribers')->insert(
            [
                'subscription_type_id' => $data['subscription_type_id'],
                'transaction_id' => $transactionID,
                'promo_code_id' => $data['promo_code_id'],
                'start_date' => $start_date,
                'end_date' => $end_date,
                'price' => $price,
                'paid' => $paid,
                'paid_in_paisa' => $paid_in_paisa,
                'subscribed_at' => now()->format('Y-m-d H:i:s'),
                'data' => json_encode($payload), #XTRA
                'status' => 0,
                'payment_status' => PaymentStatusEnum::PAYMENT_INIT->value,
                'student_profile_id' => Auth::id()
            ]
        );
        $signedFieldNames = 'total_amount,transaction_uuid,product_code';
        // Build signature string
        $signatureString =
            "total_amount={$payload['total_amount']}," .
            "transaction_uuid={$payload['transaction_uuid']}," .
            "product_code={$payload['product_code']}";

        // Generate signature
        $signature = base64_encode(
            hash_hmac(
                'sha256',
                $signatureString,
                $this->secret_key,
                true
            )
        );

        return [
            'payment_url' => $this->payment_url,
            'transaction_uuid' => $transactionID,
            'payload' => array_merge($payload, [
                'signed_field_names' => $signedFieldNames,
                'signature' => $signature,
            ]),
        ];
    }
}
