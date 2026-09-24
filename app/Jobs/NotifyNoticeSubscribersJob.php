<?php

namespace App\Jobs;

use App\Enums\NotificationTypeEnum;
use App\Models\Notice;
use App\Models\NoticeSubscription;
use App\Services\FCMService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Push notification to app users whose subscription matches a newly
 * published notice. Email subscribers get the daily digest instead
 * (notices:digest) so nobody receives one mail per notice.
 */
class NotifyNoticeSubscribersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $noticeId)
    {
        $this->onConnection(config('notices.queue_connection'));
        $this->onQueue(config('notices.queue'));
    }

    public function handle(): void
    {
        $notice = Notice::find($this->noticeId);
        if (! $notice || $notice->status !== 'published') {
            return;
        }

        $subscribers = NoticeSubscription::query()
            ->where('channel', 'push')
            ->where('is_active', true)
            ->whereNotNull('student_profile_id')
            ->with('student:id,fcm_token')
            ->get()
            ->filter(fn (NoticeSubscription $s) => $s->matches($notice) && $s->student?->fcm_token);

        if ($subscribers->isNotEmpty()) {
            $fcm = new FCMService(
                title: Str::limit($notice->displayTitle(), 100),
                body: Str::limit($notice->summary_en ?: $notice->organization, 180),
                type: NotificationTypeEnum::NEW_NOTICE->value,
                students: $subscribers->pluck('student_profile_id')->all(),
            );
            $fcm->notify($subscribers->pluck('student.fcm_token')->all());

            NoticeSubscription::whereIn('id', $subscribers->pluck('id'))->update(['last_notified_at' => now()]);
        }

        if (config('notices.social_enabled') && $notice->is_featured) {
            // Channel credentials (Facebook page token / Telegram bot) are not
            // configured yet - see docs/notices-plan.md, deviation 5.
            Log::info('notices: social auto-post skipped, no channel configured', ['notice_id' => $notice->id]);
        }
    }
}
