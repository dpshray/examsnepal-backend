<?php

namespace App\Services;

use App\Models\StudentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\RegistrationToken;

class FCMService
{
    private $factory;
    private $notification_type = null;
    public $exam_id = null;

    public function __construct(
        public string $title, 
        public string $body, 
        public ?string $type = null, 
        public ?array $students = null)
    {
        $this->factory = (new Factory())->withServiceAccount(
            base_path('exams-nepal-661f4-firebase-adminsdk-fbsvc-7bb2520f4b.json')
        );
    }

    public function notify(array $fcm_token, bool $send_and_save = true)
    {
        $messaging = $this->factory->createMessaging();

        $successCount = 0;
        $failureCount = 0;
        $errors = [];
        $successfulTokens = [];

        // Filter invalid tokens (fixes your error)
        $fcm_token = array_filter($fcm_token, function ($token) {
            return !empty($token) && is_string($token);
        });

        $fcm_token = array_values($fcm_token); // reset keys

        if (empty($fcm_token)) {
            Log::info('No valid FCM tokens found.');
            return [
                'successes' => 0,
                'failures' => 0,
                'errors' => ['No valid FCM tokens found'],
                'date' => now()->format('Y-m-d H:i:s')
            ];
        }

        $title = $this->title;
        $body  = $this->body;
        $type  = $this->type ?? 'Default';
        $students = $this->students ?? [];

        // Save notification records
        if ($send_and_save) {  
            $temp = [];
            foreach ($students as $studentId) {
                $temp[] = [
                    'student_profile_id' => $studentId,
                ];
            }
            DB::transaction(fn ()  =>                 
                StudentNotification::create([
                    'title' => $title,
                    'body' => $body,
                    'type' => $type,
                    'exam_id' => $this->exam_id,
                ])->reads()->createMany($temp)
            );
        }

        $notification = Notification::create($title, $body);

        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withData(['type' => $type]);

        //Split into chunks of 100 tokens
        $chunks = array_chunk($fcm_token, 100);

        foreach ($chunks as $chunk) {
            try {
                // Convert each token to RegistrationToken object
                $tokens = array_map(
                    fn($token) => RegistrationToken::fromValue($token),
                    $chunk
                );

                // Send multicast
                $response = $messaging->sendMulticast($message, $tokens);

                // Count success & failure
                $successCount += $response->successes()->count();
                $failureCount += $response->failures()->count();

                // Store successful tokens
                foreach ($response->successes()->getItems() as $success) {
                    $successfulTokens[] = $success->target()->value();
                }

                // Store error messages
                foreach ($response->failures()->getItems() as $failure) {
                    $errors[] = $failure->error()->getMessage();
                    Log::error("FCM error: " . $failure->error()->getMessage());
                }

                unset($tokens, $response);
                gc_collect_cycles();
            } catch (\Exception $e) {
                $failureCount += count($chunk);
                $errors[] = $e->getMessage();
                Log::error("Failed to send FCM batch: " . $e->getMessage());
            }
        }
        $result = [
            'successes' => $successCount,
            'failures' => $failureCount,
            'errors' => $errors,
            'date' => now()->format('Y-m-d H:i:s')
        ];

        Log::info('Notification summary', $result);

        return $result;
    }
}
