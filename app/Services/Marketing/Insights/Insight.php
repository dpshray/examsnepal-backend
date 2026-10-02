<?php

namespace App\Services\Marketing\Insights;

use Carbon\CarbonImmutable;

/**
 * Shared shapes for the web & social insights reports.
 *
 * An insight is one observation with a recommended action. Kinds:
 * win (keep doing it), warning (something got worse or is broken),
 * opportunity (upside we are not taking) and info (context worth knowing).
 */
class Insight
{
    public static function make(string $source, string $kind, string $title, string $detail, ?string $action = null, int $priority = 50): array
    {
        return compact('source', 'kind', 'title', 'detail', 'action', 'priority');
    }

    /** Same shape as the marketing overview KPIs: value, previous, change_pct. */
    public static function kpi(int|float|null $value, int|float|null $previous): array
    {
        return [
            'value' => $value,
            'previous' => $previous,
            'change_pct' => self::changePct($value, $previous),
        ];
    }

    public static function changePct(int|float|null $value, int|float|null $previous): ?float
    {
        return $value !== null && $previous !== null && $previous != 0
            ? round(($value - $previous) / abs($previous) * 100, 1)
            : null;
    }

    /** The equally long period right before [from, to]. */
    public static function previousPeriod(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = $from->diffInDays($to) + 1;

        return [$from->subDays($days), $from->subDay()];
    }

    public static function share(int|float $part, int|float $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }

    public static function pct(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.').'%';
    }

    public static function int(int|float $value): string
    {
        return number_format((float) round($value));
    }

    /** "+12%" / "-8%" for prose. */
    public static function signed(?float $changePct): string
    {
        return $changePct === null ? 'n/a' : ($changePct > 0 ? '+' : '').self::pct($changePct);
    }
}
