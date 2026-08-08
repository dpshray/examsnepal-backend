<?php

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillQuestionSlugs extends Command
{
    protected $signature = 'questions:backfill-slugs {--chunk=1000}';

    protected $description = 'Backfill questions.slug for rows left null by the add_slug_and_view_count_to_questions_table migration (schema-only, no data)';

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');

        $total = Question::whereNull('slug')->count();

        if ($total === 0) {
            $this->info('Nothing to backfill - every question already has a slug.');
            return self::SUCCESS;
        }

        $this->info("Backfilling slug for {$total} question(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // No in-memory set of existing slugs here on purpose - at ~200k rows
        // that blew PHP's memory limit. Uniqueness is checked per-row against
        // the indexed `slug` column instead, which stays cheap regardless of
        // table size.
        Question::whereNull('slug')->orderBy('id')->chunkById($chunkSize, function ($questions) use ($bar) {
            foreach ($questions as $question) {
                $base = Str::slug(Str::words(strip_tags((string) $question->question), 12, ''));
                if ($base === '') {
                    $base = 'question-' . $question->id;
                }

                $slug = $base;
                $suffix = 2;
                while (Question::where('slug', $slug)->exists()) {
                    $slug = "{$base}-{$suffix}";
                    $suffix++;
                }

                DB::table('questions')->where('id', $question->id)->update(['slug' => $slug]);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
