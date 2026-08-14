<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Decodes a base64 data: URI (as sent back by the bulk-question-import
 * preview/edit UI, since parsed PHPWord Image objects can't survive a JSON
 * round-trip) into raw image bytes, validating that it actually is an image.
 */
class DataUriImage
{
    private const ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    private const MAX_BYTES = 2 * 1024 * 1024; // mirrors upload_max_filesize

    /**
     * @return array{mime: string, extension: string, binary: string}
     */
    public static function decode(string $dataUri): array
    {
        if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/s', trim($dataUri), $m)) {
            throw new InvalidArgumentException('Not a valid base64 image data URI.');
        }

        $mime = strtolower($m[1]);
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported image type: {$mime}.");
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false || $binary === '') {
            throw new InvalidArgumentException('Could not decode image data.');
        }

        if (strlen($binary) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Image exceeds the 2MB size limit.');
        }

        if (@getimagesizefromstring($binary) === false) {
            throw new InvalidArgumentException('Image data is corrupt or not a real image.');
        }

        $extension = explode('/', $mime)[1];
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        return ['mime' => $mime, 'extension' => $extension, 'binary' => $binary];
    }

    public static function isValid(?string $dataUri): bool
    {
        if ($dataUri === null || $dataUri === '') {
            return false;
        }

        try {
            self::decode($dataUri);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
