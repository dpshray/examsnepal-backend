<?php

namespace App\Services\Marketing\Channels;

use Illuminate\Support\Facades\Http;

/** Sparrow SMS (Nepal): https://docs.sparrowsms.com - token + sender identity from config. */
class SparrowSmsGateway implements SmsGateway
{
    public function send(string $phone, string $text): void
    {
        $response = Http::asForm()->timeout(15)->post('https://api.sparrowsms.com/v2/sms/', [
            'token' => config('marketing.sms.sparrow_token'),
            'from' => config('marketing.sms.sparrow_from'),
            'to' => $phone,
            'text' => $text,
        ]);

        if (!$response->successful() || (int) $response->json('response_code') !== 200) {
            throw new \RuntimeException('Sparrow SMS: ' . ($response->json('response') ?? $response->status()));
        }
    }
}
