<?php

namespace App\Services\Marketing;

use Carbon\CarbonInterface;

/**
 * Explainable 0-100 upsell priority. Every rule lives here; the breakdown is
 * stored next to the score so admins can see why a student ranks high.
 */
class LeadScore
{
    /**
     * @param array{
     *   attempts_last_14d:int, sprint_attempts:int, mock_attempts:int,
     *   last_pricing_viewed_at:?CarbonInterface, checkout_abandoned:bool,
     *   target_exam_date:?CarbonInterface, last_activity_at:?CarbonInterface,
     *   signed_up_at:?CarbonInterface
     * } $m
     * @return array{score:int, breakdown:array<string,int>}
     */
    public static function compute(array $m, CarbonInterface $now): array
    {
        $b = [];

        if ($m['attempts_last_14d'] > 0) {
            $b['recent_attempts'] = min(30, 5 * $m['attempts_last_14d']);
        }
        if ($m['sprint_attempts'] > 0) {
            $b['took_sprint'] = 15;
        }
        if ($m['mock_attempts'] > 0) {
            $b['took_mock'] = 20;
        }
        if ($m['last_pricing_viewed_at'] && $m['last_pricing_viewed_at']->gte($now->copy()->subDays(7))) {
            $b['viewed_pricing_7d'] = 15;
        }
        if ($m['checkout_abandoned']) {
            $b['checkout_abandoned'] = 25;
        }
        if ($m['target_exam_date'] && $m['target_exam_date']->between($now->copy()->startOfDay(), $now->copy()->addDays(60))) {
            $b['exam_within_60d'] = 10;
        }

        // Inactive 14+ days. A student who never did anything counts from signup;
        // with neither known (legacy import) they are long inactive.
        $lastSeen = $m['last_activity_at'] ?? $m['signed_up_at'];
        if (!$lastSeen || $lastSeen->lte($now->copy()->subDays(14))) {
            $b['inactive_14d'] = -20;
        }

        return [
            'score' => max(0, min(100, array_sum($b))),
            'breakdown' => $b,
        ];
    }
}
