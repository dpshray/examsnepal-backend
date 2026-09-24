<?php

namespace App\Services\Notices\Enrichment;

/**
 * One structured-extraction call to an LLM. Implementations: OpenRouter
 * (default - one key for Gemini, Claude, ...) and the direct Anthropic API.
 * Selected by NOTICES_AI_PROVIDER (bound in AppServiceProvider).
 *
 * $content uses Anthropic-style blocks (text / document / image with base64
 * sources); each implementation converts them to its own wire format.
 */
abstract class NoticeAiClient
{
    /**
     * @param  array<int, array<string, mixed>>  $content
     * @return array{data: mixed, raw: string, input_tokens: int, output_tokens: int, cache_read_tokens: int, stop_reason: ?string, cost?: ?float}
     *
     * @throws TransientAiException
     */
    abstract public function extract(string $model, string $system, array $content, array $schema): array;

    /** False when NOTICES_AI_PROVIDER=none: notices are published as fetched. */
    public static function isEnabled(): bool
    {
        return config('notices.ai.provider') !== 'none';
    }

    public static function isConfigured(): bool
    {
        return match (config('notices.ai.provider')) {
            'openrouter' => (bool) config('notices.ai.openrouter.api_key'),
            'anthropic' => (bool) config('notices.ai.anthropic.api_key'),
            default => false,
        };
    }
}
