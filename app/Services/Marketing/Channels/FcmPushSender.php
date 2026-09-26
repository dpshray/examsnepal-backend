<?php

namespace App\Services\Marketing\Channels;

use App\Enums\NotificationTypeEnum;
use App\Services\FCMService;

/**
 * Push through the app's existing FCMService, which also saves the message to
 * the student's in-app notification list.
 */
class FcmPushSender implements PushSender
{
    public function send(int $studentId, string $token, string $title, string $body, array $data): void
    {
        $fcm = new FCMService($title, $body, NotificationTypeEnum::MARKETING->value, [$studentId]);
        $result = $fcm->notify([$token], send_and_save: true);

        if (($result['successes'] ?? 0) < 1) {
            throw new \RuntimeException('FCM: ' . implode('; ', $result['errors'] ?? ['not delivered']));
        }
    }
}
