<?php

namespace App\Services\Notices\Enrichment;

/**
 * What gets sent to the model for one notice: extracted text, and/or raw
 * files (PDF/image) when the text layer is missing or unreadable.
 */
final class NoticeDocument
{
    /**
     * @param  array<int, array{media_type: string, data: string, url: string}>  $files  raw bytes
     */
    public function __construct(
        public readonly string $text = '',
        public readonly array $files = [],
        public readonly array $warnings = [],
    ) {}

    public function isScanned(): bool
    {
        return $this->files !== [];
    }

    /** Stable fingerprint of the content, used as the AI result cache key. */
    public function fingerprint(): string
    {
        $parts = [$this->text];
        foreach ($this->files as $file) {
            $parts[] = hash('sha256', $file['data']);
        }

        return hash('sha256', implode('|', $parts));
    }
}
