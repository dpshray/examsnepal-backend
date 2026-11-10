<?php

namespace App\Console\Commands;

use App\Services\FrontendCache;
use Illuminate\Console\Command;

class FlushFrontendCache extends Command
{
    protected $signature = 'frontend:flush-cache {--tag=* : Queue these tags first, e.g. --tag=public-data}';

    protected $description = 'Send queued cache invalidations to the Vercel frontend';

    public function handle(): int
    {
        foreach ($this->option('tag') as $tag) {
            FrontendCache::invalidate($tag);
        }

        $sent = FrontendCache::flush();
        if ($sent) {
            $this->info("Revalidated {$sent} frontend cache tag(s).");
        }

        return self::SUCCESS;
    }
}
