# Marketing funnel & lifecycle automation

Status: **Phases 1–8 built**, from the data layer to onboarding. See the deploy checklist at the end.

## What exists

| Piece | Where |
|---|---|
| Behaviour events (`events` table) | `App\Services\Marketing\EventTracker` |
| Per-student metrics (`student_metrics`) | `App\Services\Marketing\StudentMetricsCalculator` |
| Lead score rules + breakdown | `App\Services\Marketing\LeadScore` |
| Cached attempt score (`student_exams.score_pct`, `submitted_at`) | `App\Services\Marketing\AttemptScorer` |
| Signup attribution, activity, consent fields | `student_profiles` (migration `2026_09_26_100000`) |

### Lifecycle stages & segments
`App\Services\Marketing\Lifecycle` holds every rule. `STAGE_PRECEDENCE` is the one ordered list that decides which stage wins (first match). The calculator stores the result in `student_metrics.lifecycle_stage` and `segments` (a JSON array). Filter with `JSON_CONTAINS(segments, '"app_user"')`.

Precedence: `expiring_soon` → `paid_active` → `paid_inactive` → `expired` → `hot_lead` → `new` → `registered_inactive` → `dormant` → `engaged_free` → `free_only` → `activated`. Paid states come first, so a paying student can never be targeted as a lead.

Deviations from the original spec, chosen deliberately:
- `hot_lead` only counts a checkout abandoned in the **last 30 days**; otherwise one old abandoned checkout would mark a student hot forever.
- `free_only` means 3+ attempts with no Sprint/Mock. Topic quizzes count as free.
- `dormant` also covers students whose only attempts are untimed legacy rows.
- Segment per exam is `exam:{exam_type_id}` (e.g. `exam:1`), not a slug, so saved segments survive exam renames.
- `streak_active` means a current streak of 2+ days. `streak_broken` means a 2+ day streak existed, today's streak is 0, and the last attempt was 2–7 days ago.
- `app_user` is set by any of: an FCM token (web never registers one), an android/ios event or last platform, or an app signup. Everyone else is `web_only`.

### Where each event comes from

| Event | Source |
|---|---|
| `signed_up` | `StudentProfileController@register`, `@googleLogin` (new account) |
| `logged_in` | `AuthController@loginStudent`, `StudentProfileController@googleLogin` |
| `exam_started` | `QuestionController::checkIfExamHasBeenStartedPreviously` (first open) |
| `exam_submitted` | `AnswerSheetController@store` (first time `is_exam_completed=1`), which also scores the attempt and queues `RefreshStudentMetricsJob` |
| `pricing_viewed` | `SubscriptionTypeController@index`, deduped to one per 30 min |
| `checkout_started`, `payment_succeeded`, `payment_failed` | `marketing:sync-payment-events` (every minute) reading `subscribers`. Admin manual adds log only `payment_succeeded` with `manual: true`. They are recognised by their `data` column (`PaymentSource`), not the transaction id, because every source generates `TXN#####` ids. |
| `exam_abandoned`, `subscription_expired` | `marketing:detect-lifecycle-events` (every 15 min) |
| `email_clicked` | Phase 4 |

### Definitions
- **Attempt**: a completed `student_exams` row whose exam is not a class exam. Types come from `exams.status`: 3 = Free, 4 = Sprint, 1 = Mock, 5 = Topic.
- **Score %**: final marks after negative marking ÷ full marks, the same as the student result screen, floored at 0.
- **Percentile**: among students with the same `exam_type_id` who were scored in the last 30 days, the share of peers with a lower 30-day average. 100 means best. It is only written by the full hourly refresh.
- **Score trend**: average of the last 3 scores vs the 3 before them; ±5 points counts as improving or declining. Fewer than 6 scored attempts gives `insufficient_data`.

### Known data gaps
- 10,397 legacy students (ids ≤ 10604) have `date = 0000-00-00` and untimed attempts, so their signup date is unknown (`created_at` NULL). Exclude them from cohort and activation charts.
- About 74% of historical attempts have no timestamp. They count toward totals but not toward 7/14/30-day windows or streaks.
- Subjects come from exam names (see "Subjects" below). Multi-subject mocks and generically named exams (most pharmacy, radiography and engineering exams) stay untagged.
- Google sign-in overwrites `student_profiles.date` on every login. `created_at` is now set once, at account creation.

