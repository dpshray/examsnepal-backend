<?php

namespace App\Services\Marketing\Insights;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Read-only calls to Google Analytics Data and Search Console APIs with the
 * service account in config('marketing.insights.google_credentials').
 */
class GoogleApi
{
    private const SCOPES = [
        'https://www.googleapis.com/auth/analytics.readonly',
        'https://www.googleapis.com/auth/webmasters.readonly',
    ];

    public static function hasCredentials(): bool
    {
        $path = config('marketing.insights.google_credentials');

        return $path && is_file($path);
    }

    public static function serviceAccountEmail(): ?string
    {
        if (! self::hasCredentials()) {
            return null;
        }
        $json = json_decode((string) file_get_contents(config('marketing.insights.google_credentials')), true);

        return $json['client_email'] ?? null;
    }

    /**
     * POST several JSON bodies concurrently.
     *
     * @param  array<string, array{0: string, 1: array}>  $requests  name => [url, body]
     * @return array<string, array> name => decoded response
     *
     * @throws RuntimeException with Google's error message on the first failure
     */
    public function postMany(array $requests): array
    {
        $token = $this->token();
        $responses = Http::pool(fn (Pool $pool) => collect($requests)->map(
            fn ($req, $name) => $pool->as($name)->withToken($token)->acceptJson()->timeout(30)->post($req[0], $req[1])
        )->all());

        $out = [];
        foreach ($responses as $name => $response) {
            if ($response instanceof \Throwable) {
                throw new RuntimeException('Could not reach Google: '.$response->getMessage());
            }
            if ($response->failed()) {
                throw new RuntimeException($response->json('error.message') ?: "Google API error {$response->status()}");
            }
            $out[$name] = $response->json() ?? [];
        }

        return $out;
    }

    private function token(): string
    {
        if (! self::hasCredentials()) {
            throw new RuntimeException('Google service account key not found at '.config('marketing.insights.google_credentials'));
        }

        // Tokens live an hour; refresh a little early.
        return Cache::remember('marketing:insights:google-token', 50 * 60, function () {
            $credentials = new ServiceAccountCredentials(self::SCOPES, config('marketing.insights.google_credentials'));
            $token = $credentials->fetchAuthToken()['access_token'] ?? null;
            if (! $token) {
                throw new RuntimeException('Google did not return an access token for the service account.');
            }

            return $token;
        });
    }
}
