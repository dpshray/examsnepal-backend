<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    public const SCHEDULED = 'scheduled';
    public const QUEUED = 'queued';
    public const CANCELLED = 'cancelled';

    protected $fillable = ['name', 'template_key', 'filters', 'status', 'scheduled_for', 'recipients_queued', 'recipients_skipped', 'created_by'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'scheduled_for' => 'datetime',
            'recipients_queued' => 'integer',
            'recipients_skipped' => 'integer',
        ];
    }
}
