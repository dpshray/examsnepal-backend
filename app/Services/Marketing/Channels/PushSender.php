<?php

namespace App\Services\Marketing\Channels;

/** Sends one push notification to one device token. Swappable in tests. */
interface PushSender
{
    /** @throws \RuntimeException when the push could not be sent */
    public function send(int $studentId, string $token, string $title, string $body, array $data): void;
}
