<?php

namespace App\Console\Commands;

use App\Support\MojibakeFixer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepairMojibake extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:repair-mojibake
        {--dry-run : Report what would change without writing anything}
        {--chunk=500 : Rows to process per batch}
        {--revert= : Undo a previous run by its run-id, restoring old_value for every row it touched}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time repair of double/triple UTF-8-as-Windows-1252 re-encoded text in question/option/explanation columns. Every change is logged to mojibake_repairs so it can be reverted with --revert.';

    /**
     * [table, column] pairs known to have been affected by the historical
     * Word-import encoding bug. All use a plain auto-increment `id` PK.
     */
    private const TARGETS = [
        ['questions', 'question'],
        ['questions', 'explanation'],
        ['option_questions', 'option'],
        ['doubts', 'remark'],
        ['corporate_questions', 'question'],
        ['corporate_questions', 'description'],
        ['corporate_question_options', 'option'],
        ['users', 'fullname'],
        ['student_profiles', 'name'],
    ];

    public function handle(): int
    {
        if ($revertRunId = $this->option('revert')) {
            return $this->revert($revertRunId);
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $runId = (string) Str::uuid();

        $this->info($dryRun ? 'DRY RUN - no changes will be written.' : "Live run. run_id = {$runId}");
        $this->newLine();

        $totals = ['scanned' => 0, 'fixed' => 0, 'unchanged' => 0, 'errored' => 0];

        foreach (self::TARGETS as [$table, $column]) {
            if (!DB::getSchemaBuilder()->hasTable($table) || !DB::getSchemaBuilder()->hasColumn($table, $column)) {
                continue;
            }

            [$scanned, $fixed, $unchanged, $errored] = $this->repairColumn($table, $column, $runId, $dryRun, $chunkSize);

            $totals['scanned'] += $scanned;
            $totals['fixed'] += $fixed;
            $totals['unchanged'] += $unchanged;
            $totals['errored'] += $errored;

            $this->line(sprintf(
                '%-32s scanned=%-6d fixed=%-6d left_unchanged=%-6d errored=%d',
                "{$table}.{$column}",
                $scanned,
                $fixed,
                $unchanged,
                $errored
            ));
        }

        $this->newLine();
        $this->info("Totals: scanned={$totals['scanned']} fixed={$totals['fixed']} left_unchanged={$totals['unchanged']} errored={$totals['errored']}");

        if ($totals['errored'] > 0) {
            $this->warn("{$totals['errored']} row(s) failed to write (see above) - most likely a column collation that can't hold the repaired Unicode text. They were left untouched; nothing was lost.");
        }

        if (!$dryRun && $totals['fixed'] > 0) {
            $this->info("To undo this run: php artisan app:repair-mojibake --revert={$runId}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int} [scanned, fixed, unchanged, errored]
     */
    private function repairColumn(string $table, string $column, string $runId, bool $dryRun, int $chunkSize): array
    {
        $scanned = 0;
        $fixed = 0;
        $unchanged = 0;
        $errored = 0;

        DB::table($table)
            ->select(['id', $column])
            ->whereRaw("HEX(`{$column}`) LIKE '%C383%'")
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($table, $column, $runId, $dryRun, &$scanned, &$fixed, &$unchanged, &$errored) {
                foreach ($rows as $row) {
                    $scanned++;
                    $old = $row->{$column};
                    $new = MojibakeFixer::fix($old);

                    if ($new === $old) {
                        $unchanged++;
                        continue;
                    }

                    if ($dryRun) {
                        $fixed++;
                        continue;
                    }

                    try {
                        DB::transaction(function () use ($table, $column, $row, $old, $new, $runId) {
                            DB::table('mojibake_repairs')->insert([
                                'run_id' => $runId,
                                'table_name' => $table,
                                'column_name' => $column,
                                'row_id' => $row->id,
                                'old_value' => $old,
                                'new_value' => $new,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            DB::table($table)->where('id', $row->id)->update([$column => $new]);
                        });
                        $fixed++;
                    } catch (\Throwable $e) {
                        $errored++;
                        $this->warn("  failed: {$table}.{$column} id={$row->id}: {$e->getMessage()}");
                    }
                }
            });

        return [$scanned, $fixed, $unchanged, $errored];
    }

    private function revert(string $runId): int
    {
        $logs = DB::table('mojibake_repairs')->where('run_id', $runId)->get();

        if ($logs->isEmpty()) {
            $this->error("No repair log found for run_id={$runId}");

            return self::FAILURE;
        }

        $this->info("Reverting {$logs->count()} changes from run_id={$runId}...");

        $bar = $this->output->createProgressBar($logs->count());

        foreach ($logs as $log) {
            DB::table($log->table_name)->where('id', $log->row_id)->update([
                $log->column_name => $log->old_value,
            ]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        DB::table('mojibake_repairs')->where('run_id', $runId)->delete();
        $this->info('Revert complete. Repair log for this run has been cleared.');

        return self::SUCCESS;
    }
}
