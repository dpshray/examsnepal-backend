<?php

namespace App\Services\Marketing;

/**
 * Reads a raw inbound email (RFC 5322 source) and decides whether it is a
 * delivery failure or a spam complaint about one of our messages.
 *
 * Understands:
 *  - RFC 3464 delivery status notifications (multipart/report;
 *    report-type=delivery-status) - what Exim, Gmail, Outlook send
 *  - RFC 5965 ARF complaint reports (report-type=feedback-report)
 *  - Exim's plain-text "Mail delivery failed" bounces as a fallback
 *
 * Our send id comes from the X-ExamsNepal-Send-Id header, which bounce
 * reports quote back in their returned-headers part.
 */
class BounceParser
{
    /**
     * @return array{type:'hard_bounce'|'soft_bounce'|'complaint', recipient:?string, status:?string, send_id:?int, diagnostic:?string}|null
     *   null when the message is not a bounce or complaint (a normal reply).
     */
    public function parse(string $raw): ?array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        [$headers] = explode("\n\n", $raw, 2) + [1 => ''];
        $headers = $this->unfold($headers);
        $contentType = strtolower($this->header($headers, 'Content-Type') ?? '');
        $from = strtolower($this->header($headers, 'From') ?? '');
        $subject = strtolower($this->header($headers, 'Subject') ?? '');

        $sendId = preg_match('/^X-ExamsNepal-Send-Id:\s*(\d+)/mi', $raw, $m) ? (int) $m[1] : null;

        if (str_contains($contentType, 'report-type=feedback-report') || preg_match('/^Feedback-Type:\s*abuse/mi', $raw)) {
            return [
                'type' => 'complaint',
                'recipient' => $this->firstEmail($this->field($raw, 'Original-Rcpt-To') ?? $this->field($raw, 'Removal-Recipient')),
                'status' => null,
                'send_id' => $sendId,
                'diagnostic' => 'spam complaint (ARF)',
            ];
        }

        if (str_contains($contentType, 'report-type=delivery-status') || preg_match('/^Action:\s*(failed|delayed)/mi', $raw)) {
            $action = strtolower($this->field($raw, 'Action') ?? '');
            if (!in_array($action, ['failed', 'delayed'], true)) {
                return null; // delivered/relayed/expanded notices
            }
            $status = $this->field($raw, 'Status');
            return [
                'type' => $action === 'failed' && str_starts_with((string) $status, '5') ? 'hard_bounce' : 'soft_bounce',
                'recipient' => $this->firstEmail($this->field($raw, 'Final-Recipient') ?? $this->field($raw, 'Original-Recipient')),
                'status' => $status,
                'send_id' => $sendId,
                'diagnostic' => $this->field($raw, 'Diagnostic-Code'),
            ];
        }

        $looksLikeBounce = str_contains($from, 'mailer-daemon') || str_contains($from, 'postmaster')
            || str_contains($subject, 'mail delivery failed') || str_contains($subject, 'undeliverable')
            || str_contains($subject, 'delivery status notification');
        if ($looksLikeBounce && preg_match('/could not be delivered to one or more of its recipients.*?\n\s*\n\s*([^\s<>]+@[^\s<>:]+)/si', $raw, $m)) {
            $permanent = preg_match('/\b5\d\d\b|permanent|does not exist|unknown user|no such user|user unknown/i', $raw);
            return [
                'type' => $permanent ? 'hard_bounce' : 'soft_bounce',
                'recipient' => strtolower(rtrim($m[1], '.')),
                'status' => preg_match('/\b([45]\.\d{1,3}\.\d{1,3})\b/', $raw, $s) ? $s[1] : null,
                'send_id' => $sendId,
                'diagnostic' => 'exim plain-text bounce',
            ];
        }

        return null;
    }

    private function unfold(string $headers): string
    {
        return preg_replace("/\n[ \t]+/", ' ', $headers);
    }

    private function header(string $headers, string $name): ?string
    {
        return preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $headers, $m) ? trim($m[1]) : null;
    }

    /** A "Name: value" field anywhere in the message (DSN / ARF parts). */
    private function field(string $raw, string $name): ?string
    {
        return preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $this->unfold($raw), $m) ? trim($m[1]) : null;
    }

    private function firstEmail(?string $value): ?string
    {
        return $value && preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $value, $m) ? strtolower($m[1]) : null;
    }
}
