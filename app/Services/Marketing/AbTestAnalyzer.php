<?php

namespace App\Services\Marketing;

use App\Models\Marketing\Automation;
use App\Models\Marketing\MessageSend;
use Illuminate\Support\Facades\DB;

/**
 * A/B result for an automation, judged on goal conversion (not opens).
 * A variant wins when both arms have enough sends and a two-proportion
 * z-test is significant at 95%.
 */
class AbTestAnalyzer
{
    public const MIN_SENDS_PER_VARIANT = 100;
    public const Z_95 = 1.96;

    /**
     * @return array{variants: array<string, array{template_key:string, sent:int, goal_met:int, goal_rate:?float}>,
     *   winner:?string, confidence:?string, needs_more_data:bool}
     */
    public function analyze(Automation $automation): array
    {
        $rows = DB::table('message_sends')
            ->where('automation_id', $automation->id)
            ->whereIn('status', MessageSend::DELIVERED_STATUSES)
            ->groupBy('variant')
            ->get(['variant', DB::raw('COUNT(*) as sent'), DB::raw('SUM(CASE WHEN goal_met_at IS NOT NULL THEN 1 ELSE 0 END) as goal_met')])
            ->keyBy('variant');

        $variants = [];
        foreach (['A', 'B'] as $v) {
            $sent = (int) ($rows[$v]->sent ?? 0);
            $goal = (int) ($rows[$v]->goal_met ?? 0);
            $variants[$v] = [
                'template_key' => $automation->templateKeyFor($v),
                'sent' => $sent,
                'goal_met' => $goal,
                'goal_rate' => $sent ? round($goal / $sent * 100, 1) : null,
            ];
        }

        $result = self::compare($variants['A']['sent'], $variants['A']['goal_met'], $variants['B']['sent'], $variants['B']['goal_met']);

        return ['variants' => $variants] + $result;
    }

    /** @return array{winner:?string, confidence:?string, needs_more_data:bool, z:?float} */
    public static function compare(int $sentA, int $goalA, int $sentB, int $goalB): array
    {
        if ($sentA < self::MIN_SENDS_PER_VARIANT || $sentB < self::MIN_SENDS_PER_VARIANT) {
            return ['winner' => null, 'confidence' => null, 'needs_more_data' => true, 'z' => null];
        }
        $pA = $goalA / $sentA;
        $pB = $goalB / $sentB;
        $pooled = ($goalA + $goalB) / ($sentA + $sentB);
        $se = sqrt($pooled * (1 - $pooled) * (1 / $sentA + 1 / $sentB));
        $z = $se > 0 ? ($pA - $pB) / $se : 0.0;

        return [
            'winner' => abs($z) >= self::Z_95 ? ($z > 0 ? 'A' : 'B') : null,
            'confidence' => abs($z) >= self::Z_95 ? '95%' : null,
            'needs_more_data' => false,
            'z' => round($z, 2),
        ];
    }
}
