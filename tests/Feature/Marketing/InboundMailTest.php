<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\MessageSend;
use App\Services\Marketing\BounceParser;
use Illuminate\Support\Facades\DB;

class InboundMailTest extends MarketingDatabaseTestCase
{
    private function dsn(string $recipient, string $action, string $status, int $sendId = 0): string
    {
        return <<<MAIL
From: Mail Delivery System <Mailer-Daemon@saphire.mysecurecloudserver.com>
To: info@examsnepal.com
Subject: Mail delivery failed: returning message to sender
Content-Type: multipart/report; report-type=delivery-status; boundary="b1"

--b1
Content-Type: text/plain

This message was created automatically by mail delivery software.

--b1
Content-Type: message/delivery-status

Reporting-MTA: dns; saphire.mysecurecloudserver.com

Action: {$action}
Final-Recipient: rfc822;{$recipient}
Status: {$status}
Diagnostic-Code: smtp; 550 5.1.1 The email account that you tried to reach does not exist.

--b1
Content-Type: text/rfc822-headers

From: ExamsNepal <info@examsnepal.com>
To: {$recipient}
Subject: Your weekly progress
X-ExamsNepal-Send-Id: {$sendId}

--b1--
MAIL;
    }

    private function sendTo(string $email): MessageSend
    {
        $id = $this->student(['email' => $email]);
        return MessageSend::create([
            'student_id' => $id, 'template_key' => 'x', 'status' => MessageSend::SENT,
            'to_address' => $email, 'sent_at' => now(),
        ]);
    }

    private function ingest(string $raw): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mail');
        file_put_contents($path, $raw);
        $this->artisan('marketing:ingest-mail', ['--file' => $path])->assertSuccessful();
        unlink($path);
    }

    public function test_parser_classifies_reports(): void
    {
        $p = new BounceParser();

        $hard = $p->parse($this->dsn('Gone@Example.com', 'failed', '5.1.1', 42));
        $this->assertSame(['type' => 'hard_bounce', 'recipient' => 'gone@example.com', 'status' => '5.1.1', 'send_id' => 42], array_slice($hard, 0, 4));

        $this->assertSame('soft_bounce', $p->parse($this->dsn('full@example.com', 'failed', '4.2.2'))['type']);
        $this->assertSame('soft_bounce', $p->parse($this->dsn('slow@example.com', 'delayed', '4.4.7'))['type']);
        $this->assertNull($p->parse($this->dsn('ok@example.com', 'delivered', '2.0.0')));

        $arf = "From: feedback@gmail.com\nSubject: complaint\nContent-Type: multipart/report; report-type=feedback-report; boundary=x\n\n--x\nContent-Type: message/feedback-report\n\nFeedback-Type: abuse\nOriginal-Rcpt-To: <angry@gmail.com>\n--x--\n";
        $this->assertSame(['complaint', 'angry@gmail.com'], [$p->parse($arf)['type'], $p->parse($arf)['recipient']]);

        $exim = "From: Mail Delivery System <Mailer-Daemon@host>\nSubject: Mail delivery failed: returning message to sender\n\nThis message was created automatically by mail delivery software.\n\nA message that you sent could not be delivered to one or more of its recipients. This is a permanent error. The following address(es) failed:\n\n  nobody@example.org\n    host mx.example.org said: 550 No such user\n";
        $this->assertSame(['hard_bounce', 'nobody@example.org'], [$p->parse($exim)['type'], $p->parse($exim)['recipient']]);

        $this->assertNull($p->parse("From: student@gmail.com\nSubject: Re: Your weekly progress\n\nThanks!"));
    }

    public function test_hard_bounce_suppresses_and_marks_the_send(): void
    {
        $send = $this->sendTo('gone@example.com');
        $this->ingest($this->dsn('gone@example.com', 'failed', '5.1.1', $send->id));

        $send->refresh();
        $this->assertSame(MessageSend::BOUNCED, $send->status);
        $this->assertNotNull($send->bounced_at);
        $this->assertSame('hard_bounce', DB::table('suppressions')->where('email', 'gone@example.com')->value('reason'));
    }

    public function test_soft_bounces_suppress_only_after_the_limit(): void
    {
        foreach (range(1, 3) as $i) {
            $send = $this->sendTo($i === 1 ? 'full@example.com' : 'full@example.com');
            $this->ingest($this->dsn('full@example.com', 'failed', '4.2.2', $send->id));
            $this->assertSame($i === 3, DB::table('suppressions')->where('email', 'full@example.com')->exists(), "after {$i} soft bounces");
        }
    }

    public function test_complaint_suppresses(): void
    {
        $this->ingest("From: fbl@example.net\nContent-Type: multipart/report; report-type=feedback-report; boundary=x\n\n--x\n\nFeedback-Type: abuse\nOriginal-Rcpt-To: angry@gmail.com\n--x--\n");
        $this->assertSame('complaint', DB::table('suppressions')->where('email', 'angry@gmail.com')->value('reason'));
    }

    public function test_mailto_unsubscribe_and_normal_replies(): void
    {
        $id = $this->student(['email' => 'Maya@Example.com']);
        $this->ingest("From: Maya <maya@example.com>\nSubject: unsubscribe\n\n");
        $this->assertFalse((bool) DB::table('student_profiles')->where('id', $id)->value('marketing_email_opt_in'));

        $this->ingest("From: someone@example.com\nSubject: Question about MDMS mocks\n\nHello");
        $this->assertSame(1, DB::table('suppressions')->count());
    }
}
