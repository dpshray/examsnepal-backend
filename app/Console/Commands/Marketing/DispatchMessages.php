<?php

namespace App\Console\Commands\Marketing;

use App\Services\Marketing\AutomationEngine;
use Illuminate\Console\Command;

class DispatchMessages extends Command
{
    protected $signature = 'marketing:dispatch {--limit=200}';

    protected $description = 'Send due lifecycle messages (guards re-checked; respects kill switch, send windows and caps)';

    public function handle(): int
    {
        $stats = (new AutomationEngine())->dispatch((int) $this->option('limit'));
        $this->info(collect($stats)->map(fn ($n, $k) => "{$k}: {$n}")->implode(', '));

        return self::SUCCESS;
    }
}
