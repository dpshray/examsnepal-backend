<?php

namespace App\Services;

use App\Enums\PaymentStatusEnum;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class ConnectIPSService
{
    private $merchant_id, $app_id, $basic_auth_password, $app_name, $url, $key_file_password, $validation_url = null;

    public function __construct()
    {
        $this->merchant_id = config('services.connectips.merchant_id');
        $this->app_id = config('services.connectips.app_id');
        $this->app_name = config('services.connectips.app_name');
        $this->url = config('services.connectips.url');
        $this->validation_url = config('services.connectips.validation_url');
        $this->basic_auth_password = config('services.connectips.basic_auth_password');
        $this->key_file_password = config('services.connectips.keyfile_password');
    }

    public function initiateTransaction(array $data){
        // dd($data);
        // $pfxPath = storage_path('certs/CREDITOR.key'); #TEST
        $pfxPath = storage_path('certs/live/DWORKIT_LIVE.key'); #LIVE
        if (!file_exists($pfxPath)) {
            throw new \Exception("PFX file not found at: $pfxPath");
        }

        $pfxPassword = $this->key_file_password;
        $privateKey = openssl_pkey_get_private(file_get_contents($pfxPath), $pfxPassword);
        // $pfxPath = storage_path('certs/CREDITOR.pfx');

        // $transactionID = 'TXN' . date('YmdHis') . rand(1000, 9999);
        $transactionID = 'TXN' . rand(10000, 99999);
        $transactionDateCarbon = now(); 
        $transactionDate = $transactionDateCarbon->format('d-m-Y');
        $REF_ID = uniqid('REF-');
        $price = $data['price'];
        $paid = $data['paid'];
        $paid_in_paisa = $paid * 100;
        $REMARKS = uniqid('RMKS-');
        $PARTICULAR = uniqid('PART-');
        $currency = 'NPR';

        $dataString = "MERCHANTID={$this->merchant_id},APPID={$this->app_id},APPNAME={$this->app_name},TXNID={$transactionID},TXNDATE={$transactionDate},TXNCRNCY={$currency},TXNAMT={$paid_in_paisa},REFERENCEID={$REF_ID},REMARKS={$REMARKS},PARTICULARS={$PARTICULAR},TOKEN=TOKEN";
        $signature = '';
        openssl_sign($dataString, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $token = base64_encode($signature);

        // Subscriber::
        $student = Auth::user();
        $subscribe = $student->subscribed;
        // $price = $data['price'];
        // $paid = $data['paid'];

        $start_date = $transactionDateCarbon; #DEFAULT
        $end_date = $transactionDateCarbon->copy()->addMonths($data['month']); #DEFAULT
        if ($subscribe) { #if has previous active subscribe
            $start_date = $subscribe->start_date; 
            $end_date = $subscribe->end_date->copy()->addMonths($data['month']);
            // $price = $data['price'] + $subscribe->price;
            // $paid = $data['paid'] + $subscribe->paid;
            // dd([$data['paid'] , $subscribe->paid]);
        }
        // dd([
        //     'subscription_type_id' => $data['subscription_type_id'],
        //     'transaction_id' => $transactionID,
        //     'promo_code_id' => $data['promo_code_id'],
        //     'start_date' => $start_date,
        //     'end_date' => $end_date,
        //     'price' => $price,
        //     'paid' => $paid,
        //     'subscribed_at' => now()->format('Y-m-d H:i:s'),
        //     'data' => json_encode($response), #XTRA
        //     'status' => 0,
        //     'payment_status' => PaymentStatusEnum::PAYMENT_INIT->value,
        //     'student_profile_id' => Auth::id()
        // ]);

        $response = [
            'transaction_id' => $transactionID,
            'transaction_date' => $transactionDate,
            'ref_id' => $REF_ID,
            'remarks' => $REMARKS,
            'particular' => $PARTICULAR,
            'price' => $paid_in_paisa,
            'currency' => $currency,
            'token' => $token,
        ];

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
                'data' => json_encode($response), #XTRA
                'status' => 0,
                'payment_status' => PaymentStatusEnum::PAYMENT_INIT->value,
                'student_profile_id' => Auth::id()
            ]
        );

        $response['merchant_id'] = $this->merchant_id;
        $response['app_id'] = $this->app_id;
        $response['app_name'] = $this->app_name;

        return $response;

        $postData = [
            'MERCHANTID' => $this->merchant_id,
            'APPID' => $this->app_id,
            'APPNAME' => $this->app_name,
            'TXNID' => $transactionID,
            'TXNDATE' => $transactionDate,
            'TXNCRNCY' => $currency,
            'TXNAMT' => $paid_in_paisa,
            'REFERENCEID' => $REF_ID,
            'REMARKS' => $REMARKS,
            'PARTICULARS' => $PARTICULAR,
            'TOKEN' => $token
        ];
        // dd($postData);

        $ch = curl_init($this->validation_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
        // dd($privateKey);
    }

    public function transactionSuccessfull(array $data){

        $transaction_id = $data['transaction_id'];
        $transaction = Subscriber::firstWhere('transaction_id', $transaction_id);
        
        if (empty($transaction)) {
            throw new \Exception("invalid transaction ID");
        }
        $metadata = $transaction->data;
        
        $pfxPath = storage_path('certs/CREDITOR.key');
        if (!file_exists($pfxPath)) {
            throw new \Exception("PFX file not found at: $pfxPath");
        }

        $pfxPassword = 123;
        $privateKey = openssl_pkey_get_private(file_get_contents($pfxPath), $pfxPassword);

        $merchantId = $this->merchant_id;
        $appId      = $this->app_id;
        $txnAmt     = $transaction->paid_in_paisa;
        $REF_ID = $transaction_id;
        $username   = $appId;  // Basic Auth username
        $password   = $this->basic_auth_password; // Basic Auth password

        // === Build message string ===
        $message = "MERCHANTID={$merchantId},APPID={$appId},REFERENCEID={$REF_ID},TXNAMT={$txnAmt}";

        // === Sign the message with SHA256/RSA ===
        $signature = '';
        openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $token = base64_encode($signature);

        // === Prepare JSON body ===
        $body = json_encode([
            "merchantId"  => $merchantId,
            "appId"       => $appId,
            "referenceId" => $transaction_id,
            "txnAmt"      => $txnAmt,
            "token"       => $token
        ]);

        // === Setup Basic Auth ===
        $authHeader = "Authorization: Basic " . base64_encode("{$username}:{$password}");

        // === Send POST request ===
        $validation_url = $this->validation_url;
        $ch = curl_init($validation_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            $authHeader
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // === Handle Response ===
        $transaction->update(['payment_status' => PaymentStatusEnum::PAYMENT_PENDING->value]);
        Log::info($response);
        $result = json_decode($response, true);
        if ($httpCode == 200) {
            if (isset($result['status']) && $result['status'] === 'SUCCESS') {
                // Log::info($transaction);
                Log::channel('payment')->debug("transaction info", [$transaction]);

                $transaction->update(['status' => 1, 'payment_status' => PaymentStatusEnum::PAYMENT_SUCCESS->value]);
                return json_decode($response);
            } else {
                Log::channel('payment')->debug("transaction error", [$result]);
                $transaction->update(['payment_status' => PaymentStatusEnum::PAYMENT_ERROR->value]);
                return json_decode($response);
                // echo "Payment failed: " . ($result['statusDesc'] ?? 'Unknown error') . "\n";
            }
        } else {
            $transaction->update(['payment_status' => PaymentStatusEnum::PAYMENT_ERROR->value]);
            Log::channel('payment')->debug("Validation API error: {$httpCode}", [$result]);
            return "Validation API error: {$httpCode}\n";
        }
    }
}