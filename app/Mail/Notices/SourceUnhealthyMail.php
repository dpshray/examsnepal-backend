<?php

namespace App\Mail\Notices;

use App\Models\NoticeSource;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SourceUnhealthyMail extends Mailable
{
    use Queueable;

    public function __construct(public NoticeSource $source) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[ExamsNepal Notices] Source unhealthy: {$this->source->name}");
    }

    public function content(): Content
    {
        $s = $this->source;

        return new Content(htmlString: nl2br(e(implode("\n", [
            "Source: {$s->name} (#{$s->id})",
            "List URL: {$s->list_url}",
            "Consecutive failures: {$s->consecutive_failures}",
            "Consecutive empty runs: {$s->consecutive_empty_runs}",
            'Last success: '.($s->last_success_at?->toDateTimeString() ?? 'never'),
            "Last error: {$s->last_error}",
            '',
            'Check the source in the admin panel (Notices → Sources → Test fetch) and update its selectors, or set it to manual.',
        ]))));
    }
}
