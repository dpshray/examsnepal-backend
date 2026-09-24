<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoticeFetchLog extends Model
{
    protected $fillable = [
        'source_id', 'started_at', 'finished_at', 'items_found', 'items_new', 'status', 'error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function source()
    {
        return $this->belongsTo(NoticeSource::class, 'source_id');
    }
}
