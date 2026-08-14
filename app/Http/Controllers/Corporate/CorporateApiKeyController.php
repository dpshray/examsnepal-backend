<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class CorporateApiKeyController extends Controller
{
    const SANDBOX_TEST_KEY = 'en_test_sandbox_demo_key';

    /**
     * Get or initialize the institute's API secret key.
     */
    public function show(Request $request)
    {
        $user = Auth::guard('users')->user();
        if (!$user) {
            return Response::apiError('Unauthorized', 401);
        }

        $apiKey = $user->ensureApiSecretKey();

        return Response::apiSuccess('Institute API key retrieved', [
            'api_secret_key' => $apiKey,
            'api_secret_key_rotated_at' => $user->api_secret_key_rotated_at,
            'sandbox_test_key' => self::SANDBOX_TEST_KEY,
            'institute_slug' => $user->slug ?: $user->username,
            'docs_url' => 'https://www.examsnepal.com/institute-api',
        ]);
    }

    /**
     * Regenerate / rotate the institute's API secret key.
     */
    public function regenerate(Request $request)
    {
        $user = Auth::guard('users')->user();
        if (!$user) {
            return Response::apiError('Unauthorized', 401);
        }

        $newKey = $user->generateApiSecretKey();

        return Response::apiSuccess('API secret key regenerated successfully. Your previous key has been invalidated.', [
            'api_secret_key' => $newKey,
            'api_secret_key_rotated_at' => $user->api_secret_key_rotated_at,
            'sandbox_test_key' => self::SANDBOX_TEST_KEY,
            'institute_slug' => $user->slug ?: $user->username,
            'docs_url' => 'https://www.examsnepal.com/institute-api',
        ]);
    }
}
