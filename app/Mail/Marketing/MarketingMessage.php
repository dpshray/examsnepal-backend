<?php

namespace App\Mail\Marketing;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\HtmlString;

/**
 * A rendered lifecycle/marketing email. Non-transactional mail carries RFC
 * 8058 one-click unsubscribe headers; every message carries its send id so a
 * bounce can be matched back to it.
 */
class MarketingMessage extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $htmlBody,
        public string $textBody,
        public int $sendId,
        public ?string $unsubscribeUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('marketing.mail.from_address'), config('marketing.mail.from_name')),
            subject: $this->subjectLine,
        );
    }

    public function headers(): Headers
    {
        $text = ['X-ExamsNepal-Send-Id' => (string) $this->sendId];
        if ($this->unsubscribeUrl) {
            $text['List-Unsubscribe'] = '<' . $this->unsubscribeUrl . '>, <mailto:' . config('marketing.mail.from_address') . '?subject=unsubscribe>';
            $text['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }
        return new Headers(text: $text);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->htmlBody, text: 'mail.marketing.text', with: ['plainText' => new HtmlString($this->textBody)]);
    }
}
