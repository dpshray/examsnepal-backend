<?php

namespace App\Mail\Notices;

use App\Models\NoticeSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class NoticeDigestMail extends Mailable
{
    use Queueable;

    public function __construct(public NoticeSubscription $subscription, public Collection $notices) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->notices->count()} new exam & vacancy notice(s) - ExamsNepal");
    }

    public function content(): Content
    {
        $site = config('notices.site_url');
        $rows = $this->notices->map(function ($n) use ($site) {
            $deadline = $n->application_deadline_ad ? ' — apply by '.$n->application_deadline_ad->format('M j, Y') : '';

            return '<li style="margin-bottom:12px"><a href="'.e("{$site}/notices/{$n->slug}").'">'.e($n->displayTitle()).'</a><br>'
                .'<small>'.e($n->organization).e($deadline).'</small></li>';
        })->implode('');

        $unsubscribe = e(url('/api/free/notices/unsubscribe/'.$this->subscription->unsubscribe_token));

        return new Content(htmlString: <<<HTML
<p>New official notices matching your alerts:</p>
<ul style="padding-left:18px">{$rows}</ul>
<p style="color:#666;font-size:12px">Compiled from official sources - always confirm details in the official notice. ExamsNepal is not affiliated with the listed organizations.<br>
<a href="{$unsubscribe}">Unsubscribe</a></p>
HTML);
    }
}
