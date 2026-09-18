<?php

namespace App\Http\Controllers\Institute;

use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Corporate\ClassPayment;
use App\Models\Corporate\Classroom;
use App\Services\ClassEsewaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class ClassEsewaController extends Controller
{
    public function beginTransaction(Request $request, string $slug)
    {
        $student = Auth::guard('institute_student')->user();

        $class = Classroom::where('slug', $slug)
            ->where('institute_id', $student->institute_id)
            ->firstOrFail();

        if ($class->price === null || (float) $class->price <= 0) {
            return Response::apiError('This class is free — use Apply to Join instead.');
        }

        $existing = $class->students()->where('institute_student_id', $student->id)->first();
        if ($existing && $existing->pivot->status === 'enrolled') {
            return Response::apiError('You are already enrolled in this class.');
        }

        $data = app(ClassEsewaService::class)->initiateTransaction($class, $student);

        return Response::apiSuccess('Transaction generated', $data);
    }

    public function successPayment(Request $request)
    {
        $encoded = $request->query('data');

        if (!$encoded) {
            return $this->redirectToCorporate(null, null, ['payment' => 'failed', 'reason' => 'missing_data']);
        }

        $decoded = json_decode(base64_decode($encoded), true);

        if (!$decoded || empty($decoded['transaction_uuid'])) {
            return $this->redirectToCorporate(null, null, ['payment' => 'failed', 'reason' => 'malformed_response']);
        }

        $payment = ClassPayment::where('transaction_uuid', $decoded['transaction_uuid'])->first();

        if (!$payment) {
            Log::warning('eSewa class-payment success callback for unknown transaction', $decoded);
            return $this->redirectToCorporate(null, null, [
                'payment' => 'failed',
                'reason' => 'unknown_transaction',
            ]);
        }

        $class = $payment->classroom;
        $instituteSlug = $class?->institute?->username ?: $class?->institute?->slug;

        // 1. Verify signature on the redirect payload itself
        if (!$this->verifySignature($decoded)) {
            $this->markPayment($payment, PaymentStatusEnum::PAYMENT_FAILED->value, $decoded);
            return $this->redirectToCorporate($instituteSlug, $class?->slug, [
                'payment' => 'failed',
                'reason' => 'signature_mismatch',
            ]);
        }

        // 2. Confirm server-to-server via eSewa's status API (don't trust the browser redirect alone)
        $status = $this->checkStatus(
            $decoded['product_code'],
            $decoded['transaction_uuid'],
            $decoded['total_amount']
        );

        if (!$status || $status['status'] !== 'COMPLETE') {
            $this->markPayment($payment, PaymentStatusEnum::PAYMENT_FAILED->value, $decoded);
            return $this->redirectToCorporate($instituteSlug, $class?->slug, [
                'payment' => 'failed',
                'reason' => 'not_complete',
            ]);
        }

        // 3. All good — mark paid and auto-enroll the student
        $this->markPayment($payment, PaymentStatusEnum::PAYMENT_SUCCESS->value, array_merge($decoded, [
            'ref_id' => $status['ref_id'] ?? null,
        ]));

        if ($class) {
            $class->students()->syncWithoutDetaching([
                $payment->institute_student_id => ['status' => 'enrolled'],
            ]);
        }

        return $this->redirectToCorporate($instituteSlug, $class?->slug, [
            'payment' => 'success',
        ]);
    }

    /**
     * eSewa redirects here (GET) after a failed/cancelled payment.
     * Typically only carries transaction_uuid (no signed data).
     */
    public function failurePayment(Request $request)
    {
        $transactionUuid = $request->query('transaction_uuid');
        $class = null;

        if ($transactionUuid) {
            $payment = ClassPayment::where('transaction_uuid', $transactionUuid)->first();

            if ($payment) {
                $this->markPayment($payment, PaymentStatusEnum::PAYMENT_FAILED->value, $request->query());
                $class = $payment->classroom;
            }
        }

        $instituteSlug = $class?->institute?->username ?: $class?->institute?->slug;

        return $this->redirectToCorporate($instituteSlug, $class?->slug, [
            'payment' => 'failed',
        ]);
    }

    private function markPayment(ClassPayment $payment, string $paymentStatus, array $extra = []): void
    {
        $payment->update([
            'payment_status' => $paymentStatus,
            'data' => $extra,
        ]);
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
                Log::error('eSewa class-payment status check non-2xx', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('eSewa class-payment status check failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function redirectToCorporate(?string $instituteSlug, ?string $classSlug, array $params)
    {
        $base = rtrim(config('esewa.class_redirect_base'), '/');

        if ($instituteSlug) {
            $path = "/institute/{$instituteSlug}/dashboard/classes";
            if ($classSlug) {
                $params['class'] = $classSlug;
            }
        } else {
            $path = '/';
        }

        return redirect()->away($base . $path . '?' . http_build_query($params));
    }
}
