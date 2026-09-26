<?php

namespace App\Console\Commands;

use App\Services\Subjects\SubjectCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tags single-subject exams (and their questions) with a subject inferred
 * from the exam name. Dry run by default; --apply writes. Exams whose
 * subject was set manually (subject_source = manual) are never touched.
 */
class InferExamSubjects extends Command
{
    protected $signature = 'subjects:infer
        {--apply : Write exams.subject_id and questions.subject_id (default: dry run)}
        {--retag : Also re-infer exams tagged automatically before}
        {--samples=8 : Example names to show per outcome}';

    protected $description = 'Infer exam subjects from exam names (for per-subject scores and weak-subject emails)';

    public function handle(): int
    {
        $exams = DB::table('exams')
            ->where(fn ($q) => $q->whereNull('subject_source')->when($this->option('retag'), fn ($w) => $w->orWhere('subject_source', 'auto')))
            ->where(fn ($q) => $q->where('is_class_exam', 0)->orWhereNull('is_class_exam'))
            ->get(['id', 'exam_name', 'exam_type_id']);

        $results = $exams->map(fn ($e) => ['exam' => $e] + SubjectCatalog::infer((string) $e->exam_name, $e->exam_type_id ? (int) $e->exam_type_id : null));
        $matched = $results->where('reason', 'matched');
        $questionCounts = DB::table('questions')->whereIn('exam_id', $matched->pluck('exam.id'))->groupBy('exam_id')->pluck(DB::raw('COUNT(*)'), 'exam_id');

        $this->table(['Subject', 'Exams', 'Questions'], $matched->groupBy('subject')->map(fn ($g, $s) => [
            $s, $g->count(), $g->sum(fn ($r) => (int) ($questionCounts[$r['exam']->id] ?? 0)),
        ])->sortByDesc(1)->values());

        $this->info(sprintf('%d exams checked: %s', $results->count(), $results->countBy('reason')->map(fn ($n, $r) => "{$r} {$n}")->implode(', ')));
        foreach (['ambiguous', 'no_match'] as $reason) {
            $sample = $results->where('reason', $reason)->take((int) $this->option('samples'))->map(fn ($r) => '  ' . trim($r['exam']->exam_name));
            if ($sample->isNotEmpty()) {
                $this->line("{$reason} (examples):");
                $sample->each(fn ($l) => $this->line($l));
            }
        }

        if (!$this->option('apply')) {
            $this->comment('Dry run. Re-run with --apply to write.');
            return self::SUCCESS;
        }

        $subjectIds = [];
        foreach ($matched->pluck('subject')->unique() as $name) {
            $id = DB::table('subjects')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id')
                ?? DB::table('subjects')->insertGetId(['name' => $name, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $subjectIds[$name] = $id;
        }

        $questions = 0;
        DB::transaction(function () use ($matched, $subjectIds, &$questions) {
            foreach ($matched as $r) {
                $sid = $subjectIds[$r['subject']];
                DB::table('exams')->where('id', $r['exam']->id)->update(['subject_id' => $sid, 'subject_source' => 'auto']);
                $questions += DB::table('questions')->where('exam_id', $r['exam']->id)
                    ->where(fn ($q) => $q->whereNull('subject_id')->orWhere('subject_id', '!=', $sid))
                    ->update(['subject_id' => $sid]);
            }
        });

        $this->info("Tagged {$matched->count()} exams and {$questions} questions across " . count($subjectIds) . ' subjects.');
        $this->comment('Next: php artisan marketing:refresh-metrics');

        return self::SUCCESS;
    }
}
