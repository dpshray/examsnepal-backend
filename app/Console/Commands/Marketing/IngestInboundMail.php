<?php

namespace App\Console\Commands\Marketing;

use App\Models\Marketing\MessageSend;
use App\Services\Marketing\BounceParser;
use App\Services\Marketing\Suppressions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Receives one raw email on stdin (cPanel forwarder "Pipe to a Program" on
 * info@examsnepal.com) or from --file, and records bounces/complaints:
 *  - hard bounce / complaint -> address suppressed immediately
 *  - soft bounce -> suppressed after N within 30 days
 * An email whose subject is "unsubscribe" (the List-Unsubscribe mailto:
 * option) unsubscribes the sender. Ordinary replies are ignored. Always
 * exits 0 so Exim never bounces the bounce back.
 */
class IngestInboundMail extends Command
{
    protected $signature = 'marketing:ingest-mail {--file= : Read the message from a file instead of stdin}';

    protected $description = 'Record a bounce, spam complaint or mailto unsubscribe from a raw inbound email';

    public function handle(BounceParser $parser): int
    {
        try {
            $raw = $this->option('file') ? (string) file_get_contents($this->option('file')) : (string) stream_get_contents(STDIN);
            $result = $parser->parse($raw);
            if (!$result) {
                $this->mailtoUnsubscribe($raw);
                return self::SUCCESS;
            }

            $send = $result['send_id'] ? MessageSend::find($result['send_id']) : null;
            $email = $result['recipient'] ?? $send?->to_address;
            if (!$email) {
                $this->warn('Bounce without a recognisable recipient; ignored.');
                return self::SUCCESS;
            }

            if ($send) {
                $send->update([
                    'status' => MessageSend::BOUNCED,
                    'bounced_at' => now(),
                    'error' => mb_substr(trim(($result['status'] ?? '') . ' ' . ($result['diagnostic'] ?? '')), 0, 1000),
                ]);
            }

            match ($result['type']) {
                'hard_bounce' => Suppressions::add($email, Suppressions::HARD_BOUNCE, $result['status'] . ' ' . $result['diagnostic']),
                'complaint' => Suppressions::add($email, Suppressions::COMPLAINT, $result['diagnostic']),
                'soft_bounce' => $this->softBounce($email, $result),
            };

            $this->info("Recorded {$result['type']} for {$email}.");
        } catch (\Throwable $e) {
            Log::error('Inbound mail ingestion failed: ' . $e->getMessage());
            $this->error($e->getMessage());
        }

        return self::SUCCESS;
    }

    /** The List-Unsubscribe mailto: option - an email whose subject is "unsubscribe". */
    private function mailtoUnsubscribe(string $raw): void
    {
        [$headers] = explode("\n\n", str_replace("\r\n", "\n", $raw), 2) + [1 => ''];
        $subject = preg_match('/^Subject:\s*(.+)$/mi', $headers, $m) ? strtolower(trim($m[1])) : '';
        $from = preg_match('/^From:.*?([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/mi', $headers, $m) ? strtolower($m[1]) : null;

        if ($from && preg_match('/^(re:\s*)?unsubscribe\b/', $subject)) {
            $studentId = DB::table('student_profiles')->whereRaw('LOWER(email) = ?', [$from])->value('id');
            $studentId ? Suppressions::unsubscribeStudent($studentId, 'mailto unsubscribe') : Suppressions::add($from, Suppressions::UNSUBSCRIBED, 'mailto unsubscribe');
            $this->info("Unsubscribed {$from} (mailto).");
            return;
        }
        $this->line('Not a bounce, complaint or unsubscribe; ignored.');
    }

    private function softBounce(string $email, array $result): void
    {
        $recent = MessageSend::query()->where('to_address', $email)
            ->where('status', MessageSend::BOUNCED)
            ->where('bounced_at', '>=', now()->subDays(30))
            ->count();
        if ($recent >= (int) config('marketing.soft_bounce_limit')) {
            Suppressions::add($email, Suppressions::HARD_BOUNCE, "{$recent} soft bounces in 30 days; last: {$result['status']}");
        }
    }
}
