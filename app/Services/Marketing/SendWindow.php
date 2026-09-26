<?php

namespace App\Services\Marketing;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Non-transactional mail goes out only inside the configured Asia/Kathmandu
 * windows (config marketing.send_windows); anything else waits for the next.
 */
class SendWindow
{
    public static function isOpen(CarbonInterface $at): bool
    {
        $local = CarbonImmutable::instance($at)->setTimezone(config('marketing.timezone'));
        $hm = $local->format('H:i');
        foreach (config('marketing.send_windows') as [$start, $end]) {
            if ($hm >= $start && $hm < $end) {
                return true;
            }
        }
        return false;
    }

    /** The given time if a window is open, else the start of the next window. */
    public static function next(CarbonInterface $at): CarbonImmutable
    {
        $at = CarbonImmutable::instance($at);
        if (self::isOpen($at)) {
            return $at;
        }
        $local = $at->setTimezone(config('marketing.timezone'));
        for ($day = 0; $day <= 1; $day++) {
            foreach (config('marketing.send_windows') as [$start]) {
                [$h, $m] = array_map('intval', explode(':', $start));
                $candidate = $local->addDays($day)->setTime($h, $m);
                if ($candidate->gt($local)) {
                    return $candidate->setTimezone($at->getTimezone());
                }
            }
        }
        return $at; // unreachable with at least one window
    }
}
