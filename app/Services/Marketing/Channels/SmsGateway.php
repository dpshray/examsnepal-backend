<?php

namespace App\Services\Marketing\Channels;

interface SmsGateway
{
    /** @throws \RuntimeException when the gateway rejects the message */
    public function send(string $phone, string $text): void;
}
