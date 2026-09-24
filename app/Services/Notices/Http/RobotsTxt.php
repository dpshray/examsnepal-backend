<?php

namespace App\Services\Notices\Http;

/**
 * Minimal robots.txt evaluator: picks the most specific user-agent group
 * (ours, else "*") and applies longest-match Allow/Disallow, with the `*`
 * and `$` wildcards Google documents.
 */
class RobotsTxt
{
    public function __construct(private readonly string $content) {}

    public function isAllowed(string $path, string $agent = 'ExamsNepalBot'): bool
    {
        $rules = $this->rulesFor(strtolower($agent));
        $path = $path === '' ? '/' : $path;

        $best = null;
        $bestLen = -1;
        foreach ($rules as [$type, $pattern]) {
            if ($pattern === '') {
                // "Disallow:" with no value allows everything.
                continue;
            }
            if ($this->matches($pattern, $path) && strlen($pattern) > $bestLen) {
                $best = $type;
                $bestLen = strlen($pattern);
            } elseif ($this->matches($pattern, $path) && strlen($pattern) === $bestLen && $type === 'allow') {
                $best = 'allow';
            }
        }

        return $best !== 'disallow';
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function rulesFor(string $agent): array
    {
        $groups = [];
        $currentAgents = [];
        $lastWasAgent = false;

        foreach (preg_split('/\R/', $this->content) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (! $lastWasAgent) {
                    $currentAgents = [];
                }
                $currentAgents[] = strtolower($value);
                $lastWasAgent = true;

                continue;
            }

            $lastWasAgent = false;
            if (in_array($field, ['allow', 'disallow'], true)) {
                foreach ($currentAgents as $ua) {
                    $groups[$ua][] = [$field, $value];
                }
            }
        }

        foreach (array_keys($groups) as $ua) {
            if ($ua !== '*' && str_contains($agent, $ua)) {
                return $groups[$ua];
            }
        }

        return $groups['*'] ?? [];
    }

    private function matches(string $pattern, string $path): bool
    {
        $regex = preg_quote($pattern, '#');
        $regex = str_replace('\*', '.*', $regex);
        if (str_ends_with($regex, '\$')) {
            $regex = substr($regex, 0, -2).'$';
        }

        return (bool) preg_match('#^'.$regex.'#', $path);
    }
}
