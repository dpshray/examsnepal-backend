<?php

namespace App\Console\Commands;

use App\Mail\Notices\NoticeDigestMail;
use App\Models\Notice;
use App\Models\NoticeSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNoticeDigest extends Command
{
    protected $signature = 'notices:digest';

    protected $description = 'Email each notice subscriber one digest of notices published since their last digest';

    public function handle(): int
    {
        if (! config('notices.notifications_enabled')) {
            $this->line('NOTICES_NOTIFICATIONS_ENABLED is off.');

            return self::SUCCESS;
        }

        $sent = 0;
        NoticeSubscription::where('channel', 'email')->where('is_active', true)->whereNotNull('email')
            ->chunkById(200, function ($subscriptions) use (&$sent) {
                foreach ($subscriptions as $subscription) {
                    $since = $subscription->last_notified_at ?? $subscription->created_at;
                    $notices = Notice::published()
                        ->where('published_at', '>', $since)
                        ->latest('published_at')
                        ->limit(100)
                        ->get()
                        ->filter(fn (Notice $n) => $subscription->matches($n))
                        ->take(25)
                        ->values();

                    if ($notices->isEmpty()) {
                        continue;
                    }

                    try {
                        Mail::to($subscription->email)->send(new NoticeDigestMail($subscription, $notices));
                        $subscription->forceFill(['last_notified_at' => now()])->save();
                        $sent++;
                    } catch (Throwable $e) {
                        $this->warn("Failed for subscription #{$subscription->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Sent {$sent} digest(s).");

        return self::SUCCESS;
    }
}
