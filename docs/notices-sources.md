# Notices — Source Verification (2026-09-24)

Verified from Kathmandu (AS141767) on 2026-09-24. For every source: the list
page was fetched, `robots.txt` read, the list parsed with the adapter below
through `php artisan notices:fetch {id} --dry-run`, and a copy of the page
saved to `tests/fixtures/notices/{code}.*` (parsed by
`tests/Unit/Notices/AdapterFixturesTest`). The registry itself lives in
`database/seeders/data/notice_sources.php` (`php artisan db:seed --class=NoticeSourceSeeder`).

**57 sources: 35 fetched automatically, 22 manual** (13 unreachable/unparseable,
9 low-value or covered elsewhere). First real run: 35/35 succeeded, 166 items
new → 153 after fixing one date selector (see below), 2 auto-rejected as tenders.

## robots.txt

All automatic sources either have no robots.txt or allow everything
(`Disallow:` empty), except:

- `www.nepalarmy.mil.np` — **`Disallow: /` for all agents**. Not crawled; Army
  recruitment is read from `vacancy.nepalarmy.mil.np` (no robots.txt) instead.
- `nrb.org.np`, `epf.org.np` — only `/wp-admin/` disallowed.

The crawler re-checks robots.txt every 24 h and refuses disallowed URLs
(`RobotsDisallowedException`, logged as a failed fetch).

## Findings that shaped the adapters

| Finding | Handling |
|---|---|
| **psc.gov.np** is a Vue SPA; data comes from `https://psc.gov.np/front/category/{slug}` (paginated JSON with BS + AD dates and file lists) | `json_api` adapter, 9 category feeds. `sangathit-*` feeds cover public-enterprise recruitment (NEA, NTC, NOC, banks, insurers…) run by PSC; `security-*` feeds cover Army/Police/APF exams run by PSC |
| **nec.gov.np / nnc.org.np** are Next.js apps backed by tRPC | `json_api` on `/api/trpc/notice.getNoticesPublic` |
| **mec.gov.np** (and several others) send only the leaf TLS certificate (missing Sectigo intermediate) | Verification stays on: `resources/certs/notices/*.pem` holds the missing intermediate; the HTTP client verifies against system CAs + those. `verify_ssl=false` is a logged per-source last resort, currently used by none |
| **ppsc.gandaki.gov.np** renders dates client-side from `englishdate="2026-09-15"` attributes | `date_attr` selector option |
| **purbanchaluniversity.edu.np** prints *today's* date on every list item | date read from the slug (`--260829171449` → 2026-08-29) via `date_url_regex` |
| **entrance.ioe.edu.np** uses US `08/06/2026` dates, **kahs.edu.np** `16-09-2026` | explicit `date_format` per source |
| BS dates appear as `२०८३/०६/०८`, `2083-06-08`, `२०८३ चैत २७`, `५ आश्विन २०८३, सोमबार`, `असोज २, २०८३`, `३२ जेष्ठ २०८०` | `NepaliDate::parseBs()` (tests cover each) |
| **Sudurpashchim advertisements**: first run used the wrong column for the date, so 13 old adverts slipped past the 45-day cutoff | selector fixed (`td:nth-child(4)`), the 13 rows deleted and re-fetched; fixture test now asserts every dated source yields a date for every item |
| Tender / auction notices mixed into several boards (Gandaki, MEC, NRB) | `notices.irrelevant_title_regex` stores them as `rejected` without an API call |
| `nams.edu.np` contains **injected gambling-spam links** (site appears compromised) | not crawled (`is_active=false`) |

## BS ↔ AD conversion

`anuzpandey/laravel-nepali-date` 3.3 was cross-checked against
`ernilambar/nepali-date` for the 1st of every month 2070–2089 BS: **240/240
identical**. (ernilambar has no data for 2090; anuzpandey covers it.) Known
pairs from psc.gov.np (which publishes both calendars) are in
`tests/Unit/Notices/NepaliDateTest`.

## Unreachable on 2026-09-24 — re-verify

