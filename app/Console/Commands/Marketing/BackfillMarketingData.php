<?php

namespace App\Console\Commands\Marketing;

use App\Services\Marketing\AttemptScorer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off (safe to re-run) backfill for data that existed before the
 * marketing tables: signup timestamps, attempt scores and payment events.
 */
class BackfillMarketingData extends Command
{
    protected $signature = 'marketing:backfill {--skip-scores} {--skip-payments}';

    protected $description = 'Backfill signup dates, attempt scores and payment events for marketing metrics';

    public function handle(AttemptScorer $scorer): int
    {
        $this->backfillSignupDates();

        if (!$this->option('skip-scores')) {
            $this->backfillScores($scorer);
        }
        if (!$this->option('skip-payments')) {
            $this->call('marketing:sync-payment-events', ['--all' => true]);
        }

        $this->comment('Now run: php artisan marketing:refresh-metrics');

        return self::SUCCESS;
    }

    /**
     * student_profiles.date holds the signup time as text ('m/d/Y h:i:s a' for
     * email signups). Google sign-in overwrites it on every login, so for those
     * accounts the first attempt, if earlier, is the better signup estimate.
     */
    private function backfillSignupDates(): void
    {
        $filled = 0;
        DB::table('student_profiles')->whereNull('created_at')
            ->select(['id', 'date', 'google_id'])
            ->chunkById(1000, function ($students) use (&$filled) {
                $firstAttempts = DB::table('student_exams')
                    ->whereIn('student_id', $students->pluck('id')->all())
                    ->whereNotNull('created_at')
                    ->groupBy('student_id')
                    ->pluck(DB::raw('MIN(created_at)'), 'student_id');

                foreach ($students as $s) {
                    $candidates = array_filter([
                        self::parseLegacyDate($s->date),
                        isset($firstAttempts[$s->id]) ? Carbon::parse($firstAttempts[$s->id]) : null,
                    ]);
                    if (!$candidates) {
                        continue;
                    }
                    $signup = $s->google_id || !self::parseLegacyDate($s->date)
                        ? min($candidates)
                        : self::parseLegacyDate($s->date);

                    DB::table('student_profiles')->where('id', $s->id)->update(['created_at' => $signup]);
                    $filled++;
                }
            });

        $missing = DB::table('student_profiles')->whereNull('created_at')->count();
        $this->info("Signup dates: filled {$filled}, still unknown {$missing}.");
    }

    public static function parseLegacyDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        foreach (['m/d/Y h:i:s a', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false && $date->format($format) === $value) {
                    return $format === 'Y-m-d' ? $date->startOfDay() : $date;
                }
            } catch (\Throwable) {
                // try the next format
            }
        }
        return null;
    }

    private function backfillScores(AttemptScorer $scorer): void
    {
        $total = DB::table('student_exams')->where('is_exam_completed', 1)->whereNull('score_pct')->count();
        $bar = $this->output->createProgressBar($total);
        $scored = 0;

        DB::table('student_exams')->where('is_exam_completed', 1)->whereNull('score_pct')
            ->select('id')
            ->chunkById(500, function ($chunk) use ($scorer, $bar, &$scored) {
                $scored += $scorer->score($chunk->pluck('id')->all());
                $bar->advance($chunk->count());
            });

        $bar->finish();
        $this->newLine();
        $this->info("Attempt scores: computed {$scored}.");
    }
}