## Deploying on the VPS

```bash
# The old DB has legacy migrations stuck as "Pending"; run these by path, NOT a bare `migrate`.
for m in 2026_09_26_100000_add_marketing_fields_to_student_profiles_table \
         2026_09_26_100100_add_score_fields_to_student_exams_table \
         2026_09_26_100200_create_events_table \
         2026_09_26_100300_create_student_metrics_table; do
  php artisan migrate --path=database/migrations/$m.php --force
done
php artisan marketing:backfill        # signup dates, attempt scores, historical payment events (safe to re-run)
php artisan marketing:refresh-metrics
```

Cron (already needed for notices):
```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Supervisor worker for the `marketing` queue, `/etc/supervisor/conf.d/examsnepal-marketing.conf`:
```ini
[program:examsnepal-marketing]
command=php /path/to/backend/artisan queue:work database --queue=marketing --sleep=3 --tries=2 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
stopwaitsecs=60
redirect_stderr=true
stdout_logfile=/path/to/backend/storage/logs/marketing-worker.log
```
Then run `supervisorctl reread && supervisorctl update`. Run `php artisan queue:restart` after each deploy.

## Client changes wanted (optional, improves data)
- Web and app signup forms can send `signup_source`, `utm_source`, `utm_medium` and `utm_campaign`. The web frontend should read these from the landing URL.
- Mobile apps should send an `X-Platform: android|ios` header on API calls. Without it the platform is guessed from the user agent.

## Email engine (Phase 4)

| Piece | Where |
|---|---|
| Tables | `email_templates`, `automations`, `message_sends`, `suppressions`, `marketing_settings` (migration `2026_09_26_100500`) |
| Engine: event triggers, hourly planner, dry run, dispatcher, goal attribution | `App\Services\Marketing\AutomationEngine` |
| All guardrails, in one place | `App\Services\Marketing\SendGuard` |
| Rendering: variables, tracked links, UTM tags, open pixel, unsubscribe footer | `App\Services\Marketing\TemplateRenderer`, `resources/views/mail/marketing/layout.blade.php` |
| Tracking endpoints (signed, public) | `EmailTrackingController`: `/e/o/{send}` open pixel, `/e/c/{send}?u=` click, `/e/u/{send}` unsubscribe (GET = one click, POST = RFC 8058), `/e/r/{send}` undo |
| Bounces, complaints, mailto unsubscribes | `BounceParser` plus `php artisan marketing:ingest-mail` (reads a raw email on stdin) |
| Admin API | `AdminMessagingController`, under `/api/admin/marketing/{messaging,automations,sends,suppressions,templates}` |
| Admin UI | admin-panel `/marketing/automation` (kill switch, automations with stats, dry run, message log, suppressions) |

### How a message flows
1. **Event automation**: `EventTracker::track()` calls `AutomationEngine::onEvent()`. That queues a `message_sends` row, due after the automation's delay and moved into the next send window.
2. **Scheduled automation**: `marketing:run-automations` (hourly, at :05) queues every student who matches `conditions` and passes `SendGuard`.
3. **Dispatch**: `marketing:dispatch` (every minute) first refreshes the recipients' metrics. It then re-runs `SendGuard` on each message, keeps only the highest-priority message per student, and sends through Laravel's mailer (the VPS Exim, as info@examsnepal.com).
4. **Goals**: when a tracked event matches a send's `goal_event` (and `goal_properties`) within 72h, that send's `goal_met_at` is set.

### Guardrails (`SendGuard`, in this order)
The address must be valid and not suppressed. Lifecycle and promotional messages also require the student to be opted in. The automation must be active. `is_upsell` automations never reach students with an active or expiring subscription. The conditions must still hold, using fresh metrics. The goal must not have been met since the trigger. The cooldown must have passed. Frequency cap: at most **1 per 48h and 3 per 7 days** for non-transactional messages. A queued message only reserves a slot against lower-priority ones.

Separately, the dispatcher enforces:
- **Kill switch**: `marketing_settings.automations_paused`, which **defaults to paused**.
- **Send windows**: 07:00–09:00 and 18:00–20:30 NPT.
- **Hourly budget**: `MARKETING_MAIL_MAX_PER_HOUR`, default 200. Set it below WHM's per-domain hourly limit.
- **Expiry**: messages still unsent 72h after they were due are dropped, not sent late.

Transactional templates skip the kill switch, send windows and caps, but never the suppression list.

`conditions` use the same keys as the student list filters (`StudentFilter::KEYS`), plus time windows such as `signed_up_hours_min/max`, `ends_in_days_min/max`, `expired_days_min/max`, `last_attempt_days_min/max`, `days_to_exam_min/max` and `pricing_views_min`.

The dry run (`preview()`, the "Dry run" button, `php artisan marketing:run-automations --dry-run`) uses the same candidate query and the same `SendGuard`. A test checks that the preview equals what is actually sent.

### Template variables
`{{first_name}} {{target_exam}} {{days_to_exam}} {{total_attempts}} {{last_score}} {{avg_score}} {{best_score}} {{weakest_subject}} {{weakest_subject_score}} {{percentile}} {{top_percent}} {{streak}} {{next_free_quiz_date}} {{subscription_ends_at}} {{cta_url}} {{pricing_url}} {{site_url}}`. Values are HTML-escaped, and unknown variables render as nothing. A template with `cta_label` gets one green button linking to `cta_path` on the site. `weakest_subject*` stays empty until questions are tagged with subjects.

### VPS setup for Phase 4
1. Run the migration by path: `php artisan migrate --path=database/migrations/2026_09_26_100500_create_marketing_messaging_tables.php --force`.
2. Set `MARKETING_SITE_URL`, `MARKETING_MAIL_MAX_PER_HOUR` (below the WHM limit) and `MARKETING_POSTAL_ADDRESS` in `.env`. `APP_URL` must be the public backend URL (e.g. `https://api.examsnepal.com`), because tracking and unsubscribe links are built from it.
3. **Bounces**: in cPanel → Email → Forwarders → Add Forwarder for `info@examsnepal.com`, choose "Pipe to a Program" and enter `/usr/local/bin/php /home/<user>/<path>/backend/artisan marketing:ingest-mail`. The mailbox still receives everything; this only adds a copy for the app.
4. The cron `schedule:run` every minute is already required; it now also runs dispatch and planning.
5. Nothing sends until an admin presses **Resume sending** on the Email automation page. Test first with the Dry run button.

