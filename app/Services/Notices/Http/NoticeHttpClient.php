<?php

namespace App\Services\Notices\Http;

use App\Models\NoticeSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The only way the notices pipeline talks to external sites.
 *
 *  - identifies as ExamsNepalBot and honours robots.txt (cached 24h)
 *  - never has two requests in flight to one host, and spaces them by
 *    notices.http.per_host_delay_ms (cache lock, so safe across workers)
 *  - retries transient failures with exponential backoff
 *  - skips TLS verification only when the source explicitly opts in, and
 *    logs every time it does
 */
class NoticeHttpClient
{
    public function get(string $url, ?NoticeSource $source = null, array $headers = []): Response
    {
        $this->assertAllowedByRobots($url, $source);

        return $this->throttled($url, function () use ($url, $source, $headers) {
            $verify = $this->verifyOption($source);
            if ($verify === false) {
                Log::channel(config('logging.default'))->notice('notices: TLS verification disabled by source opt-in', [
                    'source_id' => $source?->id,
                    'url' => $url,
                ]);
            }

            return Http::withHeaders(array_merge([
                'User-Agent' => config('notices.http.user_agent'),
                'Accept-Language' => 'ne,en;q=0.8',
            ], $headers))
                ->withOptions(['verify' => $verify, 'allow_redirects' => ['max' => 5]])
                ->timeout(config('notices.http.timeout'))
                ->retry(
                    config('notices.http.retries'),
                    fn (int $attempt) => 1000 * (2 ** ($attempt - 1)),
                    fn ($e) => $e instanceof ConnectionException
                        || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)),
                    throw: true,
                )
                ->get($url)
                ->throw();
        });
    }

    /** Download a binary (PDF/image) with a size cap; returns [bytes, contentType]. */
    public function download(string $url, ?NoticeSource $source = null): array
    {
        $response = $this->get($url, $source, ['Accept' => '*/*']);
        $body = $response->body();

        if (strlen($body) > config('notices.http.max_download_bytes')) {
            throw new RuntimeException('Attachment exceeds size limit: '.$url);
        }

        return [$body, strtolower(strtok((string) $response->header('Content-Type'), ';') ?: '')];
    }

    public function isAllowedByRobots(string $url, ?NoticeSource $source = null): bool
    {
        $parts = parse_url($url);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        $robots = Cache::remember(
            'notices:robots:'.md5($origin),
            now()->addMinutes(config('notices.http.robots_cache_minutes')),
            function () use ($origin, $source) {
                try {
                    $response = $this->throttled($origin, fn () => Http::withHeaders(['User-Agent' => config('notices.http.user_agent')])
                        ->withOptions(['verify' => $this->verifyOption($source)])
                        ->timeout(15)
                        ->get($origin.'/robots.txt'));

                    // 4xx = no robots.txt = everything allowed; HTML error
                    // pages served with 200 are not robots files either.
                    if (! $response->successful() || str_contains(strtolower($response->header('Content-Type')), 'html')) {
                        return '';
                    }

                    return $response->body();
                } catch (\Throwable) {
                    return '';
                }
            }
        );

        return (new RobotsTxt($robots))->isAllowed($path);
    }

    /**
     * Many .gov.np servers send only their leaf certificate and rely on
     * browsers fetching the intermediate. Rather than disabling verification
     * we verify against the system CAs plus the intermediates shipped in
     * resources/certs/notices. Only an explicit per-source opt-out disables it.
     */
    public function verifyOption(?NoticeSource $source): bool|string
    {
        if ($source && ! $source->verify_ssl) {
            return false;
        }

        static $bundle = null;
        if ($bundle !== null) {
            return $bundle;
        }

        $extra = glob(resource_path('certs/notices/*.pem')) ?: [];
        $system = env('NOTICES_SYSTEM_CA_FILE') ?: (openssl_get_cert_locations()['default_cert_file'] ?? null);
        if (! $extra || ! $system || ! is_readable($system)) {
            return $bundle = true;
        }

        $target = storage_path('app/notices/ca-bundle.pem');
        $newest = max(array_map('filemtime', [...$extra, $system]));
        if (! is_file($target) || filemtime($target) < $newest) {
            @mkdir(dirname($target), 0755, true);
            $pem = file_get_contents($system);
            foreach ($extra as $file) {
                $pem .= "\n".file_get_contents($file);
            }
            file_put_contents($target, $pem, LOCK_EX);
        }

        return $bundle = $target;
    }

    private function assertAllowedByRobots(string $url, ?NoticeSource $source): void
    {
        if (! $this->isAllowedByRobots($url, $source)) {
            throw new RobotsDisallowedException("robots.txt disallows {$url}");
        }
    }

    private function throttled(string $url, callable $callback): mixed
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $delayMs = (int) config('notices.http.per_host_delay_ms');
        $lastKey = 'notices:host-last:'.$host;

        // Hold the host lock for the whole request so requests to one host
        // are strictly sequential, then enforce the gap after the previous one.
        return Cache::lock('notices:host:'.$host, 120)->block(90, function () use ($callback, $lastKey, $delayMs) {
            $last = (float) Cache::get($lastKey, 0);
            $waitMs = (int) (($last + $delayMs / 1000 - microtime(true)) * 1000);
            if ($waitMs > 0) {
                usleep($waitMs * 1000);
            }

            try {
                return $callback();
            } finally {
                Cache::put($lastKey, microtime(true), 300);
            }
        });
    }
}
