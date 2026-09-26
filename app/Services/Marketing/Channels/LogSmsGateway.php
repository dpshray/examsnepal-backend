<?php

namespace App\Services\Marketing\Channels;

use Illuminate\Support\Facades\Log;

/** Default until a real gateway is configured: writes the SMS to the log. */
class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $text): void
    {
        Log::info('SMS (log driver, not sent)', ['to' => $phone, 'text' => $text]);
    }
}
