<?php

namespace App\Services\Marketing;

use App\Mail\Marketing\MarketingMessage;
use App\Models\Marketing\Automation;
use App\Models\Marketing\Broadcast;
use App\Models\Marketing\EmailTemplate;
use App\Models\Marketing\MessageSend;
use App\Services\Marketing\Channels\ChannelRouter;
use App\Services\Marketing\Channels\PushSender;
use App\Services\Marketing\Channels\SmsGateway;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lifecycle messaging engine.
 *
 *  - Event automations: a tracked event queues a message_sends row, due after
 *    the automation's delay (moved to the next send window).
 *  - Scheduled automations: plan() scans student_metrics hourly for students
 *    matching the conditions and queues them.
 *  - dispatch() runs every minute: re-checks every guard against fresh
 *    metrics, keeps only the highest-priority message per student, and sends.
 *
 * preview() is the dry run: the same candidate query and the same SendGuard,
 * without writing anything.
 */
class AutomationEngine
{
    private CarbonImmutable $now;

    public function __construct(?CarbonInterface $now = null)
    {
        $this->now = CarbonImmutable::instance($now ?? now());
    }

    // ------------------------------------------------------------ triggers

    /** Called by EventTracker for every event: queue event automations, attribute goals. */
    public function onEvent(int $studentId, string $event, array $properties, CarbonInterface $at): void
    {
        $this->attributeGoal($studentId, $event, $properties, $at);

        $automations = Automation::query()->where('is_active', true)
            ->where('trigger_type', Automation::TRIGGER_EVENT)
            ->where('trigger_event', $event)
            ->get()
            ->filter(fn (Automation $a) => Automation::propertiesMatch($a->trigger_properties, $properties));

        foreach ($automations as $automation) {
            // Every guard runs at dispatch, against fresh metrics; here we only avoid duplicates.
            if ($this->queuedOrRecent($automation, $studentId)) {
                continue;
            }
            $this->queue($automation, $studentId, $properties, CarbonImmutable::instance($at)->addMinutes($automation->delay_minutes));
        }
    }

    /** Hourly: queue scheduled automations for everyone who qualifies now. Returns count queued. */
    public function plan(): int
    {
        $queued = 0;
        foreach ($this->scheduledAutomations() as $automation) {
            if (!$automation->isDueAt($this->now)) {
                continue;
            }
            $studentIds = $this->evaluate($automation)->where('decision', 'send')->pluck('student_id');
            $category = $this->categoryOf($automation);
            $this->insertQueued($studentIds->map(function (int $studentId) use ($automation, $category) {
                $variant = $automation->variantFor($studentId);
                return array_replace($this->queuedRow($studentId, $category, $automation->templateKeyFor($variant), $automation->channel), [
                    'automation_id' => $automation->id,
                    'variant' => $variant,
                    'goal_event' => $automation->goal_event,
                ]);
            }));
            $queued += $studentIds->count();
        }
        return $queued;
    }

    /**
     * Dry run: for each active scheduled automation, who would be queued right
     * now and why the rest would be skipped.
     *
     * @return array<int, array{automation:string, candidates:int, would_send:int, skipped:array<string,int>, sample:array}>
     */
    public function preview(?Automation $only = null, int $sampleSize = 20): array
    {
        $automations = $only ? collect([$only]) : $this->scheduledAutomations();
        $out = [];
        foreach ($automations as $automation) {
            $rows = $this->evaluate($automation, ignoreActive: true);
            $send = $rows->where('decision', 'send');
            $out[] = [
                'automation' => $automation->key,
                'trigger_type' => $automation->trigger_type,
                'candidates' => $rows->count(),
                'would_send' => $send->count(),
                'skipped' => $rows->where('decision', '!=', 'send')->countBy('decision')->all(),
                'sample' => $send->take($sampleSize)->values()->all(),
            ];
        }
        return $out;
    }

    // ------------------------------------------------------------ dispatch

