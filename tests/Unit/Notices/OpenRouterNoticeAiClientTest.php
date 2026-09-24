<?php

namespace Tests\Unit\Notices;

use App\Services\Notices\Enrichment\NoticeExtractionSchema;
use App\Services\Notices\Enrichment\OpenRouterNoticeAiClient;
use App\Services\Notices\Enrichment\TransientAiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenRouterNoticeAiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'notices.ai.openrouter.api_key' => 'sk-or-test',
            'notices.ai.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'notices.ai.openrouter.json_mode' => 'schema',
            'notices.ai.openrouter.pdf_engine' => 'native',
        ]);
    }

    private function content(): array
    {
        return [
            ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode('%PDF-1.4')]],
            ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => 'AAAA']],
            ['type' => 'text', 'text' => 'notice text'],
        ];
    }

    public function test_request_shape_and_response_parsing(): void
    {
        $payload = NoticeExtractionSchemaTest::validPayload();
        Http::fake(['openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => "```json\n".json_encode($payload)."\n```"], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 2100, 'completion_tokens' => 420, 'cost' => 0.00168],
        ])]);

        $result = (new OpenRouterNoticeAiClient())->extract('google/gemini-2.5-flash', 'system prompt', $this->content(), NoticeExtractionSchema::schema());

        $this->assertSame($payload, $result['data'], 'markdown fence stripped');
        $this->assertSame(2100, $result['input_tokens']);
        $this->assertSame(0.00168, $result['cost']);
        $this->assertSame('stop', $result['stop_reason']);

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            $parts = $body['messages'][1]['content'];

            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-or-test')
                && $body['model'] === 'google/gemini-2.5-flash'
                && $body['messages'][0] === ['role' => 'system', 'content' => 'system prompt']
                && $body['response_format']['type'] === 'json_schema'
                && $body['response_format']['json_schema']['strict'] === true
                && $body['provider']['require_parameters'] === true
                && $body['plugins'][0]['pdf']['engine'] === 'native'
                && $parts[0]['type'] === 'file' && str_starts_with($parts[0]['file']['file_data'], 'data:application/pdf;base64,')
                && $parts[1] === ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,AAAA']]
                && $parts[2] === ['type' => 'text', 'text' => 'notice text'];
        });
    }

    public function test_no_pdf_plugin_without_documents(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{}'], 'finish_reason' => 'length']], 'usage' => []])]);

        $result = (new OpenRouterNoticeAiClient())->extract('m', 's', [['type' => 'text', 'text' => 'x']], []);

        $this->assertSame('max_tokens', $result['stop_reason'], 'length maps to max_tokens so the enricher treats it as truncated');
        Http::assertSent(fn (Request $r) => ! isset($r->data()['plugins']));
    }

    public function test_transient_errors_are_retryable(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['error' => ['message' => 'rate limited']], 429)
            ->push('upstream down', 502)
            ->push(['error' => ['code' => 503, 'message' => 'provider overloaded']], 200)]);
        $client = new OpenRouterNoticeAiClient();

        foreach (range(1, 3) as $_) {
            try {
                $client->extract('m', 's', [['type' => 'text', 'text' => 'x']], []);
                $this->fail('expected TransientAiException');
            } catch (TransientAiException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_client_errors_are_not_retried(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'invalid model']], 400)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400');
        (new OpenRouterNoticeAiClient())->extract('nope/model', 's', [['type' => 'text', 'text' => 'x']], []);
    }

    public function test_missing_key(): void
    {
        config(['notices.ai.openrouter.api_key' => null]);
        $this->expectExceptionMessage('OPENROUTER_API_KEY is not configured');
        (new OpenRouterNoticeAiClient())->extract('m', 's', [], []);
    }

    public function test_json_object_mode_for_free_models(): void
    {
        config(['notices.ai.openrouter.json_mode' => 'json_object', 'notices.ai.openrouter.pdf_engine' => 'cloudflare-ai']);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{}'], 'finish_reason' => 'stop']], 'usage' => []])]);

        (new OpenRouterNoticeAiClient())->extract('google/gemma-4-31b-it:free', 'rules', $this->content(), NoticeExtractionSchema::schema());

        Http::assertSent(function (Request $r) {
            $body = $r->data();

            return $body['response_format'] === ['type' => 'json_object']
                && str_contains($body['messages'][0]['content'], '"application_deadline_bs"')
                && $body['plugins'][0]['pdf']['engine'] === 'cloudflare-ai';
        });
    }
}
