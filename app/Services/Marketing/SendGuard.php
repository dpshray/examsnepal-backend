<?php

namespace App\Services\Marketing;

use App\Models\Marketing\Automation;
use App\Models\Marketing\MessageSend;
use App\Services\Marketing\Channels\ChannelRouter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every rule that can stop a message, in one place. The dry-run preview, the
 * hourly planner and the dispatcher all call this, so "who would receive it"
 * and "who actually receives it" can't drift apart.
 *
 * Returns null when the message may go, otherwise a short reason code that is
 * stored on the suppressed message_sends row.
 */
class SendGuard
{
    /** Bulk-mode caches (see prime()); null = look things up per student. */
    private ?array $primedIds = null;
    private array $suppressed = [];
    private array $paying = [];
    private array $cooldown = [];
    private array $sentByStudent = [];
    private array $queuedByStudent = [];

    public function __construct(private CarbonInterface $now) {}

    /**
     * Bulk mode for the planner, dry runs and broadcasts: load everything the
     * checks need for these students in a few chunked queries, instead of
     * several queries per student (19s -> ~1s for 50k students). The
     * students were selected with the automation's own conditions in the
     * same instant, so those aren't re-queried here. Dispatch of a single
     * queued message never uses this and keeps the exact per-student checks.
     *
     * @param Collection<int, object> $students rows with id and email
     */
    public function prime(Collection $students, ?Automation $automation): void
    {
        $this->primedIds = $students->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
        $emails = $students->pluck('email')->filter()->map(fn ($e) => strtolower(trim($e)))->unique()->values();
        foreach ($emails->chunk(5000) as $chunk) {
            foreach (DB::table('suppressions')->whereIn('email', $chunk->all())->pluck('email') as $e) {
                $this->suppressed[$e] = true;
            }
        }

        foreach (array_chunk(array_keys($this->primedIds), 5000) as $ids) {
            if ($automation?->is_upsell) {
                foreach (DB::table('student_metrics')->whereIn('student_id', $ids)->whereIn('subscription_status', ['active', 'expiring_soon'])->pluck('student_id') as $id) {
                    $this->paying[$id] = true;
                }
            }
            if ($automation) {
                $cooldown = MessageSend::query()->where('automation_id', $automation->id)->whereIn('student_id', $ids)
                    ->where(fn ($q) => $q->where('status', MessageSend::QUEUED)
                        ->orWhere(fn ($w) => $w->whereIn('status', MessageSend::DELIVERED_STATUSES)
                            ->where('sent_at', '>=', $this->now->copy()->subDays($automation->cooldown_days))))
                    ->pluck('student_id');
                foreach ($cooldown as $id) {
                    $this->cooldown[$id] = true;
                }
            }
            $sent = DB::table('message_sends')->whereIn('student_id', $ids)->where('category', '!=', 'transactional')
                ->whereIn('status', MessageSend::DELIVERED_STATUSES)->where('sent_at', '>=', $this->now->copy()->subDays(7))
                ->get(['student_id', 'channel', 'sent_at']);
            foreach ($sent as $row) {
                $this->sentByStudent[$row->student_id][] = [$row->channel, CarbonImmutable::parse($row->sent_at)];
            }
            $queued = DB::table('message_sends as s')->leftJoin('automations as a', 'a.id', '=', 's.automation_id')
                ->whereIn('s.student_id', $ids)->where('s.category', '!=', 'transactional')->where('s.status', MessageSend::QUEUED)
                ->get(['s.student_id', 's.channel', 's.automation_id', 'a.priority']);
            foreach ($queued as $row) {
                $this->queuedByStudent[$row->student_id][] = $row;
            }
        }
    }

    /** Suppressed addresses when primed (for ChannelRouter), else null = query the table. */
    public function suppressedSet(): ?array
    {
        return $this->primedIds === null ? null : $this->suppressed;
    }

    private function primed(int $studentId): bool
    {
        return $this->primedIds !== null && isset($this->primedIds[$studentId]);
    }

    /**
     * @param string $category transactional | lifecycle | promotional
     * @param ?MessageSend $send the queued row being dispatched (null when planning)
     * @param string $channel the concrete channel (email | push | sms), see ChannelRouter
     */
    public function check(object $student, string $category, ?Automation $automation = null, ?MessageSend $send = null, string $channel = ChannelRouter::EMAIL): ?string
    {
        $transactional = $category === 'transactional';

        if ($reason = ChannelRouter::blocker($channel, $student, $this->primed($student->id) ? $this->suppressed : null)) {
            return $reason;
        }
        // A marketing opt-out covers every channel, not just email.
        if (!$transactional && (!($student->marketing_email_opt_in ?? true) || $student->unsubscribed_at)) {
            return 'unsubscribed';
        }
        // Never verified and never used the product: likely a typo or dead
        // address, and bounces hurt the domain's reputation.
        if (!$transactional && $channel === ChannelRouter::EMAIL
            && empty($student->email_verified_at) && empty($student->last_active_at) && (int) ($student->total_attempts ?? 0) === 0) {
            return 'unverified_inactive';
        }
        if ($automation) {
            if (!$automation->is_active) {
                return 'automation_inactive';
            }
            if ($automation->is_upsell && $this->isPaying($student->id)) {
                return 'paying_student';
            }
            // Primed students were just selected by these same conditions.
            if (!($this->primed($student->id) && !$send) && !$this->conditionsHold($automation, $student->id)) {
                return 'conditions_not_met';
            }
            if ($send && $this->goalMetSince($automation, $student->id, $send->created_at)) {
                return 'goal_already_met';
            }
            if (!$send && $this->inCooldown($automation, $student->id)) {
                return 'cooldown';
            }
        }
        // Without an automation this is a broadcast, which ranks at the broadcast priority.
        $priority = $automation?->priority ?? (int) config('marketing.broadcast_priority');
        if (!$transactional && $this->overFrequencyCap($student->id, $priority, $send?->id, $channel)) {
            return 'frequency_cap';
        }

        return null;
    }

