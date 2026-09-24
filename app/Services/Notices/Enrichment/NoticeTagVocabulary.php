<?php

namespace App\Services\Notices\Enrichment;

use App\Models\ExamGuide;
use Illuminate\Support\Facades\Cache;

/**
 * The exam_tags the model may assign: slugs of every published exam guide
 * (each has a guide page + mock-test link) plus config('notices.fixed_exam_tags').
 */
class NoticeTagVocabulary
{
    /** @return array<string, string> slug => human label */
    public function all(): array
    {
        return Cache::remember('notices:tag-vocabulary', now()->addHour(), function () {
            $tags = ExamGuide::published()->orderBy('name')->pluck('name', 'slug')->all();

            foreach (array_keys(config('notices.fixed_exam_tags')) as $slug) {
                $tags[$slug] ??= ucwords(str_replace('-', ' ', $slug));
            }

            ksort($tags);

            return $tags;
        });
    }

    /** Drop anything the model invented outside the vocabulary. */
    public function filter(array $tags): array
    {
        $known = $this->all();

        return array_values(array_unique(array_filter(
            array_map(fn ($t) => strtolower(trim((string) $t)), $tags),
            fn ($t) => isset($known[$t])
        )));
    }

    /**
     * Resolve tags into conversion links for the notice detail page:
     * a guide page when one exists, else the mock-test category.
     *
     * @return array<int, array{tag: string, label: string, guide_url: ?string, mock_test_url: ?string}>
     */
    public function links(array $tags): array
    {
        if (! $tags) {
            return [];
        }

        $guides = ExamGuide::published()->with('category:id,slug')->whereIn('slug', $tags)->get()->keyBy('slug');
        $fixed = config('notices.fixed_exam_tags');
        $labels = $this->all();

        $links = [];
        foreach ($tags as $tag) {
            $guide = $guides->get($tag);
            $mcqCategory = $fixed[$tag] ?? null;

            $links[] = [
                'tag' => $tag,
                'label' => $guide?->name ?? ($labels[$tag] ?? $tag),
                'guide_url' => $guide ? "/exams/{$guide->category->slug}/{$guide->slug}" : null,
                'mock_test_url' => $guide?->mock_test_url ?: ($mcqCategory ? "/find-mcq/{$mcqCategory}" : null),
            ];
        }

        return array_values(array_filter($links, fn ($l) => $l['guide_url'] || $l['mock_test_url']));
    }
}
