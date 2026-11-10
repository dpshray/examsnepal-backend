<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Services\Seo\McqIndexability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScoreMcqSeo extends Command
{
    protected $signature = 'mcq:score-seo
        {--dry-run : Report what would change without writing anything}
        {--chunk=2000 : Questions per batch}';

    protected $description = 'Mark which public MCQ pages should be indexed (seo_noindex_reason) and point exact duplicates at one canonical copy';

    public function handle(McqIndexability $rules): int
    {
        // Holds a row per question (~150MB at 194k questions) to find
        // duplicates; PHP's default CLI limit of 128M isn't enough.
        ini_set('memory_limit', '1G');

        $dryRun = (bool) $this->option('dry-run');

        $withImage = DB::table('media')
            ->where('model_type', Question::class)
            ->where('collection_name', Question::QUESTION_IMAGE)
            ->pluck('model_id')
            ->flip();

        // id => [reason, explanation length, current reason, current canonical]
        $rows = [];
        $groups = [];

        DB::table('questions')
            ->select('id', 'question', 'explanation', 'seo_noindex_reason', 'canonical_question_id')
            ->orderBy('id')
            ->chunk((int) $this->option('chunk'), function ($chunk) use ($rules, $withImage, &$rows, &$groups) {
                $options = DB::table('option_questions')
                    ->whereIn('question_id', $chunk->pluck('id'))
                    ->get(['question_id', 'option'])
                    ->groupBy('question_id');

                foreach ($chunk as $q) {
                    $rows[$q->id] = [
                        $rules->reason($q->question, $q->explanation, isset($withImage[$q->id])),
                        mb_strlen(McqIndexability::plain($q->explanation)),
                        $q->seo_noindex_reason,
                        $q->canonical_question_id,
                    ];
                    $key = $rules->duplicateKey($q->question, ($options[$q->id] ?? collect())->pluck('option'));
                    $groups[$key][] = $q->id;
                }
            });

        // The copy that keeps the canonical URL: an indexable one if any, then
        // the fullest explanation, then the oldest (most likely to have links).
        $canonical = [];
        foreach ($groups as $ids) {
            if (count($ids) < 2) {
                continue;
            }
            usort($ids, fn ($a, $b) => [$rows[$a][0] !== null, -$rows[$a][1], $a] <=> [$rows[$b][0] !== null, -$rows[$b][1], $b]);
            $keeper = array_shift($ids);
            foreach ($ids as $id) {
                $canonical[$id] = $keeper;
            }
        }

        $reasonChanges = [];
        $canonicalChanges = [];
        $summary = ['indexable' => 0, 'duplicates' => count($canonical)];

        foreach ($rows as $id => [$reason, , $currentReason, $currentCanonical]) {
            $target = $canonical[$id] ?? null;
            if ($target === null) {
                $summary[$reason ?? 'indexable'] = ($summary[$reason ?? 'indexable'] ?? 0) + 1;
            }
            if ($reason !== $currentReason) {
                $reasonChanges[$reason ?? ''][] = $id;
            }
            if ($target !== ($currentCanonical === null ? null : (int) $currentCanonical)) {
                $canonicalChanges[$target ?? 0][] = $id;
            }
        }

        $this->table(['Outcome', 'Questions'], collect($summary)->map(fn ($n, $k) => [$k, number_format($n)])->values());
        $this->line(sprintf(
            '%s %s reason change(s), %s canonical change(s).',
            $dryRun ? 'Would write' : 'Writing',
            number_format(array_sum(array_map('count', $reasonChanges))),
            number_format(array_sum(array_map('count', $canonicalChanges))),
        ));

        if ($dryRun) {
            return self::SUCCESS;
        }

        foreach ($reasonChanges as $reason => $ids) {
            foreach (array_chunk($ids, 1000) as $batch) {
                DB::table('questions')->whereIn('id', $batch)->update(['seo_noindex_reason' => $reason === '' ? null : $reason]);
            }
        }
        foreach ($canonicalChanges as $target => $ids) {
            foreach (array_chunk($ids, 1000) as $batch) {
                DB::table('questions')->whereIn('id', $batch)->update(['canonical_question_id' => $target ?: null]);
            }
        }

        // Topic pages are built from indexable questions - rebuild them now.
        $topics = app(\App\Services\Seo\McqTopics::class)->all(fresh: true);
        $this->info('Done. '.count($topics).' MCQ topic page(s).');

        return self::SUCCESS;
    }
}
