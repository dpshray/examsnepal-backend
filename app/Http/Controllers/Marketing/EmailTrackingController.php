<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MessageSend;
use App\Services\Marketing\EventTracker;
use App\Services\Marketing\Suppressions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public, signed endpoints embedded in emails. Signatures stop people from
 * forging clicks/unsubscribes and stop the click endpoint being an open redirect.
 */
class EmailTrackingController extends Controller
{
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /** GET /e/o/{send} - 1x1 open pixel. */
    public function open(MessageSend $send): Response
    {
        if (!$send->opened_at) {
            $send->update([
                'opened_at' => now(),
                'status' => in_array($send->status, [MessageSend::SENT, MessageSend::DELIVERED], true) ? MessageSend::OPENED : $send->status,
            ]);
        }

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }

    /** GET /e/c/{send}?u=... - log the click, then redirect to the (UTM-tagged) destination. */
    public function click(Request $request, MessageSend $send)
    {
        $url = (string) $request->query('u');
        abort_unless(preg_match('#^https?://#i', $url), 404);

        $first = !$send->clicked_at;
        $send->update([
            'clicked_at' => $send->clicked_at ?? now(),
            'opened_at' => $send->opened_at ?? now(), // a click proves an open (images may be blocked)
            'status' => in_array($send->status, [MessageSend::SENT, MessageSend::DELIVERED, MessageSend::OPENED], true) ? MessageSend::CLICKED : $send->status,
        ]);
        if ($first) {
            app(EventTracker::class)->track($send->student_id, EventTracker::EMAIL_CLICKED, [
                'send_id' => $send->id,
                'automation_id' => $send->automation_id,
                'url' => mb_substr($url, 0, 500),
            ]);
        }

        return redirect()->away($url);
    }

    /** GET /e/u/{send} - one click from the footer link: unsubscribes immediately, offers undo. */
    public function unsubscribe(MessageSend $send)
    {
        Suppressions::unsubscribeStudent($send->student_id, "link in send #{$send->id}");
        $send->update(['unsubscribed_at' => $send->unsubscribed_at ?? now()]);

        return response()->view('marketing.unsubscribed', ['send' => $send, 'resubscribed' => false]);
    }

    /** POST /e/u/{send} - RFC 8058 List-Unsubscribe-Post from the mail client. */
    public function unsubscribePost(MessageSend $send)
    {
        Suppressions::unsubscribeStudent($send->student_id, "List-Unsubscribe header, send #{$send->id}");
        $send->update(['unsubscribed_at' => $send->unsubscribed_at ?? now()]);

        return response('Unsubscribed', 200);
    }

    /** POST /e/r/{send} - undo from the confirmation page. */
    public function resubscribe(MessageSend $send)
    {
        Suppressions::resubscribeStudent($send->student_id);
        $send->update(['unsubscribed_at' => null]);

        return response()->view('marketing.unsubscribed', ['send' => $send, 'resubscribed' => true]);
    }
}
