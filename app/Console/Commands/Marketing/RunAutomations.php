<?php

namespace App\Console\Commands\Marketing;

use App\Services\Marketing\AutomationEngine;
use App\Services\Marketing\MarketingSettings;
use Illuminate\Console\Command;

class RunAutomations extends Command
{
    protected $signature = 'marketing:run-automations {--dry-run : Only show who would be queued}';

    protected $description = 'Queue scheduled (condition-based) automations for students who qualify now';

    public function handle(): int
    {
        $engine = new AutomationEngine();

        if ($this->option('dry-run')) {
            $rows = collect($engine->preview())->map(fn ($r) => [
                $r['automation'], $r['candidates'], $r['would_send'],
                collect($r['skipped'])->map(fn ($n, $why) => "{$why}: {$n}")->implode(', '),
            ]);
            $this->table(['Automation', 'Match conditions', 'Would queue', 'Skipped'], $rows);
            return self::SUCCESS;
        }

        if (MarketingSettings::paused()) {
            $this->warn('Automations are paused (kill switch); nothing queued.');
            return self::SUCCESS;
        }

        $this->info('Queued ' . $engine->plan() . ' message(s).');

        return self::SUCCESS;
    }
}