    /** Every minute: send what's due. Returns counts by outcome. */
    public function dispatch(int $limit = 200): array
    {
        $stats = ['sent' => 0, 'suppressed' => 0, 'failed' => 0, 'deferred' => 0];
        $paused = MarketingSettings::paused();
        $windowOpen = SendWindow::isOpen($this->now);
        if (!$paused) {
            $this->releaseBroadcasts();
        }

        // Stale messages (e.g. held back while paused) are dropped, not sent late.
        $stale = MessageSend::query()->where('status', MessageSend::QUEUED)
            ->where('scheduled_for', '<', $this->now->subHours((int) config('marketing.stale_after_hours')))
            ->update(['status' => MessageSend::SUPPRESSED, 'suppress_reason' => 'expired', 'updated_at' => $this->now]);
        $stats['suppressed'] += $stale;

        $due = MessageSend::query()->with('automation')
            ->where('status', MessageSend::QUEUED)
            ->where('scheduled_for', '<=', $this->now)
            ->when($paused || !$windowOpen, fn ($q) => $q->where('category', 'transactional'))
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();
        if ($due->isEmpty()) {
            return $stats;
        }

        (new StudentMetricsCalculator($this->now))->refresh($due->pluck('student_id')->unique()->values()->all());
        $budget = $this->hourlyBudget();

        foreach ($due->groupBy('student_id') as $studentId => $sends) {
            $student = $this->student((int) $studentId);
            // Highest priority first; once one goes out the rest are dropped.
            $ordered = $sends->sortByDesc(fn (MessageSend $s) => [$s->category === 'transactional' ? 1 : 0, $this->priorityOf($s), -$s->id]);
            $sentOne = false;
            foreach ($ordered as $send) {
                if ($sentOne && $send->category !== 'transactional') {
                    $this->suppress($send, 'lower_priority');
                    $stats['suppressed']++;
                    continue;
                }
                [$channel, $reason] = $student
                    ? ChannelRouter::pick($send->channel, (bool) $send->automation?->allow_sms, $student)
                    : [null, 'no_student'];
                $reason ??= (new SendGuard($this->now))->check($student, $send->category, $send->automation, $send, $channel);
                if ($reason) {
                    $this->suppress($send, $reason);
                    $stats['suppressed']++;
                    continue;
                }
                if ($channel !== $send->channel) {
                    $send->channel = $channel; // "auto" resolved to a concrete channel
                }
                if ($budget <= 0 && $channel === ChannelRouter::EMAIL && $send->category !== 'transactional') {
                    $stats['deferred']++;
                    continue; // stays queued for the next run
                }
                if ($this->deliver($send, $student)) {
                    $stats['sent']++;
                    $budget -= $channel === ChannelRouter::EMAIL ? 1 : 0;
                    $sentOne = true;
                } else {
                    $stats['failed']++;
                }
            }
        }
        return $stats;
    }

