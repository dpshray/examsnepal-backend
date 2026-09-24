<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoticeSource extends Model
{
    protected $fillable = [
        'name', 'organization', 'category', 'sub_category', 'province',
        'base_url', 'list_url', 'fetch_type', 'adapter_class', 'selectors',
        'language', 'fetch_interval_minutes', 'is_active', 'is_trusted',
        'verify_ssl', 'priority', 'notes',
    ];

    protected $hidden = ['last_snapshot_hash'];

    protected function casts(): array
    {
        return [
            'selectors' => 'array',
            'is_active' => 'boolean',
            'is_trusted' => 'boolean',
            'verify_ssl' => 'boolean',
            'last_fetched_at' => 'datetime',
            'last_success_at' => 'datetime',
            'unhealthy_notified_at' => 'datetime',
        ];
    }

    public function notices()
    {
        return $this->hasMany(Notice::class, 'source_id');
    }

    public function fetchLogs()
    {
        return $this->hasMany(NoticeFetchLog::class, 'source_id');
    }

    public function scopeFetchable($query)
    {
        return $query->where('is_active', true)->where('fetch_type', '!=', 'manual');
    }

    public function isDue(): bool
    {
        return ! $this->last_fetched_at
            || $this->last_fetched_at->lte(now()->subMinutes($this->fetch_interval_minutes));
    }

    public function isHealthy(): bool
    {
        return $this->consecutive_failures < config('notices.health.max_consecutive_failures')
            && $this->consecutive_empty_runs < config('notices.health.max_consecutive_empty_runs');
    }

    public function host(): string
    {
        return (string) parse_url($this->list_url, PHP_URL_HOST);
    }

    public function selector(string $key, mixed $default = null): mixed
    {
        return data_get($this->selectors ?? [], $key, $default);
    }
}
