<?php

namespace App\Console\Commands;

use App\Models\Notice;
use App\Services\Notices\Enrichment\NoticeAiClient;
use App\Services\Notices\Enrichment\NoticeEnricher;
use App\Services\Notices\Enrichment\NoticeTextExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs the same notices through several models and records what each
 * extracted - nothing is saved to the notices table. Used to pick the
 * cheapest model that is still accurate on real (often scanned, Nepali)
 * notices. Results: storage/app/notices/ai-compare-*.json
 */
class CompareNoticeAiModels extends Command
{
    protected $signature = 'notices:ai-compare
        {--models= : Comma-separated model ids (default: configured model + fallback)}
        {--limit=20 : Number of notices}
        {--ids= : Comma-separated notice ids instead of an automatic sample}';

    protected $description = 'Compare AI models on real notices without saving anything';

    public function handle(NoticeTextExtractor $extractor, NoticeEnricher $enricher): int
    {
        if (! NoticeAiClient::isConfigured()) {
            $this->error('No AI key configured for provider "'.config('notices.ai.provider').'".');

            return self::FAILURE;
        }

        $models = $this->option('models')
            ? array_map('trim', explode(',', $this->option('models')))
            : array_unique([config('notices.ai.model'), config('notices.ai.fallback_model')]);

        $notices = $this->sample();
        $this->info("Comparing ".count($models)." model(s) on {$notices->count()} notice(s): ".implode(', ', $models));

        $rows = [];
        $totals = array_fill_keys($models, ['valid' => 0, 'cost' => 0.0, 'seconds' => 0.0, 'in' => 0, 'out' => 0, 'relevant' => 0]);

        foreach ($notices as $i => $notice) {
            $this->line(sprintf("\n[%d/%d] #%d %s", $i + 1, $notices->count(), $notice->id, Str::limit($notice->title_original, 80)));

            try {
                $document = $extractor->extract($notice);
            } catch (Throwable $e) {
                $this->warn('  could not load document: '.$e->getMessage());

                continue;
            }
            $this->line('  '.($document->isScanned() ? count($document->files).' file(s) sent as PDF/image' : mb_strlen($document->text).' chars of text')
                .($document->warnings ? '  ⚠ '.Str::limit(implode('; ', $document->warnings), 100) : ''));

            $row = [
                'notice_id' => $notice->id,
                'source' => $notice->source?->name,
                'title_original' => $notice->title_original,
                'source_url' => $notice->source_url,
                'attachments' => $notice->attachment_urls,
                'scanned' => $document->isScanned(),
                'results' => [],
            ];

            foreach ($models as $model) {
                try {
                    $r = $enricher->trial($notice, $document, $model);
                } catch (Throwable $e) {
                    $r = ['valid' => false, 'errors' => [$e->getMessage()], 'data' => null, 'input_tokens' => 0, 'output_tokens' => 0, 'cost' => null, 'seconds' => 0];
                }
                $row['results'][$model] = $r;

                $t = &$totals[$model];
                $t['valid'] += $r['valid'] ? 1 : 0;
                $t['relevant'] += ($r['data']['is_relevant'] ?? false) ? 1 : 0;
                $t['cost'] += (float) ($r['cost'] ?? 0);
                $t['seconds'] += (float) ($r['seconds'] ?? 0);
                $t['in'] += $r['input_tokens'];
                $t['out'] += $r['output_tokens'];
                unset($t);

                $d = $r['data'];
                $this->line(sprintf(
                    '  %-32s %s conf=%s type=%s deadline=%s exam=%s posts=%s seats=%s  %.1fs $%.4f',
                    Str::limit($model, 32, ''),
                    $r['valid'] ? 'ok ' : 'ERR',
                    $d ? number_format($d['confidence'], 2) : '-',
                    $d['notice_type'] ?? '-',
                    $d['application_deadline_bs'] ?? $d['application_deadline_ad'] ?? '-',
                    $d['exam_date_bs'] ?? $d['exam_date_ad'] ?? '-',
                    $d ? count($d['posts']) : '-',
                    $d ? array_sum(array_map(fn ($p) => (int) ($p['seats'] ?? 0), $d['posts'])) : '-',
                    $r['seconds'] ?? 0,
                    $r['cost'] ?? 0,
                ).($r['valid'] ? '' : '  '.Str::limit(implode('; ', $r['errors']), 120)));
            }

            $rows[] = $row;
        }

        $path = storage_path('app/notices/ai-compare-'.now()->format('Ymd-His').'.json');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode(['models' => $models, 'totals' => $totals, 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $n = max(count($rows), 1);
        $this->newLine();
        $this->table(
            ['model', 'valid', 'relevant', 'avg sec', 'tokens in/out', 'cost', 'est. per 1,000 notices'],
            collect($totals)->map(fn ($t, $m) => [
                $m, "{$t['valid']}/{$n}", $t['relevant'], number_format($t['seconds'] / $n, 1),
                "{$t['in']}/{$t['out']}", '$'.number_format($t['cost'], 4), '$'.number_format($t['cost'] / $n * 1000, 2),
            ])->values()->all()
        );
        $this->info("Full outputs: {$path}");

        return self::SUCCESS;
    }

    /** A spread across sources, favouring notices with attachments (the hard cases). */
    private function sample()
    {
        if ($ids = $this->option('ids')) {
            return Notice::with('source')->whereIn('id', explode(',', $ids))->get()->values();
        }

        $limit = (int) $this->option('limit');

        return Notice::with('source')
            ->where('status', 'pending')
            ->get()
            ->groupBy('source_id')
            ->map(fn ($group) => $group->sortByDesc(fn ($n) => count($n->attachment_urls ?? []))->values())
            ->pipe(function ($groups) use ($limit) {
                // Round-robin across sources.
                $picked = collect();
                for ($round = 0; $picked->count() < $limit && $round < 10; $round++) {
                    foreach ($groups as $group) {
                        if ($picked->count() < $limit && isset($group[$round])) {
                            $picked->push($group[$round]);
                        }
                    }
                }

                return $picked;
            })
            ->values();
    }
}
