<?php

namespace App\Console\Commands;

use App\Models\NoticeSource;
use App\Services\Notices\NoticeFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class FetchNotices extends Command
{
    protected $signature = 'notices:fetch
        {source?* : Source id(s); omit with --all to run every fetchable source}
        {--all : Run all active non-manual sources}
        {--dry-run : Parse and print items without saving anything}';

    protected $description = 'Fetch notice sources now (synchronously), optionally as a dry run';

    public function handle(NoticeFetcher $fetcher): int
    {
        $ids = $this->argument('source');
        if (! $ids && ! $this->option('all')) {
            $this->error('Pass one or more source ids, or --all.');

            return self::INVALID;
        }

        $sources = $ids
            ? NoticeSource::whereIn('id', $ids)->get()
            : NoticeSource::fetchable()->orderByDesc('priority')->get();

        foreach ($sources as $source) {
            $this->newLine();
            $this->info("#{$source->id} {$source->name}  [{$source->fetch_type}]  {$source->list_url}");

            if ($source->fetch_type === 'manual') {
                $this->line('  manual source - skipped');

                continue;
            }

            if ($this->option('dry-run')) {
                try {
                    $items = $fetcher->preview($source);
                } catch (Throwable $e) {
                    $this->error('  '.class_basename($e).': '.$e->getMessage());

                    continue;
                }

                $this->line('  '.count($items).' items parsed');
                $this->table(
                    ['new?', 'date (BS)', 'date (AD)', 'title', 'url', 'files'],
                    array_map(fn ($i) => [
                        $i['would_insert'] ? 'yes' : '-',
                        $i['published_date_bs'] ?? ($i['date_text'] ? '?'.Str::limit($i['date_text'], 16) : ''),
                        $i['published_date_ad'] ?? '',
                        Str::limit($i['title'], 60),
                        Str::limit($i['url'], 70),
                        count($i['attachments']),
                    ], $items)
                );

                continue;
            }

            $log = $fetcher->run($source);
            $this->line("  {$log->status}: {$log->items_found} found, {$log->items_new} new".($log->error ? "  error: {$log->error}" : ''));
        }

        return self::SUCCESS;
    }
}
