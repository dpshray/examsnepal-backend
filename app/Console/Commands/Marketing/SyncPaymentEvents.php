<?php

namespace App\Console\Commands\Marketing;

use App\Enums\PaymentStatusEnum;
use App\Services\Marketing\EventTracker;
use App\Services\Marketing\PaymentSource;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Derives checkout_started / payment_succeeded / payment_failed from the
 * subscribers table. Payment state is written from several places (eSewa,
 * ConnectIPS, admin manual add), mostly through raw DB::table calls, so
 * reading the table once a minute is the one place that sees them all.
 * Each subscriber row yields each event at most once.
 */
class SyncPaymentEvents extends Command
{
    protected $signature = 'marketing:sync-payment-events
        {--days=3 : Only look at checkouts started in the last N days}
        {--all : Process every subscriber row (historical backfill); events are dated at subscribed_at}';

    protected $description = 'Log checkout/payment marketing events from the subscribers table';

    private const FAILED = [PaymentStatusEnum::PAYMENT_FAILED->value, PaymentStatusEnum::PAYMENT_ERROR->value];

    public function handle(EventTracker $tracker): int
    {
        $all = (bool) $this->option('all');
        $logged = 0;

        DB::table('subscribers')
            ->when(!$all, fn ($q) => $q->where('subscribed_at', '>=', now()->subDays((int) $this->option('days'))))
            ->select(['id', 'student_profile_id', 'subscription_type_id', 'data', 'payment_status', 'status', 'paid', 'subscribed_at'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($tracker, $all, &$logged) {
                $existing = DB::table('events')
                    ->whereIn('name', [EventTracker::CHECKOUT_STARTED, EventTracker::PAYMENT_SUCCEEDED, EventTracker::PAYMENT_FAILED])
                    ->whereIn('properties->subscriber_id', $rows->pluck('id')->all())
                    ->get(['name', 'properties'])
                    ->map(fn ($e) => $e->name . ':' . json_decode($e->properties, true)['subscriber_id'])
                    ->flip();

                foreach ($rows as $row) {
                    $manual = PaymentSource::isManual($row->data);
                    $props = [
                        'subscriber_id' => $row->id,
                        'subscription_type_id' => $row->subscription_type_id,
                        'amount_npr' => (float) $row->paid,
                    ];
                    $startedAt = Carbon::parse($row->subscribed_at);
                    $settledAt = $all ? $startedAt : now();

                    $emit = function (string $name, Carbon $at, array $extra = []) use ($tracker, $row, $props, $existing, &$logged) {
                        if (!isset($existing["{$name}:{$row->id}"])) {
                            $tracker->track($row->student_profile_id, $name, $props + $extra, null, $at);
                            $logged++;
                        }
                    };

                    if (!$manual) {
                        $emit(EventTracker::CHECKOUT_STARTED, $startedAt);
                    }
                    if ($row->payment_status === PaymentStatusEnum::PAYMENT_SUCCESS->value && (int) $row->status === 1) {
                        $emit(EventTracker::PAYMENT_SUCCEEDED, $settledAt, ['manual' => $manual]);
                    } elseif (in_array($row->payment_status, self::FAILED, true)) {
                        $emit(EventTracker::PAYMENT_FAILED, $settledAt, ['payment_status' => $row->payment_status]);
                    }
                }
            });

        $this->info("Logged {$logged} payment event(s).");

        return self::SUCCESS;
    }
}
