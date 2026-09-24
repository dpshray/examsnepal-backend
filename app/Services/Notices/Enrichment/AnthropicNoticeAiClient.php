<?php

namespace App\Services\Notices\Enrichment;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use RuntimeException;

class AnthropicNoticeAiClient extends NoticeAiClient
{
    private ?Client $client = null;

    public function extract(string $model, string $system, array $content, array $schema): array
    {
        try {
            $message = $this->client()->messages->create(
                model: $model,
                maxTokens: (int) config('notices.ai.max_tokens'),
                // The system prompt (rules + tag vocabulary) is identical across
                // notices, so it is cached; the notice content follows it.
                system: [
                    ['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']],
                ],
                messages: [['role' => 'user', 'content' => $content]],
                outputConfig: ['format' => ['type' => 'json_schema', 'schema' => $schema]],
            );
        } catch (RateLimitException|InternalServerException|APIConnectionException $e) {
            throw new TransientAiException($e->getMessage(), 0, $e);
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return [
            'data' => json_decode($text, true),
            'raw' => $text,
            'input_tokens' => $message->usage->inputTokens,
            'output_tokens' => $message->usage->outputTokens,
            'cache_read_tokens' => (int) ($message->usage->cacheReadInputTokens ?? 0),
            'stop_reason' => $message->stopReason,
            'cost' => null,
        ];
    }

    private function client(): Client
    {
        $key = config('notices.ai.anthropic.api_key');
        if (! $key) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured');
        }

        return $this->client ??= new Client(apiKey: $key);
    }
}
