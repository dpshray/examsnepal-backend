<?php

namespace App\Services\Marketing;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes behavioural events to the `events` table. Tracking is a side effect:
 * it must never break the request that triggered it, so failures are logged
 * and swallowed.
 */
class EventTracker
{
    public const SIGNED_UP = 'signed_up';
    public const LOGGED_IN = 'logged_in';
    public const EXAM_STARTED = 'exam_started';
    public const EXAM_SUBMITTED = 'exam_submitted';
    public const EXAM_ABANDONED = 'exam_abandoned';
    public const PRICING_VIEWED = 'pricing_viewed';
    public const CHECKOUT_STARTED = 'checkout_started';
    public const PAYMENT_SUCCEEDED = 'payment_succeeded';
    public const PAYMENT_FAILED = 'payment_failed';
    public const SUBSCRIPTION_EXPIRED = 'subscription_expired';
    public const EMAIL_CLICKED = 'email_clicked';

    /** Events that mean the student was actually using the product. */
    private const ACTIVITY_EVENTS = [self::SIGNED_UP, self::LOGGED_IN, self::EXAM_STARTED, self::EXAM_SUBMITTED];

    public function track(
        int $studentId,
        string $name,
        array $properties = [],
        ?string $platform = null,
        ?CarbonInterface $at = null,
    ): void {
        $at ??= now();
        $platform ??= app()->runningInConsole() ? null : Platform::fromRequest();

        try {
            DB::table('events')->insert([
                'student_id' => $studentId,
                'name' => $name,
                'properties' => $properties ? json_encode($properties) : null,
                'platform' => $platform,
                'created_at' => $at,
            ]);

            if (in_array($name, self::ACTIVITY_EVENTS, true)) {
                DB::table('student_profiles')->where('id', $studentId)->update(array_filter([
                    'last_active_at' => $at,
                    'last_platform' => $platform,
                ]));
            }
        } catch (\Throwable $e) {
            Log::error("Marketing event tracking failed ({$name})", ['student_id' => $studentId, 'error' => $e->getMessage()]);
            return;
        }

        try {
            (new AutomationEngine())->onEvent($studentId, $name, $properties, $at);
        } catch (\Throwable $e) {
            Log::error("Marketing automation trigger failed ({$name})", ['student_id' => $studentId, 'error' => $e->getMessage()]);
        }
    }

    /** True if the student already has this event, optionally since a time / matching a property. */
    public function has(int $studentId, string $name, ?CarbonInterface $since = null, ?array $propertyMatch = null): bool
    {
        return DB::table('events')
            ->where('student_id', $studentId)
            ->where('name', $name)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($propertyMatch, function ($q) use ($propertyMatch) {
                foreach ($propertyMatch as $key => $value) {
                    is_array($value) ? $q->whereIn("properties->{$key}", $value) : $q->where("properties->{$key}", $value);
                }
            })
            ->exists();
    }
}
