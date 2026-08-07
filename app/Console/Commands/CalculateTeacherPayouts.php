<?php

namespace App\Console\Commands;

use App\Services\TeacherRevenueShareService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CalculateTeacherPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payouts:calculate-teacher {--month= : Month to calculate, format YYYY-MM. Defaults to last month.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate (or recalculate, while still pending) teacher revenue-share payouts for a given month.';

    /**
     * Execute the console command.
     */
    public function handle(TeacherRevenueShareService $service): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))
            : Carbon::now()->subMonthNoOverflow();

        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        $this->info("Calculating teacher payouts for {$periodStart->format('F Y')}...");

        $payouts = $service->finalizeForPeriod($periodStart, $periodEnd);

        $this->info("Done - {$payouts->count()} payout row(s) written (status: pending).");

        return self::SUCCESS;
    }
}
