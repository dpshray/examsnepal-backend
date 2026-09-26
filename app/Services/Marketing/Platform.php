<?php

namespace App\Services\Marketing;

use hisorange\BrowserDetect\Facade as Browser;
use Illuminate\Http\Request;

/**
 * Resolves web | android | ios for the current request, using the same
 * user-agent rules as student registration. Apps may send an explicit
 * `X-Platform` header, which wins.
 */
class Platform
{
    public const WEB = 'web';
    public const ANDROID = 'android';
    public const IOS = 'ios';

    public static function fromRequest(?Request $request = null): string
    {
        $request ??= request();
        $header = strtolower((string) $request?->header('X-Platform'));
        if (in_array($header, [self::WEB, self::ANDROID, self::IOS], true)) {
            return $header;
        }

        try {
            if (Browser::isAndroid() || Browser::isTablet()) {
                return self::ANDROID;
            }
            if (Browser::platformFamily() === 'iOS') {
                return self::IOS;
            }
        } catch (\Throwable) {
            // console / queue context - no user agent
        }

        return self::WEB;
    }
}
