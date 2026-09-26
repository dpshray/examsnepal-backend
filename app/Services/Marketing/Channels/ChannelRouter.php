<?php

namespace App\Services\Marketing\Channels;

use Illuminate\Support\Facades\DB;

/**
 * Picks the channel for one message. "auto" follows the fallback rule:
 * push if the app is installed with a token -> email -> SMS (only when the
 * automation allows SMS, i.e. high-value moments).
 */
class ChannelRouter
{
    public const EMAIL = 'email';
    public const PUSH = 'push';
    public const SMS = 'sms';
    public const AUTO = 'auto';

    /**
     * @return array{0:?string, 1:?string} [chosen channel, reason when none is possible]
     */
    public static function pick(string $channel, bool $allowSms, object $student, ?array $suppressed = null): array
    {
        $candidates = match ($channel) {
            self::AUTO => array_merge([self::PUSH, self::EMAIL], $allowSms ? [self::SMS] : []),
            default => [$channel],
        };

        $firstReason = null;
        foreach ($candidates as $candidate) {
            $reason = self::blocker($candidate, $student, $suppressed);
            if ($reason === null) {
                return [$candidate, null];
            }
            $firstReason ??= $reason;
        }
        return [null, $channel === self::AUTO ? 'no_reachable_channel' : $firstReason];
    }

    /**
     * Why this channel can't reach the student (null = it can).
     *
     * @param ?array<string,true> $suppressed preloaded suppressed addresses (bulk mode); null = query
     */
    public static function blocker(string $channel, object $student, ?array $suppressed = null): ?string
    {
        return match ($channel) {
            self::EMAIL => self::emailBlocker($student, $suppressed),
            self::PUSH => !config('marketing.push_enabled') ? 'push_disabled' : (empty($student->fcm_token) ? 'no_push_token' : null),
            self::SMS => self::nepaliMobile($student->phone ?? null) ? null : 'no_phone',
            default => 'unknown_channel',
        };
    }

    private static function emailBlocker(object $student, ?array $suppressed): ?string
    {
        $email = strtolower(trim((string) ($student->email ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'no_email';
        }
        if ($suppressed !== null ? isset($suppressed[$email]) : DB::table('suppressions')->where('email', $email)->exists()) {
            return 'suppressed_address';
        }
        return null;
    }

    /** "+977-9841234567" / "9779841234567" / "9841234567" -> "9841234567"; null if not a Nepali mobile. */
    public static function nepaliMobile(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) === 13 && str_starts_with($digits, '977')) {
            $digits = substr($digits, 3);
        }
        return preg_match('/^9[678]\d{8}$/', $digits) ? $digits : null;
    }
}
