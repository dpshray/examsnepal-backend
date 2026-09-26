<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    public const TRIGGER_EVENT = 'event';
    public const TRIGGER_SCHEDULED = 'scheduled';

    protected $fillable = [
        'key', 'name', 'channel', 'allow_sms', 'trigger_type', 'trigger_event', 'trigger_properties', 'conditions', 'schedule', 'template_key',
        'variant_b_template_key', 'ab_split_pct', 'delay_minutes', 'goal_event', 'goal_properties',
        'cooldown_days', 'priority', 'is_upsell', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'trigger_properties' => 'array',
            'schedule' => 'array',
            'goal_properties' => 'array',
            'is_upsell' => 'boolean',
            'allow_sms' => 'boolean',
            'is_active' => 'boolean',
            'ab_split_pct' => 'integer',
            'delay_minutes' => 'integer',
            'cooldown_days' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function sends(): HasMany
    {
        return $this->hasMany(MessageSend::class);
    }

    /** Event properties satisfy a {key: value|[values]} filter (all keys must match; arrays mean any-of). */
    public static function propertiesMatch(?array $filter, array $properties): bool
    {
        foreach ($filter ?? [] as $key => $expected) {
            $actual = $properties[$key] ?? null;
            if (is_array($expected) ? !in_array($actual, $expected, false) : $actual != $expected) {
                return false;
            }
        }
        return true;
    }

    /** A scheduled automation with a day/hour schedule only runs in those Asia/Kathmandu hours. */
    public function isDueAt(\Carbon\CarbonInterface $at): bool
    {
        if (!$this->schedule) {
            return true;
        }
        $local = $at->copy()->setTimezone(config('marketing.timezone'));
        $weekdays = $this->schedule['weekdays'] ?? null;
        $hours = $this->schedule['hours'] ?? null;

        return (!$weekdays || in_array($local->dayOfWeek, $weekdays, true))
            && (!$hours || in_array($local->hour, $hours, true));
    }

    /** Deterministic A/B assignment so a student always lands in the same arm. */
    public function variantFor(int $studentId): string
    {
        if (!$this->variant_b_template_key) {
            return 'A';
        }
        return (crc32("{$this->id}:{$studentId}") % 100) < $this->ab_split_pct ? 'A' : 'B';
    }

    public function templateKeyFor(string $variant): string
    {
        return $variant === 'B' && $this->variant_b_template_key ? $this->variant_b_template_key : $this->template_key;
    }
}
