# Notices — Implementation Plan (Phase 0)

## Stack found

| Piece | What exists | Where notices go |
|---|---|---|
| API / pipeline | `backend/` — Laravel 11, PHP ^8.2 (prod ≤ 8.3 because `kreait/laravel-firebase` is locked to it), MySQL, JWT (`tymon/jwt-auth`), `QUEUE_CONNECTION=database`, no `app/Jobs` yet, scheduler via `routes/console.php` | Models, migrations, adapters, jobs, commands, admin + public JSON APIs |
| Public site | `frontend/` — Next.js 16 app router, server components fetching the Laravel API (`src/lib/examGuideApi.ts` pattern), Tailwind + shadcn/ui, `src/app/sitemap.ts` (sharded) + `/sitemap.xml` index route | `/notices`, `/notices/{loksewa,entrance,license}`, `/notices/[slug]`, `/notices/feed.xml`, `/bot`, sitemap entries |
| Admin | `admin-panel/` — Next.js 16 client pages, react-query hooks in `src/hooks`, axios services in `src/service`, shadcn/ui | `/notices` (review queue + all notices), `/notice-sources`, `/notice-fetch-logs` |
| Mobile apps | Consume the same Laravel `/api/*` JSON (unauthenticated `free/*` routes for public content) | `GET /api/free/notices`, `GET /api/free/notices/{slug}` |

Conventions followed: admin routes in the existing `['auth:users', 'role:admin']` group under `/admin/*`; responses via `Response::apiSuccess()` + `PaginatorTrait`; public content under `free/*` (same as `free/exam-guides`); enum columns via `$table->enum`.

## Mapping to the spec

- **Mock-test linking**: `exam_tags` vocabulary = slugs of published `exam_guides` (each already has `mock_test_url` + a guide page) plus a fixed list in `config/notices.php`. The detail-page CTA resolves tags → guide name, guide page, and mock-test link.
- **Existing `/lok-sewa-notices` page** is an empty placeholder; it becomes a permanent redirect to `/notices/loksewa`.
- **Scheduler**: `notices:dispatch` every 30 min (`routes/console.php`), one `FetchNoticeSourceJob` per due source on a dedicated `notices` queue. Requires `php artisan schedule:run` in cron + a queue worker (`queue:work --queue=notices,default`).
- **HTTP etiquette**: one `NoticeHttpClient` (UA `ExamsNepalBot/1.0 (+https://www.examsnepal.com/bot)`, robots.txt cached 24 h, per-host 2 s spacing via cache lock, 3 retries with backoff, per-source `verify_ssl` opt-out that is logged).
- **AI**: official `anthropic-ai/sdk`, structured outputs (`output_config.format` json_schema); `claude-haiku-4-5` first pass, `claude-sonnet-5` on low confidence / scanned PDF. PDFs: text layer via `smalot/pdfparser`; if the text layer is empty or legacy-font garbage (Preeti etc. — common on `.gov.np`), the PDF itself is sent as a `document` block.
- **BS dates**: `anuzpandey/laravel-nepali-date` (verified 2070–2090, see tests) wrapped in `App\Services\Notices\NepaliDate` for parsing Devanagari/Latin/month-name formats.
- **Tests**: phpunit.xml currently points tests at the dev MySQL DB; notice tests use an in-memory sqlite connection and run only the notice migrations so they never touch real data.

## Deviations

1. Docs live in `backend/docs/` (workspace root is not a git repo).
2. `notice_sources.selectors` also carries JSON-API field maps and custom-adapter options (one JSON column instead of several).
3. `notice_sources` gets two extra columns: `is_trusted` (auto-publish gate from Phase 4) and `verify_ssl`, plus `last_snapshot_hash`, `consecutive_empty_runs` for redesign detection.
4. Added `notice_reports` table for "Report an error" (no ticket system exists).
5. Social auto-posting (Phase 6b optional) is stubbed behind `NOTICES_SOCIAL_ENABLED` with no channel wired — needs page tokens.

## Deploying on cPanel (no supervisor)

Everything runs from **one cron job**; there is no long-running worker.

1. Upload code, then check `php artisan migrate:status` first. Locally, five older migrations show as
   *Pending* although their tables exist, so a plain `migrate` fails on them. If production shows the same,
   run only the new files:
   ```
   for f in database/migrations/2026_09_24_10*.php; do php artisan migrate --force --path=$f; done
   php artisan db:seed --class=NoticeSourceSeeder --force
   ```
   (notice tables + `ensure_queue_tables_exist`, which only creates `jobs`/`failed_jobs` if missing).
2. `.env`: add the `NOTICES_*` block from `.env.example`. Simple mode (title + date + official link,
   no AI): `NOTICES_FETCH_ENABLED=true`, `NOTICES_AUTOPUBLISH_ENABLED=true`, `NOTICES_AI_PROVIDER=none`.
   Trusted sources publish on fetch; sources marked untrusted (KU, LBU, APF, IOST - they mix in news)
   wait in the admin review queue.
3. cPanel → **Cron Jobs** → Once Per Minute (`* * * * *`):
   ```
   cd /home/CPANEL_USER/PATH_TO_BACKEND && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
   Use the PHP binary matching the site's version (cPanel's *MultiPHP* often exposes it as
   `/opt/cpanel/ea-php83/root/usr/bin/php`); check with `which php` / `php -v` in Terminal.
   If the host only allows every 5 minutes, use `*/5` and raise `--max-time` in `routes/console.php` to 240.
4. What that one cron does (`routes/console.php`): every 30 min queue fetches for due sources; every minute
   run `queue:work database --queue=notices --stop-when-empty --max-time=50`, which drains **only** the
   `notices` queue and exits. Other queued app jobs (e.g. mail on `default`) are not processed by it.
5. Verify: admin → Notice Fetch Logs shows runs within ~30 minutes; `failed_jobs` stays empty.
