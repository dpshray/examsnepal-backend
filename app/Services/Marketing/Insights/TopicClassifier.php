<?php

namespace App\Services\Marketing\Insights;

/**
 * Buckets free text (search queries, Facebook posts) into exam topics and
 * search intents from config('marketing.insights.topics' / '.intents').
 */
class TopicClassifier
{
    /** @var array<string, string> key => regex */
    private array $topicPatterns;

    /** @var array<string, string> */
    private array $intentPatterns;

    public function __construct()
    {
        $this->topicPatterns = $this->compile(config('marketing.insights.topics', []));
        $this->intentPatterns = $this->compile(config('marketing.insights.intents', []));
    }

    public function topic(?string $text): ?string
    {
        return $this->first($this->topicPatterns, $text);
    }

    public function intent(?string $text): ?string
    {
        return $this->first($this->intentPatterns, $text);
    }

    public static function topicLabel(?string $key): string
    {
        return $key ? (config("marketing.insights.topics.$key.label") ?? $key) : 'Other / unclassified';
    }

    public static function intentLabel(?string $key): string
    {
        return $key ? (config("marketing.insights.intents.$key.label") ?? $key) : 'Other';
    }

    public static function isBrand(string $query): bool
    {
        $q = mb_strtolower($query);
        foreach (config('marketing.insights.brand_terms', []) as $term) {
            if (str_contains($q, $term)) {
                return true;
            }
        }

        return false;
    }

    private function first(array $patterns, ?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }
        $text = mb_strtolower($text);
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text)) {
                return $key;
            }
        }

        return null;
    }

    /** Short keywords (≤3 letters: "cee", "ha") match whole words only; longer ones match word starts ("pharma" → "pharmacy"). */
    private function compile(array $groups): array
    {
        $out = [];
        foreach ($groups as $key => $group) {
            $parts = array_map(function (string $kw) {
                $quoted = preg_quote(mb_strtolower($kw), '/');

                return mb_strlen($kw) <= 3 ? "(?<![\\p{L}\\p{N}])$quoted(?![\\p{L}\\p{N}])" : "(?<![\\p{L}\\p{N}])$quoted";
            }, $group['keywords'] ?? []);
            if ($parts) {
                $out[$key] = '/'.implode('|', $parts).'/u';
            }
        }

        return $out;
    }
}