`tsc.gov.np`, `psc.koshi.gov.np`, `ppsc.madhesh.gov.np`, `spsc.bagamati.gov.np`,
`ppsc.karnali.gov.np`, `nepalbarcouncil.org.np`, `nvc.gov.np`, `nwsc.gov.np`
all resolve to the GIDC load balancer `103.69.124.8`, which answered
`503 No server is available` to every request (not a UA/geo block — a
browser UA from inside Nepal got the same). `ppsc.p2.gov.np` / `ppsc.p5.gov.np`
time out, `www.nea.org.np` fails the TLS handshake, `nhpc.org.np` redirects to
itself in a loop. **TSC is a top-5 priority source** — check it first when
GIDC is back and switch it to `html_list` via the admin "Test fetch".

Not found in DNS: `kppsc.koshi.gov.np`, `ppsc.p1.gov.np`, `ppsc.sudurpashchim.gov.np`
(real domain: `psc.sudurpashchim.gov.np`), `joinnepalarmy.mil.np`, `cit.org.np`.

## All sources

| Source | Category | List URL | Type | First run | Selectors | Notes |
|---|---|---|---|---|---|---|
| Lok Sewa Aayog — Advertisements (Vigyapan) | loksewa / federal_psc | https://psc.gov.np/front/category/notice-advertisement?page=1 | json_api | success (10 items) | `{"date":"upload_date_bs","title":"title_np","date_ad":"upload_date","title_alt":"title","items_path":"data.children.data","attachments":{"url":"location","name":"name","path":"files"},"url_template":"https://psc.gov.np/category/notice-advertisement/{slug}"}` |  |
| Lok Sewa Aayog — Civil Service Exam Programme | loksewa / federal_psc | https://psc.gov.np/front/category/nijamati-exam-program?page=1 | json_api | success (10 items) | `{"date":"upload_date_bs","title":"title_np","date_ad":"upload_date","title_alt":"title","items_path":"data.children.data","attachments":{"url":"location","name":"name","path":"files"},"url_template":"https://psc.gov.np/category/nijamati-exam-program/{slug}"}` |  |
| Teacher Service Commission | loksewa / tsc | https://tsc.gov.np/ | manual | manual |  | Unreachable 2026-09-24 (GIDC 503). High priority - re-verify and convert to html_list; teaching-licence notices go under category=license. |
| Lok Sewa Aayog — Notices | loksewa / federal_psc | https://psc.gov.np/front/category/notice?page=1 | json_api | success (10 items) | `{"date":"upload_date_bs","title":"title_np","date_ad":"upload_date","title_alt":"title","items_path":"data.children.data","attachments":{"url":"location","name":"name","path":"files"},"url_template":"https://psc.gov.np/category/notice/{slug}"}` |  |
| Lok Sewa Aayog — Public Enterprise Vacancies | loksewa / corporation | https://psc.gov.np/front/category/sangathit-vacancies?page=1 | json_api | success (10 items) | `{"date":"upload_date_bs","title":"title_np","date_ad":"upload_date","title_alt":"title","items_path":"data.children.data","attachments":{"url":"location","name":"name","path":"files"},"url_template":"https://psc.gov.np/category/sangathit-vacancies/{slug}"}` |  |
| Lok Sewa Aayog — Security Forces Vacancies | loksewa / security_forces | https://psc.gov.np/front/category/security-vacancies?page=1 | json_api | success (10 items) | `{"date":"upload_date_bs","title":"title_np","date_ad":"upload_date","title_alt":"title","items_path":"data.children.data","attachments":{"url":"location","name":"name","path":"files"},"url_template":"https://psc.gov.np/category/security-vacancies/{slug}"}` |  |
| Nepal Police — Vacancies | loksewa / security_forces | https://www.nepalpolice.gov.np/career/vacancy/ | html_list | success (40 items) | `{"date":"td:first-child","item":"table tbody tr","link":"td.f1 a"}` |  |
| Nepal Army — Vacancy Notices | loksewa / security_forces | https://vacancy.nepalarmy.mil.np/ | html_list | success (15 items) | `{"date":"time.published","item":"article.post","link":"h2.entry-title a"}` | Home page lists the latest recruitment notices. www.nepalarmy.mil.np robots.txt disallows all bots; vacancy. subdomain has no robots.txt. |
| Sudurpashchim Province PSC — Advertisements | loksewa / provincial_psc | https://psc.sudurpashchim.gov.np/advertisements | html_list | success (20 items) | `{"date":"td:nth-child(4)","item":"table tbody tr","link":"a.n-tlink"}` |  |
| Nepal Army — Recruitment Exam Notices | loksewa / security_forces | https://vacancy.nepalarmy.mil.np/exam-notice | html_list | success (20 items) | `{"date":"time.published","item":"article.post","link":"h2.entry-title a"}` |  |
| Nepal Rastra Bank — HR / Recruitment Notices | loksewa / bank | https://www.nrb.org.np/category/notices/feed/?department=hrm | rss | success (5 items) |  |  |
| Gandaki Province PSC — Advertisement Notices | loksewa / provincial_psc | https://ppsc.gandaki.gov.np/list/advertisment_notive | html_list | success (20 items) | `{"date":"span.nepaliDate","item":"li.tap-box-list","link":"a:not(.dn-link)","title":"a:not(.dn-link) p","date_attr":"englishdate"}` |  |
| Lumbini Province PSC — Notices | loksewa / provincial_psc | https://ppsc.lumbini.gov.np/notices | html_list | success (12 items) | `{"date":"td:first-child","item":"tbody.table-hover-css tr","link":"a.list-detail-font","attachments":"a[href*=\".pdf\"]"}` |  |
| Sudurpashchim Province PSC — Notices | loksewa / provincial_psc | https://psc.sudurpashchim.gov.np/notice_list | html_list | success (10 items) | `{"date":".n-date","item":"table tbody tr","link":"a.n-tlink"}` |  |
| Armed Police Force — Notices | loksewa / security_forces | https://www.apf.gov.np/notices | html_list | success (20 items) | `{"date":".pt-3","item":".news-section > div","link":"a","title":"h5.notice-title"}` | General notice board (hospital, college notices mixed in) - relies on AI relevance filtering. |
| Lok Sewa Aayog — Public Enterprise Exam Programme | loksewa / corporation | https://psc.gov.np/front/category/sangathit-examinations?page=1 | json_api | success (10 items) | `{"date":"upload_date_bs","title":"title_np","date_ad":"upload_date","title_alt":"title","items_path":"data.children.data","attachments":{"url":"location","name":"name","path":"files"},"url_template":"https://psc.gov.np/category/sangathit-examinations/{slug}"}` |  |
| Lok Sewa Aayog — Security Forces Exam Programme | loksewa / security_forces | https://psc.gov.np/front/category/security-examinations?page=1 | json_api | success (10 items) | `{"date":"upload_date_bs","title":"title_np","date_ad":"upload_date","title_alt":"title","items_path":"data.children.data","attachments":{"url":"location","name":"name","path":"files"},"url_template":"https://psc.gov.np/category/security-examinations/{slug}"}` |  |
| Gandaki Province PSC — Notice Board | loksewa / provincial_psc | https://ppsc.gandaki.gov.np/list/notice_bord | html_list | success (20 items) | `{"date":"span.nepaliDate","item":"li.tap-box-list","link":"a:not(.dn-link)","title":"a:not(.dn-link) p","date_attr":"englishdate"}` |  |
| Koshi Province PSC | loksewa / provincial_psc | https://psc.koshi.gov.np/ | manual | manual |  |  |
| Madhesh Province PSC | loksewa / provincial_psc | https://ppsc.madhesh.gov.np/ | manual | manual |  |  |
| Bagmati Province PSC | loksewa / provincial_psc | https://spsc.bagamati.gov.np/ | manual | manual |  |  |
| Karnali Province PSC | loksewa / provincial_psc | https://ppsc.karnali.gov.np/ | manual | manual |  |  |
| Rastriya Banijya Bank — Career Notices | loksewa / bank | https://www.rbb.com.np/career | pdf_list | success (10 items) | `{"limit":10,"container":"table"}` |  |
| Lok Sewa Aayog — Public Enterprise Notices | loksewa / corporation | https://psc.gov.np/front/category/sangathit-notices?page=1 | json_api | success (10 items) | `{"date":"upload_date_bs","title":"title_np","date_ad":"upload_date","title_alt":"title","items_path":"data.children.data","attachments":{"url":"location","name":"name","path":"files"},"url_template":"https://psc.gov.np/category/sangathit-notices/{slug}"}` |  |
| Lok Sewa Aayog — Security Forces Notices | loksewa / security_forces | https://psc.gov.np/front/category/security-notices?page=1 | json_api | success (8 items) | `{"date":"upload_date_bs","title":"title_np","date_ad":"upload_date","title_alt":"title","items_path":"data.children.data","attachments":{"url":"location","name":"name","path":"files"},"url_template":"https://psc.gov.np/category/security-notices/{slug}"}` |  |
| Civil Aviation Authority of Nepal — Career Notices | loksewa / corporation | https://caanepal.gov.np/career-notice | manual | manual |  | Career notices are listed on the home page (career-notice/{slug}) but /career-notice list is client-rendered - candidate for html_list on the home page. |
| Agricultural Development Bank — Careers | loksewa / bank | https://adbl.gov.np/en | manual | manual |  | No career/vacancy link found on home page. |
| Nepal Bank Ltd — Careers | loksewa / bank | https://www.nepalbank.com.np/career | manual | manual |  | Career list not in server HTML; applications via jobs.nepalbank.com.np. |
| Nepal Electricity Authority — Vacancies | loksewa / corporation | https://www.nea.org.np/ | manual | manual |  | TLS handshake failed (SSL_ERROR_SYSCALL) on 2026-09-24. |
| Nepal Telecom — Careers | loksewa / corporation | https://www.ntc.net.np/post/new-career | manual | manual |  | Nuxt app; posts listed under "See Also" on /post/new-career. |
| Nepal Oil Corporation — Vacancies | loksewa / corporation | https://noc.org.np/vacancy | manual | manual |  | Vacancy page has no parseable list; recruitment via erecruitment.nepaloil.org.np. |
| Medical Education Commission — Notices | entrance / medical_entrance | https://mec.gov.np/category/notice | html_list | success (10 items) | `{"date":".date","item":".listing .blog","link":".has-url","title":"h4","link_attr":"data-href"}` |  |
| IOE Entrance Board — Notices | entrance / engineering_entrance | https://entrance.ioe.edu.np/Notice | html_list | success (12 items) | `{"date":"td:nth-child(3)","item":"table tbody tr","link":"a","title":"td:nth-child(2)","date_format":"m/d/Y"}` |  |
| IOST (TU) — Notices | entrance / science_entrance | https://iost.tu.edu.np/notices | html_list | success (6 items) | `{"date":".nep_date","item":".notices-warpper .recent-post-wrapper","link":".detail a","title":"h5"}` | Mostly semester results; entrance (CSIT/BSc/BIT) notices appear here too. Entrance portals themselves (entrancecsit.iost.tu.edu.np) have no notice list. |
| BPKIHS — Notices | entrance / medical_entrance | https://bpkihs.edu/download/notice | html_list | success (6 items) | `{"date":"span.text-xs","item":"[data-notice-container] > a","link":"@self","title":"span.font-semibold"}` | Staff vacancies + academic notices; BPKIHS runs its own recruitment exams. |
| CTEVT — Notice Board | entrance / ctevt_entrance | https://ctevt.org.np/documents/list/notice-board | html_list | success (13 items) | `{"date":"p.des","item":"li","link":".notice-link a"}` |  |
| TU Faculty of Management (CMAT) | entrance / management_entrance | https://fom.tu.edu.np/notices | manual | manual |  | Notice list last updated 2024-02 - CMAT notices must be added manually. |
| Pokhara University — Notices | entrance / university_entrance | https://pu.edu.np/feed/?post_type=notice | rss | success (10 items) |  |  |
| Kathmandu University — News & Notices | entrance / university_entrance | https://ku.edu.np/news-app | html_list | success (10 items) | `{"item":".news-wrap-item","link":"a.my-a","title":".primary-box__title"}` | Mixed news + notices; relies on AI relevance filtering. List shows no publish date (enrichment extracts it). |
| Karnali Academy of Health Sciences — Notices | entrance / medical_entrance | https://kahs.edu.np/post/notices | html_list | success (12 items) | `{"date":"date ins","item":"li.list-group-item","link":"header a","date_format":"d-m-Y"}` |  |
| Tribhuvan University — Central Notices | entrance / university_entrance | https://tu.edu.np/notices | manual | manual | `{"date":".nep_date","item":".notices-warpper .recent-post-wrapper","link":".detail a","title":"h5"}` | Main notice list is not in the server-rendered HTML (0 items with the IOST selectors) - manual until mapped. |
| Purbanchal University — Notices | entrance / university_entrance | https://purbanchaluniversity.edu.np/notice/list | html_list | success (10 items) | `{"item":".timeline-item","link":"a","title":"h2","date_url_regex":"--(?<y>\\d{2})(?<m>\\d{2})(?<d>\\d{2})\\d{6}"}` |  |
| Patan Academy of Health Sciences | entrance / medical_entrance | https://web.pahs.edu.np/ | manual | manual |  | pahs.edu.np meta-refreshes to web.pahs.edu.np; MBBS entrance runs via MEC. |
| National Academy of Medical Sciences (NAMS) | entrance / pg_entrance | https://nams.edu.np/en/exams-and-results | manual | manual |  | Site contained injected gambling-spam links on 2026-09-24 (compromised) - do not scrape until cleaned. PG entrance runs via MEC. |
| Medical Education Commission — Results | entrance / medical_entrance | https://mec.gov.np/category/result | html_list | success (10 items) | `{"date":".date","item":".listing .blog","link":".has-url","title":"h4","link_attr":"data-href"}` |  |
| Far Western University | entrance / university_entrance | https://www.fwu.edu.np/ | manual | manual |  | Home page only exposes news items; no stable notice list found. |
| Mid-West University | entrance / university_entrance | https://mu.edu.np/news-and-events | manual | manual |  | Only news-and-events list; entrance via entrance.mwu.edu.np portal. |
| Lumbini Buddhist University — News | entrance / university_entrance | https://lbu.edu.np/feed/ | rss | success (10 items) |  |  |
| Nepal Medical Council — Exam Notices | license / medical_license | https://nmc.org.np/exam-notice | manual | manual |  | Blazor Server app - content is rendered over a websocket, nothing to parse over HTTP. Add NMC licensing notices manually. |
| Nepal Engineering Council — Notices | license / engineering_license | https://nec.gov.np/api/trpc/notice.getNoticesPublic?input=%7B%22json%22%3A%7B%22page%22%3A1%2C%22limit%22%3A20%2C%22sortBy%22%3A%22createdAt%22%2C%22sortOrder%22%3A%22desc%22%7D%7D | json_api | success (10 items) | `{"title":"title","date_ad":"createdAt","items_path":"result.data.notices","attachments":{"url":"image","name":"fileName","path":"attachments"},"url_template":"https://nec.gov.np/notices/{_id}"}` | Next.js site backed by tRPC (notice.getNoticesPublic). |
| Nepal Nursing Council — Notices | license / nursing_license | https://nnc.org.np/api/trpc/notice.getNoticesPublic?input=%7B%22json%22%3A%7B%22page%22%3A1%2C%22limit%22%3A20%2C%22sortBy%22%3A%22createdAt%22%2C%22sortOrder%22%3A%22desc%22%7D%7D | json_api | success (10 items) | `{"title":"title","date_ad":"createdAt","items_path":"result.data.notices","attachments":{"url":"image","name":"fileName","path":"attachments"},"url_template":"https://nnc.org.np/notices/{_id}"}` | Next.js site backed by tRPC (notice.getNoticesPublic). |
| Nepal Pharmacy Council — News & Notices | license / pharmacy_license | https://nepalpharmacycouncil.org.np/news | html_list | success (20 items) | `{"date":".npc__news-item-right p","item":".npc__news-item","link":"a.npc__news-item-title"}` |  |
| Nepal Health Professional Council | license / health_professional_license | https://nhpc.org.np/ | manual | manual |  | https://nhpc.org.np/ was stuck in a redirect loop on 2026-09-24. |
| Nepal Bar Council | license / law_license | https://nepalbarcouncil.org.np/ | manual | manual |  | Unreachable 2026-09-24 (GIDC 503). |
| Institute of Chartered Accountants of Nepal | license / ca_license | https://en.ican.org.np/en/ | manual | manual |  | www.ican.org.np meta-refreshes to en.ican.org.np; notice list not yet mapped. |
| Nepal Ayurveda Medical Council — Notices | license / ayurveda_license | https://namc.gov.np/ | html_list | success (13 items) | `{"date":".list p","item":"#tab_id1 li","link":".list a"}` |  |
| Nepal Veterinary Council | license / veterinary_license | https://nvc.gov.np/ | manual | manual |  | Unreachable 2026-09-24 (GIDC 503). |
