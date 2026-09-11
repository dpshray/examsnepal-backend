<?php

namespace App\Services;

use App\Enums\PaymentStatusEnum;
use App\Models\Corporate\ClassPayment;
use App\Models\Corporate\Classroom;
use App\Models\InstituteStudent;

class ClassEsewaService
{
    private string $merchant_code;
    private string $secret_key;
    private string $payment_url;

    public function __construct()
    {
        $this->merchant_code = config('esewa.merchant_code');
        $this->secret_key = config('esewa.secret_key');
        $this->payment_url = config('esewa.payment_url');
    }

    public function initiateTransaction(Classroom $class, InstituteStudent $student): array
    {
        $transactionUuid = 'CLS' . now()->format('YmdHis') . rand(1000, 9999);
        $amount = (float) $class->price;

        $payload = [
            'amount' => number_format($amount, 2, '.', ''),
            'tax_amount' => 0,
            'total_amount' => number_format($amount, 2, '.', ''),
            'transaction_uuid' => $transactionUuid,
            'product_code' => $this->merchant_code,
            'product_service_charge' => 0,
            'product_delivery_charge' => 0,
            'success_url' => route('api.esewa.class.success'),
            'failure_url' => route('api.esewa.class.failure'),
        ];

        ClassPayment::create([
            'class_id' => $class->id,
            'institute_student_id' => $student->id,
            'transaction_uuid' => $transactionUuid,
            'amount' => $amount,
            'payment_status' => PaymentStatusEnum::PAYMENT_INIT->value,
            'data' => $payload,
        ]);

        $signedFieldNames = 'total_amount,transaction_uuid,product_code';
        $signatureString =
            "total_amount={$payload['total_amount']}," .
            "transaction_uuid={$payload['transaction_uuid']}," .
            "product_code={$payload['product_code']}";

        $signature = base64_encode(
            hash_hmac('sha256', $signatureString, $this->secret_key, true)
        );

        return [
            'payment_url' => $this->payment_url,
            'transaction_uuid' => $transactionUuid,
            'payload' => array_merge($payload, [
                'signed_field_names' => $signedFieldNames,
                'signature' => $signature,
            ]),
        ];
    }
}
