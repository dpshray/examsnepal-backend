<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoticeReport extends Model
{
    protected $fillable = ['notice_id', 'field', 'message', 'email', 'ip', 'is_resolved'];

    protected function casts(): array
    {
        return ['is_resolved' => 'boolean'];
    }

    public function notice()
    {
        return $this->belongsTo(Notice::class);
    }
}