## Starter automations (Phase 5)

`php artisan marketing:install-starter` installs 25 templates and 29 automations from `App\Services\Marketing\StarterCatalog`, **all switched off**. Re-running it skips anything that already exists. `--force` refreshes copy and settings but never changes whether an automation is on or off.

| Group | Automations |
|---|---|
| A. Activation | `welcome` (first login, account ≤72h old), `activate_1/2/3` (no exam 24–72h / 3–7d / 7–11d after signup) |
| B. Free → Mock | `free_to_mock_1` (30 min after a FREE quiz), `free_to_mock_2` (7+ days after #1, still no Sprint/Mock), `free_waiting` (later free quizzes; upsell, 3-month plan) |
| D. Intent | `checkout_abandoned_1` (+1h), `checkout_abandoned_2` (+48h), `pricing_viewed` (2+ views, latest ≤2 days), `payment_failed` (+15 min, transactional) |
| E. Paid | `paid_welcome`, `paid_inactive`, `expiring_7d`, `expiring_1d`, `expired_winback` (3–5 days), `expired_winback_2` (14–17 days) |
| C. Performance | `score_improving`, `score_declining`, `weak_subject`, `high_performer` (upsell), `low_performer` |
| F. Recurring | `weekly_progress_report` (Sundays 07:00–08:00 NPT, active in last 14 days), `exam_countdown_60/30/14/7`, `dormant_winback_21/45` |

Deviations from the original spec, chosen deliberately:
- **Welcome fires on the first login, not at signup.** Email signups can't log in before verifying, so this avoids mailing unverified (often mistyped) addresses. Google signups log in straight away.
- **`checkout_abandoned_2` waits 48h, not 24h.** The frequency cap allows one lifecycle email per 48h, so a 24h follow-up could never send.
- **Addresses that were never verified and never active are skipped** (`unverified_inactive`) for all non-transactional mail. That is 6,720 legacy addresses; mailing them risks bounces.
- **Every scheduled automation targets a recent time window**, so switching one on cannot mass-mail the 9,000 old inactive accounts.
- **Some emails need data that doesn't exist yet.** `exam_countdown_*` needs `target_exam_date`, which the Phase 8 onboarding asks for. `weak_subject` needs exams tagged with subjects (`subjects:infer`) and only fires below 40%. The optional discount code in `checkout_abandoned_2` isn't included.
- **`free_to_mock_1` and `free_to_mock_2` also include `activated` students** (1–2 exams), not only `free_only` (3+).

**Suggested rollout:** turn on `payment_failed`, `checkout_abandoned_1`, `welcome` and `activate_1` first. Watch the goal rate and bounce/unsubscribe counts for a week, then turn on the rest group by group.

## Templates, A/B tests, broadcasts (Phase 6)

- **Template editor** (admin-panel `/marketing/templates`): a variable picker inserts at the cursor. The live preview renders unsaved edits for a real student through `POST templates/draft-preview`. "Send test to me" mails the saved version to the logged-in admin. Template keys can't change after creation, and a template in use can't be deleted.
- **Automation settings** (the "Settings" button on the Email automation page): delay, cooldown, priority, variant B template and split. **A/B results are judged on goal conversion**, via `AbTestAnalyzer`: two-proportion z-test, a winner at 95% only once each variant has 100+ sends. "Keep A/B, end test" promotes one variant.
- **Broadcasts**: `broadcasts` table (migration `2026_09_26_100700`); Email automation → Broadcasts tab, or "Send email" on the Students page with its current filters. The dry run shows who'd get it, who's skipped and why, and an estimate of how many days sending takes at the hourly limit. A broadcast must have at least one filter. Recipients are resolved when it becomes due and pass the same `SendGuard` (unsubscribes, bounces, frequency cap at priority 50, send windows). Cancelling drops anything not yet sent.
- **Kill-switch audit**: each pause/resume records the admin id and time (`marketing_settings.automations_paused_changed_by`, plus a log line), shown under the switch.

## Multi-channel (Phase 7)

- `automations.channel`: `email` | `push` | `sms` | `auto`. **auto** means push if the app is installed with an FCM token, else email, else SMS. SMS is used only when `allow_sms` is set (migration `2026_09_26_100800`). `Channels\ChannelRouter` picks the channel per student. `message_sends.channel` records the one actually used.
- **Push** goes through the existing `FCMService` (`Channels\FcmPushSender`), so it also appears in the app's notification list, with type `MARKETING`. Title = subject, body = preheader. The data carries `url` (a signed click-tracking link) and `path` (the in-app route to open). Disable with `MARKETING_PUSH_ENABLED=false`.
- **SMS** uses `Channels\SmsGateway`: the `log` driver by default (writes to the log, sends nothing), or `sparrow` with `MARKETING_SMS_DRIVER=sparrow`, `SPARROW_SMS_TOKEN` and `SPARROW_SMS_FROM`. Numbers are normalised to Nepali mobiles (98/97/96 + 8 digits).
- **Frequency caps are per channel** (`config marketing.caps`): email 1/48h and 3/week (the spec); push 1/20h and 5/week; SMS 1/72h and 2/week. A marketing unsubscribe stops every channel.
- **Starter set:** `checkout_abandoned_1` and `expiring_1d` are `auto` + SMS, the spec's high-value moments. `streak_reminder` is push only, daily at 19:00 NPT, for students with a live streak who haven't practised today. New-exam pushes already come from `ExamObserver`, so no separate "free quiz unlocked" automation was added.
- **In-app banner:** `GET /api/student/marketing/banner` returns a banner for the student's lifecycle stage (`config marketing.banners`). The web dashboard shows it (`frontend/src/components/banner/LifecycleBanner.tsx`), dismissible for 3 days.

## Onboarding (Phase 8)

- `GET/POST /api/student/onboarding`. Step 1 is the exam (prefilled from signup; locked while a paid plan is active). Step 2 is the exam month or "not sure", stored as `target_exam_date` on the 1st of that month. The response sends the student to `/student/exams/free-quiz`. Skipping is allowed. Events `onboarding_completed` / `onboarding_skipped`; `student_profiles.onboarded_at` (migration `2026_09_26_100900`).
- Login responses now include `student.needs_onboarding` (true until onboarded or an exam date is set). The web login routes to `/student/onboarding` when it's true.
- The exam date powers `exam_countdown_60/30/14/7`, the `exam_date_near` segment and the lead score's "+10 exam within 60 days".

## Needed in the mobile app (separate codebase)
1. Send `X-Platform: android` / `ios` on API calls.
2. After login, if `student.needs_onboarding` is true, show the 2 questions and POST `/api/student/onboarding`.
3. For push type `MARKETING`, open `data.path` in the app on tap (or `data.url` in a browser). Opening `data.url` records the click.
4. Optional: show `GET /api/student/marketing/banner` on the home screen.

## Deploy checklist (VPS)
1. Pull the code, then run `php artisan marketing:migrate --pretend` followed by `php artisan marketing:migrate`. It applies only the ten marketing migrations, in order, by path. Never run a bare `migrate`: old migrations are stuck as "Pending" on that database.
2. `php artisan marketing:backfill && php artisan marketing:refresh-metrics && php artisan marketing:install-starter`.
3. `.env`: `APP_URL` (the public backend URL), `MARKETING_SITE_URL`, `MARKETING_MAIL_MAX_PER_HOUR` (below WHM's limit), `MARKETING_POSTAL_ADDRESS`, and optionally the SMS variables. Mail settings for `info@examsnepal.com` over SMTP.
4. Cron `* * * * * php artisan schedule:run`, plus the Supervisor worker for the `marketing` queue.
5. cPanel forwarder: pipe `info@examsnepal.com` to `php artisan marketing:ingest-mail`.
6. DNS: DMARC `rua=mailto:info@examsnepal.com`; confirm outbound port 25 is open.
7. Admin panel → Email automation: dry-run, send a test, switch on a few automations, then press **Resume sending**.

## Performance at 50,000 students (measured)

Benchmarked on a scratch MySQL copy of the real data ×4: 50,980 students and 91,484 attempts.

| Operation | Time |
|---|---|
| Overview, 30 days / 12 months / 12 months for one exam | 0.04s / 0.52s / 0.51s |
| Weekly cohorts, 26 weeks | 0.02s |
| Student list: no filter / stage+segment / search / ranges / platform+exam | 0.03–0.09s |
| Dry run over all 50,980 students (unconditioned event automation) | 1.5s (was 19.3s before `SendGuard::prime()`) |
| Broadcast release, 9,280 recipients queued | 0.9s |
| Hourly `student_metrics` rebuild | 8s |

Every dashboard read is well under the 2s target. `SendGuard::prime()` makes bulk evaluation load its data in chunks; a test checks that it decides exactly like the per-student checks.

## Subjects

`exams.subject_id` and `subject_source` (migration `2026_09_26_101000`) record which subject a single-subject exam covers. The subject is copied to `questions.subject_id`.

- **Inference:** `php artisan subjects:infer` (a dry run that shows coverage, ambiguous names and unmatched names) and then `php artisan subjects:infer --apply`. `App\Services\Subjects\SubjectCatalog` holds the rules:
  - Subjects are scoped by exam type, so "Ortho" is Orthopedics for medical exams and Orthodontics for dental ones.
  - A subject named in the title beats topic keywords ("MBBS Chemistry Thermodynamics" is Chemistry).
  - Series names belong to the series subject ("MDMS ENT-1-Anatomy of Ear" is ENT).
  - Lists and mixed mocks are left untagged. A wrong subject is worse than none.
- **Manual tags:** set `subject_source = 'manual'` on an exam and inference never overwrites it. Re-run with `--retag` to refresh automatic tags after the catalogue changes.
- **Local result:** 1,190 of 2,439 exams tagged, which is 107,274 questions across 52 subjects. A random sample of 70 was all correct. The rest are multi-subject (184), ambiguous lists (30), generic names (894) or exam types without subject rules (141: pharmacy, radiography, administration, agriculture).
- **Scores:** a student's subject score is their average on that subject's exams. `weakest_subject_*` / `strongest_subject_id` need at least two subjects with two or more scored attempts each. They feed the `has_weak_subject` segment, the `weakest_score_max` condition, `{{weakest_subject}}` / `{{weakest_subject_score}}` in templates, and the drawer's Subjects section.
- **Deploy:** after `marketing:migrate`, run `php artisan subjects:infer`, check the output, then `--apply` and `marketing:refresh-metrics`.