    public function conditionsHold(Automation $automation, int $studentId): bool
    {
        return StudentFilter::apply(StudentFilter::base(), $automation->conditions ?? [])
            ->where('m.student_id', $studentId)
            ->exists();
    }

    private function isPaying(int $studentId): bool
    {
        if ($this->primed($studentId)) {
            return isset($this->paying[$studentId]);
        }
        return DB::table('student_metrics')->where('student_id', $studentId)
            ->whereIn('subscription_status', ['active', 'expiring_soon'])->exists();
    }

    /** The student already did what the message asks for, after it was triggered. */
    private function goalMetSince(Automation $automation, int $studentId, CarbonInterface $since): bool
    {
        // Strictly after the trigger: an event in the same second belongs to the trigger, not the goal.
        return $automation->goal_event
            && app(EventTracker::class)->has($studentId, $automation->goal_event, $since->copy()->addSecond(), $automation->goal_properties ?: null);
    }

    /** Sent (or queued) this automation to the student within its cooldown. */
    private function inCooldown(Automation $automation, int $studentId): bool
    {
        if ($this->primed($studentId)) {
            return isset($this->cooldown[$studentId]);
        }
        return MessageSend::query()
            ->where('automation_id', $automation->id)
            ->where('student_id', $studentId)
            ->where(fn ($q) => $q->where('status', MessageSend::QUEUED)
                ->orWhere(fn ($w) => $w->whereIn('status', MessageSend::DELIVERED_STATUSES)
                    ->where('sent_at', '>=', $this->now->copy()->subDays($automation->cooldown_days))))
            ->exists();
    }

    /**
     * Per channel (config marketing.caps): email max 1 per 48h and 3 per 7
     * days. Messages already sent always count; queued ones count only if they
     * outrank this one, so a higher-priority message can still claim the slot
     * (the dispatcher then drops the lower one). Queued "auto" messages may
     * land on any channel, so they count for all.
     */
    private function overFrequencyCap(int $studentId, int $priority, ?int $exceptSendId, string $channel): bool
    {
        $caps = config("marketing.caps.{$channel}") ?? config('marketing.caps.email');
        $since7d = $this->now->copy()->subDays(7);
        $since48h = $this->now->copy()->subHours($caps['min_hours_between']);

        if ($this->primed($studentId) && $exceptSendId === null) {
            $sent = collect($this->sentByStudent[$studentId] ?? [])->filter(fn ($s) => $s[0] === $channel)->map(fn ($s) => $s[1]);
            $broadcastPriority = (int) config('marketing.broadcast_priority');
            $reserved = collect($this->queuedByStudent[$studentId] ?? [])
                ->filter(fn ($q) => in_array($q->channel, [$channel, ChannelRouter::AUTO], true))
                ->filter(fn ($q) => $q->automation_id ? (int) $q->priority > $priority : $broadcastPriority > $priority)
                ->count();
            if ($sent->contains(fn ($at) => $at->gte($since48h)) || $reserved > 0) {
                return true;
            }
            return $sent->count() + $reserved >= $caps['max_per_7_days'];
        }

        $sent = MessageSend::query()
            ->where('student_id', $studentId)
            ->where('channel', $channel)
            ->where('category', '!=', 'transactional')
            ->whereIn('status', MessageSend::DELIVERED_STATUSES)
            ->where('sent_at', '>=', $since7d)
            ->pluck('sent_at');

        $reserved = MessageSend::query()
            ->from('message_sends as s')
            ->leftJoin('automations as a', 'a.id', '=', 's.automation_id')
            ->where('s.student_id', $studentId)
            ->where('s.category', '!=', 'transactional')
            ->where('s.status', MessageSend::QUEUED)
            ->whereIn('s.channel', [$channel, ChannelRouter::AUTO])
            ->when($exceptSendId, fn ($q) => $q->where('s.id', '!=', $exceptSendId))
            ->where(fn ($q) => $q->where('a.priority', '>', $priority)
                ->when((int) config('marketing.broadcast_priority') > $priority, fn ($w) => $w->orWhereNull('s.automation_id')))
            ->count();

        if ($sent->contains(fn ($at) => $at->gte($since48h)) || $reserved > 0) {
            return true;
        }
        return $sent->count() + $reserved >= $caps['max_per_7_days'];
    }
}
