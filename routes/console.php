<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Public website stats (App\Services\PublicStats). Cached for 6h; refreshed
// every 4h so a visitor request never pays for the ~10s recompute.
Schedule::command('stats:refresh-public')->everyFourHours()->withoutOverlapping();

// Push content edits to the Vercel frontend, whose public pages are cached for days (App\Services\FrontendCache).
Schedule::command('frontend:flush-cache')->everyMinute()->withoutOverlapping();

// Re-score MCQ pages for indexing nightly so new/edited questions (and new
// duplicates) are picked up (App\Services\Seo\McqIndexability).
Schedule::command('mcq:score-seo')->dailyAt('03:15')->timezone('Asia/Kathmandu')->withoutOverlapping();

// Notices pipeline (see docs/notices-plan.md). The only server setup needed is
// one cPanel cron job running `php artisan schedule:run` every minute - there
// is no long-running worker/supervisor on shared hosting.
Schedule::command('notices:dispatch')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('notices:archive')->dailyAt('01:30')->timezone('Asia/Kathmandu');
Schedule::command('notices:digest')->dailyAt('07:00')->timezone('Asia/Kathmandu');

// Short-lived worker instead of a daemon: drains the `notices` queue (and
// only that queue - other app jobs are untouched) for up to 50 seconds, then
// exits before the next minute's run.
Schedule::command('queue:work', [
    config('notices.queue_connection'),
    '--queue' => config('notices.queue'),
    '--stop-when-empty',
    '--max-time' => 50,
    '--sleep' => 0,
])->everyMinute()->withoutOverlapping(10);

// Marketing funnel (see docs/marketing.md). Metric refresh jobs queued on
// submission run on the `marketing` queue, served by a supervisor-managed
// worker on the VPS - not by the short-lived notices worker above.
Schedule::command('marketing:sync-payment-events')->everyMinute()->withoutOverlapping();
Schedule::command('marketing:detect-lifecycle-events')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('marketing:refresh-metrics')->hourly()->withoutOverlapping(55);
Schedule::command('marketing:run-automations')->hourlyAt(5)->withoutOverlapping();
Schedule::command('marketing:dispatch')->everyMinute()->withoutOverlapping(10);
