<?php

namespace Tests\Feature\Notices;

use App\Models\Notice;
use App\Models\NoticeReport;
use App\Models\NoticeSubscription;
use App\Services\Notices\NoticePublisher;
use Illuminate\Support\Str;

class NoticeApiTest extends NoticeDatabaseTestCase
{
    private function notice(array $attributes = []): Notice
    {
        static $n = 0;
        $n++;

        return Notice::create(array_replace([
            'category' => 'loksewa',
            'sub_category' => 'federal_psc',
            'organization' => 'Public Service Commission',
            'title_original' => "सूचना {$n}",
            'title_en' => "PSC Notice {$n}",
            'source_url' => "https://psc.gov.np/category/notice/{$n}",
            'content_hash' => hash('sha256', (string) $n.Str::random()),
            'published_date_ad' => today()->subDays($n % 30),
            'notice_type' => 'vacancy',
            'status' => 'published',
        ], $attributes));
    }

    public function test_list_shows_only_published_and_paginates(): void
    {
        $this->notice();
        $this->notice(['status' => 'pending']);
        $this->notice(['status' => 'rejected']);
        $this->notice(['status' => 'archived']);

        $this->getJson('/api/free/notices')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonStructure(['data' => ['data' => [['slug', 'title_en', 'category', 'notice_type', 'application_deadline_ad']], 'current_page', 'last_page', 'total']]);

        $this->getJson('/api/free/notices?archived=1')->assertJsonPath('data.total', 2);
    }

    public function test_filters(): void
    {
        $this->notice(['category' => 'entrance', 'organization' => 'Medical Education Commission (MEC)', 'title_en' => 'MBBS CEE schedule']);
        $this->notice(['province' => 'lumbini', 'notice_type' => 'result']);
        $this->notice(['application_deadline_ad' => today()->addDays(3)]);
        $this->notice(['application_deadline_ad' => today()->addDays(20)]);
        $this->notice(['exam_date_ad' => today()->addDays(10)]);

        $this->getJson('/api/free/notices?category=entrance')->assertJsonPath('data.total', 1);
        $this->getJson('/api/free/notices?province=lumbini&type=result')->assertJsonPath('data.total', 1);
        $this->getJson('/api/free/notices?closing=7d')->assertJsonPath('data.total', 1);
        $this->getJson('/api/free/notices?upcoming=30d')->assertJsonPath('data.total', 1);
        $this->getJson('/api/free/notices?q=MBBS')->assertJsonPath('data.total', 1);
        $this->getJson('/api/free/notices?org='.urlencode('Medical Education Commission (MEC)'))->assertJsonPath('data.total', 1);
        $this->getJson('/api/free/notices?category=tender')->assertStatus(422);
    }

    public function test_home_sections(): void
    {
        $this->notice(['application_deadline_ad' => today()->addDays(2)]);
        $this->notice(['exam_date_ad' => today()->addDays(12)]);
        $this->notice(['category' => 'license']);

        $this->getJson('/api/free/notices/home')
            ->assertOk()
            ->assertJsonCount(1, 'data.closing_soon')
            ->assertJsonCount(1, 'data.upcoming_exams')
            ->assertJsonCount(3, 'data.latest')
            ->assertJsonPath('data.counts.loksewa', 2)
            ->assertJsonPath('data.counts.license', 1);
    }

    public function test_detail_page(): void
    {
        $notice = $this->notice([
            'application_deadline_ad' => '2026-10-07',
            'posts' => [['name' => 'Kharidar', 'service_group' => 'Administration', 'level' => '4th', 'seats' => 12, 'qualification' => 'SEE']],
            'exam_tags' => ['kharidar'],
            'summary_en' => 'Summary.',
            'enriched_at' => now(),
        ]);
        $related = $this->notice();

        $this->getJson("/api/free/notices/{$notice->slug}")
            ->assertOk()
            ->assertJsonPath('data.application_deadline_bs', '2083-06-21')
            ->assertJsonPath('data.posts.0.seats', 12)
            ->assertJsonPath('data.is_ai_summary', true)
            ->assertJsonPath('data.exam_links.0.tag', 'kharidar')
            ->assertJsonPath('data.exam_links.0.mock_test_url', '/find-mcq/administration-loksewa-all-levels-exams')
            ->assertJsonPath('data.related.0.slug', $related->slug);

        $this->assertSame(1, $notice->fresh()->view_count);
    }

    public function test_hidden_statuses_404(): void
    {
        $pending = $this->notice(['status' => 'pending']);
        $this->getJson("/api/free/notices/{$pending->slug}")->assertNotFound();
        $this->getJson('/api/free/notices/does-not-exist')->assertNotFound();

        $archived = $this->notice(['status' => 'archived']);
        $this->getJson("/api/free/notices/{$archived->slug}")->assertOk()->assertJsonPath('data.is_archived', true);
    }

