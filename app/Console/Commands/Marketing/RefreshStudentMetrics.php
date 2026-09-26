<?php

namespace App\Console\Commands\Marketing;

use App\Services\Marketing\StudentMetricsCalculator;
use Illuminate\Console\Command;

class RefreshStudentMetrics extends Command
{
    protected $signature = 'marketing:refresh-metrics {--student=* : Only recompute these student ids}';

    protected $description = 'Rebuild the denormalised student_metrics table';

    public function handle(): int
    {
        $started = microtime(true);
        $calculator = new StudentMetricsCalculator();

        if ($ids = array_map('intval', $this->option('student'))) {
            $calculator->refresh($ids);
            $count = count($ids);
        } else {
            $count = $calculator->refreshAll();
        }

        $this->info(sprintf('Refreshed metrics for %d student(s) in %.1fs.', $count, microtime(true) - $started));

        return self::SUCCESS;
    }
}