    /** Render and send one message now (also used for test sends). */
    public function deliver(MessageSend $send, object $student): bool
    {
        $template = EmailTemplate::where('key', $send->template_key)->first();
        if (!$template || !$template->is_active) {
            $this->suppress($send, 'template_missing');
            return false;
        }
        try {
            $rendered = (new TemplateRenderer())->render($template, $student->id, $send, $send->context ?? []);
            $to = match ($send->channel) {
                ChannelRouter::PUSH => $this->sendPush($send, $student, $template, $rendered),
                ChannelRouter::SMS => $this->sendSms($send, $student, $template, $rendered),
                default => $this->sendEmail($send, $student, $template, $rendered),
            };
            $send->update([
                'status' => MessageSend::SENT,
                'sent_at' => $this->now,
                'channel' => $send->channel,
                'to_address' => $to,
                'subject' => mb_substr($rendered['subject'], 0, 255),
                'error' => null,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Marketing send failed', ['send_id' => $send->id, 'error' => $e->getMessage()]);
            $send->update(['status' => MessageSend::FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            return false;
        }
    }

    private function sendEmail(MessageSend $send, object $student, EmailTemplate $template, array $rendered): string
    {
        $unsubscribe = $template->isTransactional() ? null : TemplateRenderer::unsubscribeUrl($send);
        Mail::to($student->email)->send(new MarketingMessage($rendered['subject'], $rendered['html'], $rendered['text'], $send->id, $unsubscribe));

        return strtolower($student->email);
    }

    /** Push: title = subject, body = preheader; tapping opens the CTA through click tracking. */
    private function sendPush(MessageSend $send, object $student, EmailTemplate $template, array $rendered): string
    {
        $cta = TemplateRenderer::withUtm(config('marketing.site_url') . '/' . ltrim((string) $template->cta_path, '/'), $this->campaignOf($send));
        app(PushSender::class)->send($student->id, $student->fcm_token, $rendered['subject'], $rendered['preheader'] ?: mb_substr($rendered['text'], 0, 150), [
            'send_id' => (string) $send->id,
            'url' => \Illuminate\Support\Facades\URL::signedRoute('marketing.click', ['send' => $send->id, 'u' => $cta]),
            'path' => (string) $template->cta_path,
        ]);

        return 'push';
    }

    /** SMS: one short line plus the link (no tracking redirect - it would double the length). */
    private function sendSms(MessageSend $send, object $student, EmailTemplate $template, array $rendered): string
    {
        $phone = ChannelRouter::nepaliMobile($student->phone);
        $link = config('marketing.site_url') . '/' . ltrim((string) $template->cta_path, '/');
        app(SmsGateway::class)->send($phone, mb_substr("ExamsNepal: {$rendered['subject']} {$link}", 0, 300));

        return $phone;
    }

    private function campaignOf(MessageSend $send): string
    {
        return $send->automation?->key ?? ($send->broadcast_id ? "broadcast_{$send->broadcast_id}" : $send->template_key);
    }

    // ------------------------------------------------------------ attribution

    /** Mark recent sends whose goal this event completes (within the attribution window). */
    public function attributeGoal(int $studentId, string $event, array $properties, CarbonInterface $at): int
    {
        $sends = MessageSend::query()->with('automation')
            ->where('student_id', $studentId)
            ->where('goal_event', $event)
            ->whereNull('goal_met_at')
            ->whereIn('status', MessageSend::DELIVERED_STATUSES)
            ->whereBetween('sent_at', [CarbonImmutable::instance($at)->subHours((int) config('marketing.attribution_hours')), $at])
            ->get();

        $count = 0;
        foreach ($sends as $send) {
            if (Automation::propertiesMatch($send->automation?->goal_properties, $properties)) {
                $send->update(['goal_met_at' => $at]);
                $count++;
            }
        }
        return $count;
    }

    // ------------------------------------------------------------ helpers

    /**
     * Broadcast dry run: how many of the segment would get it now, and why the rest wouldn't.
     *
     * @return array{candidates:int, would_send:int, skipped:array<string,int>, sample:array}
     */
    public function previewBroadcast(array $filters, string $templateKey, int $sampleSize = 20): array
    {
        $rows = $this->evaluateFilters($filters, $this->categoryOfTemplate($templateKey), null);
        $send = $rows->where('decision', 'send');

        return [
            'candidates' => $rows->count(),
            'would_send' => $send->count(),
            'skipped' => $rows->where('decision', '!=', 'send')->countBy('decision')->all(),
            'sample' => $send->take($sampleSize)->values()->all(),
        ];
    }

    /** Due broadcasts become queued message_sends for every recipient that passes the guard. */
    public function releaseBroadcasts(): int
    {
        $released = 0;
        $due = Broadcast::where('status', Broadcast::SCHEDULED)->where('scheduled_for', '<=', $this->now)->get();
        foreach ($due as $broadcast) {
            $category = $this->categoryOfTemplate($broadcast->template_key);
            $rows = $this->evaluateFilters($broadcast->filters ?? [], $category, null);
            $send = $rows->where('decision', 'send');
            $this->insertQueued($send->map(fn ($row) => array_replace(
                $this->queuedRow($row['student_id'], $category, $broadcast->template_key, ChannelRouter::EMAIL),
                ['broadcast_id' => $broadcast->id],
            )));
            $broadcast->update([
                'status' => Broadcast::QUEUED,
                'recipients_queued' => $send->count(),
                'recipients_skipped' => $rows->count() - $send->count(),
            ]);
            $released += $send->count();
        }
        return $released;
    }

    /** @return Collection<int, array{student_id:int, name:string, email:string, decision:string}> */
    private function evaluate(Automation $automation, bool $ignoreActive = false): Collection
    {
        // The dry run judges the automation as if it were switched on.
        $judged = $ignoreActive ? (clone $automation)->forceFill(['is_active' => true]) : $automation;

        return $this->evaluateFilters($automation->conditions ?? [], $this->categoryOf($automation), $judged);
    }

    /** @return Collection<int, array{student_id:int, name:string, email:string, decision:string}> */
    private function evaluateFilters(array $filters, string $category, ?Automation $judged): Collection
    {
        $guard = new SendGuard($this->now);

        $students = StudentFilter::apply(StudentFilter::base(), $filters)
            ->select(['p.id', 'p.name', 'p.email', 'p.phone', 'p.fcm_token', 'p.marketing_email_opt_in', 'p.unsubscribed_at', 'p.email_verified_at', 'p.last_active_at', 'm.total_attempts'])
            ->orderBy('p.id')
            ->get();
        $guard->prime($students, $judged);

        return $students->map(fn ($s) => [
                'student_id' => (int) $s->id,
                'name' => $s->name,
                'email' => $s->email,
                'decision' => $this->decide($guard, $s, $category, $judged),
            ]);
    }

    private function decide(SendGuard $guard, object $student, string $category, ?Automation $automation): string
    {
        [$channel, $reason] = ChannelRouter::pick($automation?->channel ?? ChannelRouter::EMAIL, (bool) $automation?->allow_sms, $student, $guard->suppressedSet());

        return $reason ?? $guard->check($student, $category, $automation, null, $channel) ?? 'send';
    }

    private function scheduledAutomations(): Collection
    {
        return Automation::query()->where('is_active', true)
            ->where('trigger_type', Automation::TRIGGER_SCHEDULED)
            ->orderByDesc('priority')
            ->get();
    }

    private function queue(Automation $automation, int $studentId, array $context, CarbonInterface $dueAt): MessageSend
    {
        $variant = $automation->variantFor($studentId);
        $category = $this->categoryOf($automation);

        return MessageSend::create([
            'student_id' => $studentId,
            'automation_id' => $automation->id,
            'channel' => $automation->channel,
            'category' => $category,
            'template_key' => $automation->templateKeyFor($variant),
            'variant' => $variant,
            'status' => MessageSend::QUEUED,
            'context' => $context ?: null,
            'goal_event' => $automation->goal_event,
            'scheduled_for' => $category === 'transactional' ? $dueAt : SendWindow::next($dueAt),
        ]);
    }

    /** Base columns of a queued row, for bulk inserts (planner, broadcasts). */
    private function queuedRow(int $studentId, string $category, string $templateKey, string $channel): array
    {
        return [
            'student_id' => $studentId,
            'automation_id' => null,
            'broadcast_id' => null,
            'channel' => $channel,
            'category' => $category,
            'template_key' => $templateKey,
            'variant' => 'A',
            'status' => MessageSend::QUEUED,
            'goal_event' => null,
            'scheduled_for' => $category === 'transactional' ? $this->now : SendWindow::next($this->now),
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ];
    }

    /** Thousands of rows per run are normal (weekly report, broadcasts): insert in chunks, not one by one. */
    private function insertQueued(Collection $rows): void
    {
        foreach ($rows->chunk(1000) as $chunk) {
            MessageSend::insert($chunk->values()->all());
        }
    }

    private function queuedOrRecent(Automation $automation, int $studentId): bool
    {
        return MessageSend::query()->where('automation_id', $automation->id)->where('student_id', $studentId)
            ->where(fn ($q) => $q->where('status', MessageSend::QUEUED)
                ->orWhere(fn ($w) => $w->whereIn('status', MessageSend::DELIVERED_STATUSES)
                    ->where('sent_at', '>=', $this->now->subDays($automation->cooldown_days))))
            ->exists();
    }

    private function categoryOf(Automation $automation): string
    {
        return $this->categoryOfTemplate($automation->template_key);
    }

    private function categoryOfTemplate(string $templateKey): string
    {
        return EmailTemplate::where('key', $templateKey)->value('category') ?? 'lifecycle';
    }

    private function priorityOf(MessageSend $send): int
    {
        return $send->automation?->priority ?? ($send->broadcast_id ? (int) config('marketing.broadcast_priority') : 0);
    }

    private function suppress(MessageSend $send, string $reason): void
    {
        $send->update(['status' => MessageSend::SUPPRESSED, 'suppress_reason' => $reason]);
    }

    private function student(int $id): ?object
    {
        return DB::table('student_profiles as p')->leftJoin('student_metrics as m', 'm.student_id', '=', 'p.id')
            ->where('p.id', $id)
            ->first(['p.id', 'p.name', 'p.email', 'p.phone', 'p.fcm_token', 'p.marketing_email_opt_in', 'p.unsubscribed_at', 'p.email_verified_at', 'p.last_active_at', 'm.total_attempts']);
    }

    private function hourlyBudget(): int
    {
        $sentLastHour = MessageSend::query()->where('channel', 'email')
            ->where('sent_at', '>=', $this->now->subHour())->count();

        return max(0, (int) config('marketing.mail.max_per_hour') - $sentLastHour);
    }
}
