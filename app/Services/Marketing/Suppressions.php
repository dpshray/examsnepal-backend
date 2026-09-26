<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\DB;

/** Addresses that must never receive non-requested mail again. */
class Suppressions
{
    public const UNSUBSCRIBED = 'unsubscribed';
    public const HARD_BOUNCE = 'hard_bounce';
    public const COMPLAINT = 'complaint';
    public const MANUAL = 'manual';

    public static function add(string $email, string $reason, ?string $detail = null): void
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }
        DB::table('suppressions')->insertOrIgnore([
            'email' => $email,
            'reason' => $reason,
            'detail' => $detail ? mb_substr($detail, 0, 255) : null,
            'created_at' => now(),
        ]);
    }

    public static function remove(string $email): void
    {
        DB::table('suppressions')->where('email', strtolower(trim($email)))->delete();
    }

    /** Student-level opt-out: profile flag + suppression entry, so every automation and broadcast respects it. */
    public static function unsubscribeStudent(int $studentId, string $detail = 'one-click link'): void
    {
        DB::table('student_profiles')->where('id', $studentId)->update([
            'marketing_email_opt_in' => false,
            'unsubscribed_at' => now(),
        ]);
        $email = DB::table('student_profiles')->where('id', $studentId)->value('email');
        if ($email) {
            self::add($email, self::UNSUBSCRIBED, $detail);
        }
    }

    public static function resubscribeStudent(int $studentId): void
    {
        DB::table('student_profiles')->where('id', $studentId)->update([
            'marketing_email_opt_in' => true,
            'unsubscribed_at' => null,
        ]);
        $email = DB::table('student_profiles')->where('id', $studentId)->value('email');
        // Only lift an unsubscribe; bounces and complaints stay suppressed.
        if ($email) {
            DB::table('suppressions')->where('email', strtolower($email))->where('reason', self::UNSUBSCRIBED)->delete();
        }
    }
}
