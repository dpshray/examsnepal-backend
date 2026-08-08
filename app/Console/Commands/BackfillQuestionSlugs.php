<?php

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;

class BackfillQuestionSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-question-slugs {--chunk=500} {--limit= : Only process this many rows, for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time backfill of slugs for existing questions (new questions get one automatically via SlugTrait).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $total = Question::whereNull('slug')->count();

        if ($total === 0) {
            $this->info('Nothing to backfill - every question already has a slug.');

            return self::SUCCESS;
        }

        $target = $limit !== null ? min($limit, $total) : $total;
        $this->info("Backfilling slugs for {$target} of {$total} question(s)...");
        $bar = $this->output->createProgressBar($target);
        $done = 0;

        Question::whereNull('slug')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($questions) use (&$done, $bar, $limit) {
                foreach ($questions as $question) {
                    if ($limit !== null && $done >= $limit) {
                        return false;
                    }

                    $question->slug = Question::createSlug($question, $question->slug_source);
                    $question->saveQuietly();
                    $done++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("Done. {$done} question(s) given a slug.");

        return self::SUCCESS;
    }
}