    public function test_publish_invalidates_list_cache(): void
    {
        $this->notice();
        $this->getJson('/api/free/notices')->assertJsonPath('data.total', 1);

        $pending = $this->notice(['status' => 'pending']);
        $this->getJson('/api/free/notices')->assertJsonPath('data.total', 1, 'cached');

        app(NoticePublisher::class)->publish($pending);
        $this->getJson('/api/free/notices')->assertJsonPath('data.total', 2);
    }

    public function test_report_and_subscribe(): void
    {
        $notice = $this->notice();

        $this->postJson("/api/free/notices/{$notice->slug}/report", ['message' => 'Deadline is wrong', 'field' => 'application_deadline_ad'])->assertCreated();
        $this->assertSame(1, NoticeReport::count());

        $this->postJson('/api/free/notices/subscribe', ['email' => 'A@Example.com', 'categories' => ['loksewa'], 'exam_tags' => ['kharidar']])->assertCreated();
        $this->postJson('/api/free/notices/subscribe', ['email' => 'a@example.com', 'categories' => ['entrance']])->assertCreated();
        $this->assertSame(1, NoticeSubscription::count());
        $this->postJson('/api/free/notices/subscribe', ['email' => 'b@example.com', 'exam_tags' => ['not-a-tag']])->assertStatus(422);

        $token = NoticeSubscription::first()->unsubscribe_token;
        $this->get("/api/free/notices/unsubscribe/{$token}")->assertOk();
        $this->assertFalse(NoticeSubscription::first()->is_active);
    }

    public function test_subscription_matching(): void
    {
        $sub = new NoticeSubscription(['categories' => ['entrance'], 'exam_tags' => ['mds-entrance']]);
        $this->assertTrue($sub->matches(new Notice(['category' => 'entrance', 'exam_tags' => ['mds-entrance', 'md-ms-entrance']])));
        $this->assertFalse($sub->matches(new Notice(['category' => 'entrance', 'exam_tags' => ['mbbs-cee']])));
        $this->assertFalse($sub->matches(new Notice(['category' => 'loksewa', 'exam_tags' => ['mds-entrance']])));
        $this->assertTrue((new NoticeSubscription([]))->matches(new Notice(['category' => 'license'])));
    }

    public function test_archive_command(): void
    {
        $old = $this->notice(['application_deadline_ad' => today()->subDays(31)]);
        $recent = $this->notice(['application_deadline_ad' => today()->subDays(5)]);

        $this->artisan('notices:archive')->assertSuccessful();

        $this->assertSame('archived', $old->fresh()->status);
        $this->assertSame('published', $recent->fresh()->status);
    }

    public function test_slug_is_frozen_after_publish(): void
    {
        $notice = $this->notice(['status' => 'pending', 'title_en' => null, 'title_original' => 'लोक सेवा सूचना', 'published_date_ad' => '2026-09-24']);
        $this->assertSame('public-service-commission-notice-2026-09-24', $notice->slug);

        $notice->title_en = 'PSC Kharidar Vacancy 2083';
        $notice->refreshSlugFromEnglishTitle();
        $notice->save();
        $this->assertSame('psc-kharidar-vacancy-2083', $notice->slug);

        app(NoticePublisher::class)->publish($notice);
        $notice->title_en = 'Changed title';
        $notice->refreshSlugFromEnglishTitle();
        $this->assertSame('psc-kharidar-vacancy-2083', $notice->slug);
    }

    public function test_list_stays_fast_with_5000_notices(): void
    {
        $rows = [];
        foreach (range(1, 5000) as $i) {
            $rows[] = [
                'category' => ['loksewa', 'entrance', 'license'][$i % 3],
                'organization' => 'Org '.($i % 40),
                'title_original' => "Notice {$i}",
                'title_en' => "Notice {$i}",
                'slug' => "notice-{$i}",
                'source_url' => "https://psc.gov.np/n/{$i}",
                'content_hash' => hash('sha256', "n{$i}"),
                'published_date_ad' => today()->subDays($i % 365)->toDateString(),
                'application_deadline_ad' => $i % 5 ? null : today()->addDays($i % 20)->toDateString(),
                'notice_type' => 'vacancy',
                'status' => $i % 10 ? 'published' : 'archived',
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Notice::insert($chunk);
        }

        $start = microtime(true);
        foreach (['', '?category=entrance', '?closing=7d', '?q=Notice%2049', '?page=40'] as $query) {
            $this->getJson('/api/free/notices'.$query)->assertOk();
        }
        $this->getJson('/api/free/notices/home')->assertOk();
        $elapsed = microtime(true) - $start;

        $this->assertSame(4500, $this->getJson('/api/free/notices')->json('data.total'));
        $this->assertLessThan(3.0, $elapsed, "6 uncached list requests over 5,000 notices took {$elapsed}s");
    }
}
