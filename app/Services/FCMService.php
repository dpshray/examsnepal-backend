<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\RegistrationToken;

class FCMService
{
    private $factory;
    public function __construct(public string $title, public string $body)
    {
        $this->factory = (new Factory())->withServiceAccount(
            base_path('exams-nepal-661f4-firebase-adminsdk-fbsvc-7bb2520f4b.json')
        );
    }

    public function notify(array $fcm_token)
    {
        $messaging = $this->factory->createMessaging();

        $successCount = 0;
        $failureCount = 0;
        $errors = [];
        $successfulTokens = [];

        if (!empty($fcm_token)) {
            $title = $this->title;
            $body = $this->body;
            // ['title' => $title, 'body' => $body] = $form_data;
            // dd([$title, $body]);
            // Create Firebase Notification object
            $notification = Notification::create($title, $body);

            // Create message base
            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData(['type' => 'notification']);

            // Split tokens into chunks of 100
            $chunks = array_chunk($fcm_token, 100, true);

            foreach ($chunks as $chunk) {
                try {
                    $tokens = array_map(
                        fn($token) => RegistrationToken::fromValue($token),
                        $chunk
                    );

                    $response = $messaging->sendMulticast($message, $tokens);

                    // Count successes & failures
                    $successCount += $response->successes()->count();
                    $failureCount += $response->failures()->count();

                    // Handle successful sends
                    foreach ($response->successes()->getItems() as $success) {
                        $token = trim($success->target()->value());
                        $successfulTokens[] = $token;
                    }

                    // Handle failed sends
                    foreach ($response->failures()->getItems() as $failure) {
                        $errorMsg = $failure->error()->getMessage();
                        $errors[] = $errorMsg;
                        Log::error("FCM error: {$errorMsg}");
                    }

                    // Free memory after each batch
                    unset($tokens, $response);
                    gc_collect_cycles();
                } catch (\Exception $e) {
                    $failureCount += count($chunk);
                    $errors[] = $e->getMessage();
                    Log::error("Failed to send FCM batch: " . $e->getMessage());
                }
            }
        } else {
            Log::info('No valid FCM tokens found.');
        }
        $result = [
            'successes' => $successCount,
            'failures' => $failureCount,
            'errors' => $errors,
            'date' => now()->format('Y-m-d H:i:s')
        ];
        Log::info('Notification summary', $result);
        return [
            'successes' => $successCount,
            'failures' => $failureCount,
            'errors' => $errors,
            'date' => now()->format('Y-m-d H:i:s')
        ];
    }
}
