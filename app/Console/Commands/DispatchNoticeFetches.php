<?php

namespace App\Console\Commands;

use App\Jobs\FetchNoticeSourceJob;
use App\Models\NoticeSource;
use Illuminate\Console\Command;

class DispatchNoticeFetches extends Command
{
    protected $signature = 'notices:dispatch';

    protected $description = 'Queue a fetch job for every notice source whose interval has elapsed';

    public function handle(): int
    {
        if (! config('notices.fetch_enabled')) {
            $this->line('NOTICES_FETCH_ENABLED is off - nothing dispatched.');

            return self::SUCCESS;
        }

        $due = NoticeSource::fetchable()->orderByDesc('priority')->get()->filter->isDue();

        foreach ($due as $source) {
            FetchNoticeSourceJob::dispatch($source->id)->onQueue(config('notices.queue'));
        }

        $this->info("Dispatched {$due->count()} fetch job(s).");

        return self::SUCCESS;
    }
}
