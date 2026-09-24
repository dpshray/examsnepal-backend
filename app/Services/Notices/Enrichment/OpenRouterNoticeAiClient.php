<?php

namespace App\Services\Notices\Enrichment;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenRouter chat/completions (OpenAI-compatible) - one key for Gemini,
 * Claude and other models. https://openrouter.ai/docs
 */
class OpenRouterNoticeAiClient extends NoticeAiClient
{
    public function extract(string $model, string $system, array $content, array $schema): array
    {
        $key = config('notices.ai.openrouter.api_key');
        if (! $key) {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured');
        }

        $hasPdf = collect($content)->contains(fn ($b) => ($b['type'] ?? null) === 'document');
        $strict = config('notices.ai.openrouter.json_mode') !== 'json_object';

        if (! $strict) {
            // Plain JSON mode: the model only knows the shape from the prompt.
            $system .= "\n\nRespond with a single JSON object (no markdown, no commentary) that validates against this JSON schema. Every property is required; use null or [] when unknown:\n"
                .json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $body = [
            'model' => $model,
            'max_tokens' => (int) config('notices.ai.max_tokens'),
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $this->convertContent($content)],
            ],
            'response_format' => $strict
                ? ['type' => 'json_schema', 'json_schema' => ['name' => 'notice_extraction', 'strict' => true, 'schema' => $schema]]
                : ['type' => 'json_object'],
            // Only route to endpoints that honour response_format, and report
            // the real cost of each call.
            'provider' => ['require_parameters' => true],
            'usage' => ['include' => true],
        ];

        if ($hasPdf) {
            // Explicit engine - without one OpenRouter may fall back to paid OCR.
            $body['plugins'] = [['id' => 'file-parser', 'pdf' => ['engine' => config('notices.ai.openrouter.pdf_engine')]]];
        }

        try {
            $response = Http::withToken($key)
                ->withHeaders([
                    'HTTP-Referer' => config('notices.site_url'),
                    'X-Title' => 'ExamsNepal Notices',
                ])
                ->timeout(180)
                ->post(rtrim(config('notices.ai.openrouter.base_url'), '/').'/chat/completions', $body);
        } catch (ConnectionException $e) {
            throw new TransientAiException($e->getMessage(), 0, $e);
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new TransientAiException("OpenRouter HTTP {$response->status()}: ".mb_substr($response->body(), 0, 300));
        }
        if (! $response->successful()) {
            throw new RuntimeException("OpenRouter HTTP {$response->status()}: ".mb_substr($response->body(), 0, 500));
        }

        $json = $response->json();
        // OpenRouter can return 200 with an upstream error object.
        if (isset($json['error'])) {
            $code = (int) ($json['error']['code'] ?? 0);
            $message = 'OpenRouter error: '.($json['error']['message'] ?? 'unknown');
            throw ($code === 429 || $code >= 500) ? new TransientAiException($message) : new RuntimeException($message);
        }

        $text = (string) data_get($json, 'choices.0.message.content', '');
        // Some models wrap JSON in a markdown fence despite response_format.
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text));

        return [
            'data' => json_decode($clean, true),
            'raw' => $text,
            'input_tokens' => (int) data_get($json, 'usage.prompt_tokens', 0),
            'output_tokens' => (int) data_get($json, 'usage.completion_tokens', 0),
            'cache_read_tokens' => (int) data_get($json, 'usage.prompt_tokens_details.cached_tokens', 0),
            'stop_reason' => data_get($json, 'choices.0.finish_reason') === 'length' ? 'max_tokens' : data_get($json, 'choices.0.finish_reason'),
            'cost' => data_get($json, 'usage.cost') !== null ? (float) data_get($json, 'usage.cost') : null,
        ];
    }

    /** Anthropic-style blocks -> OpenAI/OpenRouter content parts. */
    private function convertContent(array $content): array
    {
        $parts = [];
        $pdfNo = 0;
        foreach ($content as $block) {
            $source = $block['source'] ?? [];
            $parts[] = match ($block['type']) {
                'document' => [
                    'type' => 'file',
                    'file' => [
                        'filename' => 'notice-'.(++$pdfNo).'.pdf',
                        'file_data' => 'data:application/pdf;base64,'.$source['data'],
                    ],
                ],
                'image' => [
                    'type' => 'image_url',
                    'image_url' => ['url' => "data:{$source['mediaType']};base64,{$source['data']}"],
                ],
                default => ['type' => 'text', 'text' => (string) ($block['text'] ?? '')],
            };
        }

        return $parts;
    }
}
