<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillQuestionExamType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-question-exam-type {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill questions.exam_type_id from exams.exam_type_id (via exam_id) for questions where it is currently null.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $affected = DB::table('questions')
            ->join('exams', 'exams.id', '=', 'questions.exam_id')
            ->whereNull('questions.exam_type_id')
            ->whereNotNull('exams.exam_type_id')
            ->count();

        $this->info("Questions eligible for backfill: {$affected}");

        if ($affected === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run - no changes made.');

            return self::SUCCESS;
        }

        $updated = DB::update(
            'UPDATE questions
             INNER JOIN exams ON exams.id = questions.exam_id
             SET questions.exam_type_id = exams.exam_type_id
             WHERE questions.exam_type_id IS NULL
               AND exams.exam_type_id IS NOT NULL'
        );

        $this->info("Backfilled {$updated} question(s).");

        return self::SUCCESS;
    }
}
