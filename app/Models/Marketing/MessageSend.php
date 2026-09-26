<?php

namespace App\Models\Marketing;

use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageSend extends Model
{
    public const QUEUED = 'queued';
    public const SENT = 'sent';
    public const DELIVERED = 'delivered';
    public const BOUNCED = 'bounced';
    public const OPENED = 'opened';
    public const CLICKED = 'clicked';
    public const FAILED = 'failed';
    public const SUPPRESSED = 'suppressed';

    /** Statuses that mean the message actually left (or is about to). */
    public const DELIVERED_STATUSES = [self::SENT, self::DELIVERED, self::OPENED, self::CLICKED, self::BOUNCED];

    protected $fillable = [
        'student_id', 'automation_id', 'broadcast_id', 'channel', 'category', 'template_key', 'variant', 'status',
        'suppress_reason', 'to_address', 'subject', 'context', 'scheduled_for', 'sent_at', 'opened_at',
        'clicked_at', 'bounced_at', 'unsubscribed_at', 'goal_event', 'goal_met_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'bounced_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'goal_met_at' => 'datetime',
        ];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }
}
