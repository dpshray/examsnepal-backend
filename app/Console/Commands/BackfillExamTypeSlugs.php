<?php

namespace App\Console\Commands;

use App\Models\ExamType;
use Illuminate\Console\Command;

class BackfillExamTypeSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-examtype-slugs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time backfill of slugs for existing exam types (new ones get one automatically via SlugTrait).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $examTypes = ExamType::whereNull('slug')->get();

        if ($examTypes->isEmpty()) {
            $this->info('Nothing to backfill - every exam type already has a slug.');

            return self::SUCCESS;
        }

        foreach ($examTypes as $examType) {
            $examType->slug = ExamType::createSlug($examType, $examType->name);
            $examType->saveQuietly();
            $this->line("  {$examType->id}: {$examType->name} -> {$examType->slug}");
        }

        $this->info("Backfilled {$examTypes->count()} exam type(s).");

        return self::SUCCESS;
    }
}
