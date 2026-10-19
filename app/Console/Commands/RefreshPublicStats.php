<?php

namespace App\Console\Commands;

use App\Services\PublicStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshPublicStats extends Command
{
    protected $signature = 'stats:refresh-public';

    protected $description = 'Recompute the cached public website stats (homepage numbers, per-exam performance)';

    public function handle(PublicStats $stats): int
    {
        $stats->site(fresh: true);

        $shown = 0;
        foreach (DB::table('exam_types')->pluck('id') as $examTypeId) {
            if ($stats->forExamType((int) $examTypeId, fresh: true)) {
                $shown++;
            }
        }

        $this->info("Public stats refreshed ({$shown} exam type(s) with enough data to show).");

        return self::SUCCESS;
    }
}
