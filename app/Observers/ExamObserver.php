<?php

namespace App\Observers;

use App\Models\{Exam, StudentProfile};
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\RegistrationToken;
use Illuminate\Support\Facades\DB;

class ExamObserver
{
    /**
     * Handle the Exam "created" event.
     */
    public function created(Exam $exam): void
    {
        $factory = (new Factory())->withServiceAccount(
            base_path('exams-nepal-661f4-firebase-adminsdk-fbsvc-7bb2520f4b.json')
        );

        $messaging = $factory->createMessaging();

        $students = DB::table('student_profiles')
            ->select('fcm_token', 'id')
            ->where('exam_type_id', $exam->exam_type_id)
            ->whereNotNull('fcm_token')
            ->get();

        $fcmTokens = $students->pluck('fcm_token', 'id')->all();

        $successCount = 0;
        $failureCount = 0;
        $errors = [];

        if (!empty($fcmTokens)) {
            $title = 'New Exam Added';
            $body = "New exam *".$exam->exam_name ."* has been added";

            $notification = Notification::create($title, $body);

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData([
                    'type' => 'notification',
                ]);

            try {
                $response = $messaging->sendMulticast($message, array_map(
                    fn($token) => RegistrationToken::fromValue($token),
                    $fcmTokens
                ));

                $successCount = $response->successes()->count();
                $failureCount = $response->failures()->count();

                $successfulTokens = [];
                $_successes = iterator_to_array($response->successes()->getItems());
                
                foreach ($_successes as $success) {
                    $token = trim($success->target()->value());
                    $successfulTokens[] = $token;
                }
                // DB::select('user')


                $result = [];
                foreach ($fcmTokens as $id => $token) {
                    // Log::info([in_array(($token), $successfulTokens),$token == $successfulTokens, $token, $successfulTokens]);
                    $temp = [];
                    if (in_array(($token), $successfulTokens)) {
                        $temp['model_type'] = StudentProfile::class;
                        $temp['model_id'] = $id;
                        $temp['data'] = json_encode(compact('title','body'));
                        $temp['notified_at'] = now();
                        $result[] = $temp;
                    }
                }
                Log::info($result);
                DB::table('notifications')->insert($result);
                // $result = array_filter($fcmTokens, function ($fcm) use ($successfulTokens) {
                //     return in_array(trim($fcm), $successfulTokens, true);
                // });
                // Log::info('Success token:', collect($fcmTokens)->where('fcm_token', $successfulTokens)->all());
                // $result;

                foreach ($response->failures() as $failure) {
                    $errors[] = $failure;
                    Log::error("FCM error: " . $failure->error()->getMessage());
                }
            } catch (\Exception $e) {
                $failureCount = count($fcmTokens);
                $errors[] = $e->getMessage();
                Log::error("Failed to send FCM notifications: " . $e->getMessage());
            }
        } else {
            Log::info('No valid FCM tokens found.');
        }

        Log::info('Notification summary', [
            'successes' => $successCount,
            'failures' => $failureCount,
            'errors' => $errors,
            'date' => now()->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Handle the Exam "updated" event.
     */
    public function updated(Exam $exam): void
    {
        //
    }

    /**
     * Handle the Exam "deleted" event.
     */
    public function deleted(Exam $exam): void
    {
        //
    }

    /**
     * Handle the Exam "restored" event.
     */
    public function restored(Exam $exam): void
    {
        //
    }

    /**
     * Handle the Exam "force deleted" event.
     */
    public function forceDeleted(Exam $exam): void
    {
        //
    }
}
