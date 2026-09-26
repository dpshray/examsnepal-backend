<?php

namespace App\Console\Commands\Marketing;

use App\Enums\PaymentStatusEnum;
use App\Services\Marketing\EventTracker;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Time-based events nobody's request triggers:
 *  - exam_abandoned: attempt started, not submitted after N hours
 *  - subscription_expired: a student's last paid day has passed and nothing newer is active
 */
class DetectLifecycleEvents extends Command
{
    protected $signature = 'marketing:detect-lifecycle-events {--days=2 : How far back to look}';

    protected $description = 'Log exam_abandoned and subscription_expired marketing events';

    public function handle(EventTracker $tracker): int
    {
        $days = (int) $this->option('days');

        $this->info(sprintf(
            'Logged %d exam_abandoned, %d subscription_expired event(s).',
            $this->abandonedExams($tracker, $days),
            $this->expiredSubscriptions($tracker, $days),
        ));

        return self::SUCCESS;
    }

    private function abandonedExams(EventTracker $tracker, int $days): int
    {
        $cutoff = now()->subHours((int) config('marketing.abandon_after_hours'));

        $attempts = DB::table('student_exams')
            ->whereNotNull('student_id')
            ->where('is_exam_completed', 0)
            ->whereBetween('created_at', [$cutoff->copy()->subDays($days), $cutoff])
            ->get(['id', 'student_id', 'exam_id', 'created_at']);

        $logged = 0;
        foreach ($attempts as $a) {
            if (!$tracker->has($a->student_id, EventTracker::EXAM_ABANDONED, propertyMatch: ['student_exam_id' => $a->id])) {
                $tracker->track($a->student_id, EventTracker::EXAM_ABANDONED, [
                    'student_exam_id' => $a->id,
                    'exam_id' => $a->exam_id,
                    'started_at' => $a->created_at,
                ]);
                $logged++;
            }
        }
        return $logged;
    }

    private function expiredSubscriptions(EventTracker $tracker, int $days): int
    {
        $today = today()->toDateString();

        // Latest paid end date per student that fell in the look-back window.
        $expired = DB::table('subscribers')
            ->where('payment_status', PaymentStatusEnum::PAYMENT_SUCCESS->value)
            ->where('status', 1)
            ->groupBy('student_profile_id')
            ->havingRaw('MAX(end_date) < ?', [$today])
            ->havingRaw('MAX(end_date) >= ?', [today()->subDays($days)->toDateString()])
            ->get(['student_profile_id', DB::raw('MAX(end_date) as ended_on')]);

        $logged = 0;
        foreach ($expired as $row) {
            if (!$tracker->has($row->student_profile_id, EventTracker::SUBSCRIPTION_EXPIRED, propertyMatch: ['ended_on' => $row->ended_on])) {
                $tracker->track($row->student_profile_id, EventTracker::SUBSCRIPTION_EXPIRED, [
                    'ended_on' => $row->ended_on,
                ], null, Carbon::parse($row->ended_on)->addDay());
                $logged++;
            }
        }
        return $logged;
    }
}
