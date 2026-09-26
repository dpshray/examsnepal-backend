<?php

namespace App\Jobs;

use App\Services\Marketing\StudentMetricsCalculator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Incremental student_metrics update after an exam submission, so the
 * dashboard and automations don't wait for the hourly full refresh.
 */
class RefreshStudentMetricsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;

    public int $uniqueFor = 60;

    public function __construct(public int $studentId)
    {
        $this->onConnection(config('marketing.queue_connection'));
        $this->onQueue(config('marketing.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->studentId;
    }

    public function handle(): void
    {
        (new StudentMetricsCalculator())->refresh([$this->studentId]);
    }
}
