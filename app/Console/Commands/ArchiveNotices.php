<?php

namespace App\Console\Commands;

use App\Models\Notice;
use App\Services\Notices\NoticePublisher;
use Illuminate\Console\Command;

class ArchiveNotices extends Command
{
    protected $signature = 'notices:archive';

    protected $description = 'Archive published notices whose application deadline passed long enough ago';

    public function handle(NoticePublisher $publisher): int
    {
        $count = Notice::published()
            ->whereNotNull('application_deadline_ad')
            ->where('application_deadline_ad', '<', today()->subDays((int) config('notices.archive_after_deadline_days')))
            ->update(['status' => 'archived', 'updated_at' => now()]);

        if ($count) {
            $publisher->flushCaches();
        }

        $this->info("Archived {$count} notice(s).");

        return self::SUCCESS;
    }
}
