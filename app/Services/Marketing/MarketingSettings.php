<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\DB;

/** Runtime switches admins flip from the dashboard (stored in marketing_settings). */
class MarketingSettings
{
    public const PAUSED = 'automations_paused';

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = DB::table('marketing_settings')->where('key', $key)->value('value');

        return $value === null ? $default : json_decode($value, true);
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('marketing_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now()]);
    }

    /** Global kill switch. Defaults to paused so nothing sends until an admin turns it on. */
    public static function paused(): bool
    {
        return (bool) self::get(self::PAUSED, true);
    }
}
