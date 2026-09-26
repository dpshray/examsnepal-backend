<?php

namespace Tests\Feature\Marketing;

use App\Services\Marketing\AttemptScorer;
use App\Services\Marketing\EventTracker;
use Illuminate\Support\Facades\DB;

class MarketingEventsTest extends MarketingDatabaseTestCase
{
    private function events(?string $name = null): array
    {
        return DB::table('events')->when($name, fn ($q) => $q->where('name', $name))->orderBy('id')->get()->all();
    }

    public function test_tracker_logs_event_and_marks_activity(): void
    {
        $id = $this->student();
        (new EventTracker())->track($id, EventTracker::LOGGED_IN, ['method' => 'password'], 'android');

        $event = $this->events()[0];
        $this->assertSame('logged_in', $event->name);
        $this->assertSame('android', $event->platform);
        $this->assertSame(['method' => 'password'], json_decode($event->properties, true));

        $profile = DB::table('student_profiles')->find($id);
        $this->assertNotNull($profile->last_active_at);
        $this->assertSame('android', $profile->last_platform);
    }

    public function test_non_activity_events_do_not_touch_last_active(): void
    {
        $id = $this->student();
        (new EventTracker())->track($id, EventTracker::PAYMENT_FAILED, [], 'web');

        $this->assertNull(DB::table('student_profiles')->find($id)->last_active_at);
    }

    public function test_tracker_never_throws(): void
    {
        (new EventTracker())->track(999999, EventTracker::LOGGED_IN); // FK violation, swallowed
        $this->assertSame([], $this->events());
    }

    public function test_attempt_scorer_uses_exam_marking_rules(): void
    {
        $id = $this->student();
        $exam = $this->exam(1, 4, ['is_negative_marking' => true, 'negative_marking_point' => 0.5]);
        $attempt = DB::table('student_exams')->insertGetId([
            'exam_id' => $exam, 'student_id' => $id, 'is_exam_completed' => 1,
            'created_at' => now()->subHour(), 'updated_at' => now()->subMinutes(5),
        ]);
        DB::table('answersheets')->insert([
            ['student_exam_id' => $attempt, 'question_id' => 1, 'is_correct' => 1],
            ['student_exam_id' => $attempt, 'question_id' => 2, 'is_correct' => 1],
            ['student_exam_id' => $attempt, 'question_id' => 3, 'is_correct' => 0],
            ['student_exam_id' => $attempt, 'question_id' => 4, 'is_correct' => null],
        ]);

        $this->assertSame(1, (new AttemptScorer())->score([$attempt]));

        $row = DB::table('student_exams')->find($attempt);
        $this->assertEquals(37.5, (float) $row->score_pct); // (2 - 0.5) / 4
        $this->assertSame(now()->subMinutes(5)->toDateTimeString(), substr($row->submitted_at, 0, 19));
    }

    public function test_payment_events_are_derived_once_per_subscriber_row(): void
    {
        $id = $this->student();
        $paid = $this->payment($id, ['subscribed_at' => now()->subHour()]);
        $failed = $this->payment($id, ['payment_status' => 'PAYMENT_ERROR', 'status' => 0, 'subscribed_at' => now()->subHour()]);
        $pending = $this->payment($id, ['payment_status' => 'PAYMENT_INIT', 'status' => 0, 'subscribed_at' => now()->subMinutes(5)]);
        $manual = $this->manualPayment($id);
        $this->payment($id, ['subscribed_at' => now()->subDays(10)]); // outside default window

        $this->artisan('marketing:sync-payment-events')->assertSuccessful();
        $this->artisan('marketing:sync-payment-events')->assertSuccessful(); // idempotent

        $bySubscriber = fn (string $name) => collect($this->events($name))
            ->map(fn ($e) => json_decode($e->properties, true)['subscriber_id'])->sort()->values()->all();

        $this->assertSame([$paid, $failed, $pending], $bySubscriber(EventTracker::CHECKOUT_STARTED));
        $this->assertSame([$paid], $bySubscriber(EventTracker::PAYMENT_SUCCEEDED));
        $this->assertSame([$failed], $bySubscriber(EventTracker::PAYMENT_FAILED));

        // The pending checkout later succeeds -> exactly one success event added.
        DB::table('subscribers')->where('id', $pending)->update(['payment_status' => 'PAYMENT_SUCCESS', 'status' => 1]);
        $this->artisan('marketing:sync-payment-events')->assertSuccessful();
        $this->assertSame([$paid, $pending], $bySubscriber(EventTracker::PAYMENT_SUCCEEDED));

        // --all backfills the manual and old rows; manual ones get no checkout.
        $this->artisan('marketing:sync-payment-events', ['--all' => true])->assertSuccessful();
        $this->assertNotContains($manual, $bySubscriber(EventTracker::CHECKOUT_STARTED));
        $manualEvent = collect($this->events(EventTracker::PAYMENT_SUCCEEDED))
            ->first(fn ($e) => json_decode($e->properties, true)['subscriber_id'] === $manual);
        $this->assertTrue(json_decode($manualEvent->properties, true)['manual']);
    }

    public function test_abandoned_exams_and_expired_subscriptions(): void
    {
        $id = $this->student();
        $abandoned = $this->attempt($id, 1, null, now()->subHours(4), completed: false);
        $this->attempt($id, 1, null, now()->subHour(), completed: false); // still within 3h
        $this->attempt($id, 1, 50, now()->subHours(5));                   // submitted

        $expired = $this->student();
        $this->payment($expired, ['end_date' => today()->subDay()]);
        $renewed = $this->student();
        $this->payment($renewed, ['end_date' => today()->subDay()]);
        $this->payment($renewed, ['end_date' => today()->addMonth()]);

        $this->artisan('marketing:detect-lifecycle-events')->assertSuccessful();
        $this->artisan('marketing:detect-lifecycle-events')->assertSuccessful(); // idempotent

        $abandonEvents = $this->events(EventTracker::EXAM_ABANDONED);
        $this->assertCount(1, $abandonEvents);
        $this->assertSame($abandoned, json_decode($abandonEvents[0]->properties, true)['student_exam_id']);

        $expiredEvents = $this->events(EventTracker::SUBSCRIPTION_EXPIRED);
        $this->assertCount(1, $expiredEvents);
        $this->assertSame($expired, $expiredEvents[0]->student_id);
    }

    public function test_backfill_fills_signup_dates_and_scores(): void
    {
        $email = $this->student(['created_at' => null, 'date' => '08/06/2026 08:42:05 pm']);
        $google = $this->student(['created_at' => null, 'date' => '2026-09-20 10:00:00', 'google_id' => 'g1']);
        DB::table('student_exams')->insert([
            'exam_id' => $this->exam(3, 2), 'student_id' => $google, 'is_exam_completed' => 1,
            'created_at' => '2026-03-01 09:00:00', 'updated_at' => '2026-03-01 09:10:00',
        ]);

        $this->artisan('marketing:backfill', ['--skip-payments' => true])->assertSuccessful();

        $this->assertSame('2026-08-06 20:42:05', substr(DB::table('student_profiles')->find($email)->created_at, 0, 19));
        // Google login rewrites `date`, so the earlier first attempt wins.
        $this->assertSame('2026-03-01 09:00:00', substr(DB::table('student_profiles')->find($google)->created_at, 0, 19));
        $this->assertEquals(0, (float) DB::table('student_exams')->where('student_id', $google)->value('score_pct'));
    }
}
