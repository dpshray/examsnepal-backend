<?php

namespace App\Services\Marketing;

use App\Enums\ExamTypeEnum;
use App\Models\Marketing\EmailTemplate;
use App\Models\Marketing\MessageSend;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Turns a template + student into subject / html / text. `{{var}}` placeholders
 * are filled from the student's profile and metrics; in a real send every link
 * is routed through click tracking with UTM tags, an open pixel is added, and
 * the footer carries the one-click unsubscribe link.
 */
class TemplateRenderer
{
    public const VARIABLES = [
        'first_name', 'target_exam', 'days_to_exam', 'total_attempts', 'last_score', 'avg_score', 'best_score',
        'weakest_subject', 'weakest_subject_score', 'percentile', 'top_percent', 'streak', 'next_free_quiz_date',
        'subscription_ends_at', 'cta_url', 'pricing_url', 'site_url',
        'attempts_this_week', 'avg_score_this_week', 'avg_score_last_week', 'students_active_this_week',
        'recommended_exam', 'recommended_exam_url', 'free_quiz_url', 'sprint_url', 'mock_url', 'profile_url', 'dashboard_url',
    ];

    public const PATHS = [
        'free_quiz_url' => '/student/exams/free-quiz',
        'sprint_url' => '/student/exams/sprint-quiz',
        'mock_url' => '/student/exams/mock-tests',
        'pricing_url' => '/student/subscription',
        'profile_url' => '/student/profile',
        'dashboard_url' => '/student/dashboard',
    ];

    /** @return array<string,string> */
    public function variables(int $studentId, ?EmailTemplate $template = null, array $context = []): array
    {
        $p = DB::table('student_profiles as p')->leftJoin('exam_types as et', 'et.id', '=', 'p.exam_type_id')
            ->where('p.id', $studentId)->first(['p.name', 'p.exam_type_id', 'p.target_exam_date', 'et.name as exam_name']);
        $m = DB::table('student_metrics')->where('student_id', $studentId)->first();
        $site = config('marketing.site_url');
        $pct = fn ($v) => $v === null ? '' : (string) round((float) $v);
        $percentile = $m?->percentile_in_exam;

        $week = $this->weeklyStats($studentId);
        $recommended = $this->recommendedExam($studentId, $p?->exam_type_id, in_array($m?->subscription_status, ['active', 'expiring_soon'], true));

        return [
            ...array_map(fn ($path) => $site . $path, self::PATHS),
            'attempts_this_week' => (string) $week['this_week'],
            // These two include the % sign and show "–" for a week without scores.
            'avg_score_this_week' => $week['avg_this_week'] === null ? '–' : $pct($week['avg_this_week']) . '%',
            'avg_score_last_week' => $week['avg_last_week'] === null ? '–' : $pct($week['avg_last_week']) . '%',
            'students_active_this_week' => $p?->exam_type_id
                ? number_format(DB::table('student_metrics')->where('exam_type_id', $p->exam_type_id)->where('attempts_last_7d', '>', 0)->count())
                : '',
            'recommended_exam' => $recommended['name'] ?? '',
            'recommended_exam_url' => $site . ($recommended['path'] ?? self::PATHS['free_quiz_url']),
            'first_name' => $this->firstName($p->name ?? ''),
            'target_exam' => $this->shortExamName($p->exam_name ?? 'your exam'),
            'days_to_exam' => $p?->target_exam_date ? (string) max(0, (int) now()->startOfDay()->diffInDays(Carbon::parse($p->target_exam_date), false)) : '',
            'total_attempts' => (string) ($m->total_attempts ?? 0),
            'last_score' => $pct($context['score_pct'] ?? $m?->last_score_pct),
            'avg_score' => $pct($m?->avg_score_pct),
            'best_score' => $pct($m?->best_score_pct),
            'weakest_subject' => $m?->weakest_subject_id ? (string) DB::table('subjects')->where('id', $m->weakest_subject_id)->value('name') : '',
            'weakest_subject_score' => $pct($m?->weakest_subject_score_pct),
            'percentile' => $pct($percentile),
            'top_percent' => $percentile === null ? '' : (string) max(1, (int) round(100 - (float) $percentile)),
            'streak' => (string) ($m->current_streak_days ?? 0),
            'next_free_quiz_date' => $this->nextFreeQuizDate($p?->exam_type_id),
            'subscription_ends_at' => $m?->subscription_ends_at ? Carbon::parse($m->subscription_ends_at)->format('j M Y') : '',
            'cta_url' => $site . '/' . ltrim($template?->cta_path ?? '', '/'),
            'site_url' => $site,
        ];
    }

    /**
     * @return array{subject:string, preheader:string, html:string, text:string}
     */
    public function render(EmailTemplate $template, int $studentId, ?MessageSend $send = null, array $context = []): array
    {
        $vars = $this->variables($studentId, $template, $context);
        $campaign = $send?->automation?->key ?? ($send?->broadcast_id ? "broadcast_{$send->broadcast_id}" : $template->key);

        $body = $this->fill($template->html_body, $vars, html: true);
        if ($template->cta_label) {
            $body .= $this->button($this->fill($template->cta_label, $vars), $vars['cta_url']);
        }

        $unsubscribeUrl = $send && !$template->isTransactional() ? self::unsubscribeUrl($send) : null;
        $html = view('mail.marketing.layout', [
            'preheader' => $this->fill((string) $template->preheader, $vars),
            'body' => $body,
            'unsubscribeUrl' => $unsubscribeUrl,
            'postalAddress' => config('marketing.mail.postal_address'),
            'openPixel' => $send ? URL::signedRoute('marketing.open', ['send' => $send->id]) : null,
        ])->render();

        if ($send) {
            $html = $this->trackLinks($html, $send, $campaign, $unsubscribeUrl);
        }

        $text = $template->text_body
            ? $this->fill($template->text_body, $vars)
            : $this->htmlToText($body) . ($template->cta_label ? "\n\n" . $this->fill($template->cta_label, $vars) . ": {$vars['cta_url']}" : '');
        if ($unsubscribeUrl) {
            $text .= "\n\n--\nUnsubscribe from these emails: {$unsubscribeUrl}";
        }

        return [
            'subject' => $this->fill($template->subject, $vars),
            'preheader' => $this->fill((string) $template->preheader, $vars),
            'html' => $html,
            'text' => $text,
        ];
    }

    public static function unsubscribeUrl(MessageSend $send): string
    {
        return URL::signedRoute('marketing.unsubscribe', ['send' => $send->id]);
    }

    /** Adds UTM tags to links into our own site; other links are left alone. */
    public static function withUtm(string $url, string $campaign): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $siteHost = parse_url(config('marketing.site_url'), PHP_URL_HOST);
        if (!$host || !$siteHost || !str_ends_with($host, preg_replace('/^www\./', '', $siteHost))) {
            return $url;
        }
        $utm = http_build_query(['utm_source' => 'email', 'utm_medium' => 'lifecycle', 'utm_campaign' => $campaign]);
        $fragment = '';
        if (($pos = strpos($url, '#')) !== false) {
            [$url, $fragment] = [substr($url, 0, $pos), substr($url, $pos)];
        }
        return $url . (str_contains($url, '?') ? '&' : '?') . $utm . $fragment;
    }

    private function trackLinks(string $html, MessageSend $send, string $campaign, ?string $unsubscribeUrl): string
    {
        return preg_replace_callback('/href="(https?:\/\/[^"]+)"/i', function ($m) use ($send, $campaign, $unsubscribeUrl) {
            $url = html_entity_decode($m[1]);
            if ($unsubscribeUrl && $url === $unsubscribeUrl) {
                return $m[0];
            }
            $tracked = URL::signedRoute('marketing.click', ['send' => $send->id, 'u' => self::withUtm($url, $campaign)]);
            return 'href="' . e($tracked) . '"';
        }, $html);
    }

    private function fill(string $text, array $vars, bool $html = false): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($m) use ($vars, $html) {
            $value = $vars[$m[1]] ?? '';
            return $html ? e($value) : $value;
        }, $text);
    }

    private function button(string $label, string $url): string
    {
        return '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0"><tr><td style="border-radius:8px;background:#16803c">'
            . '<a href="' . e($url) . '" style="display:inline-block;padding:12px 24px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px">'
            . e($label) . '</a></td></tr></table>';
    }

    private function htmlToText(string $html): string
    {
        $text = preg_replace(['/<br\s*\/?>/i', '/<\/(p|div|h[1-6]|li|tr)>/i'], "\n", $html);
        $text = html_entity_decode(strip_tags($text));
        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text)));
    }

    private function firstName(string $name): string
    {
        $first = trim(explode(' ', trim($name))[0] ?? '');
        return $first !== '' ? mb_convert_case($first, MB_CASE_TITLE) : 'there';
    }

    /** "Medical NMCLE / Loksewa / MDMS Exams" -> "Medical NMCLE / Loksewa / MDMS". */
    private function shortExamName(string $name): string
    {
        return trim(preg_replace('/\s+Exams?$/i', '', $name));
    }

    /** @return array{this_week:int, avg_this_week:?float, avg_last_week:?float} */
    private function weeklyStats(int $studentId): array
    {
        $rows = DB::table('student_exams')->where('student_id', $studentId)->where('is_exam_completed', 1)
            ->where('submitted_at', '>=', now()->subDays(14))
            ->get(['submitted_at', 'score_pct']);
        $cut = now()->subDays(7);
        $thisWeek = $rows->filter(fn ($r) => Carbon::parse($r->submitted_at)->gte($cut));
        $lastWeek = $rows->reject(fn ($r) => Carbon::parse($r->submitted_at)->gte($cut));
        $avg = fn ($c) => $c->whereNotNull('score_pct')->isEmpty() ? null : $c->whereNotNull('score_pct')->avg('score_pct');

        return ['this_week' => $thisWeek->count(), 'avg_this_week' => $avg($thisWeek), 'avg_last_week' => $avg($lastWeek)];
    }

    /**
     * A live exam for the student's target exam they haven't taken: a Mock for
     * payers, otherwise a Sprint (the step up from free quizzes).
     *
     * @return array{name:string, path:string}|null
     */
    private function recommendedExam(int $studentId, ?int $examTypeId, bool $paying): ?array
    {
        if (!$examTypeId) {
            return null;
        }
        $order = $paying
            ? [ExamTypeEnum::MOCK_TEST->value => 'mock_url', ExamTypeEnum::SPRINT_QUIZ->value => 'sprint_url']
            : [ExamTypeEnum::SPRINT_QUIZ->value => 'sprint_url', ExamTypeEnum::MOCK_TEST->value => 'mock_url', ExamTypeEnum::FREE_QUIZ->value => 'free_quiz_url'];

        foreach ($order as $status => $pathKey) {
            $name = DB::table('exams')
                ->where('exam_type_id', $examTypeId)->where('status', (string) $status)->where('live', 1)
                ->whereNotExists(fn ($q) => $q->from('student_exams')->whereColumn('student_exams.exam_id', 'exams.id')->where('student_exams.student_id', $studentId))
                ->orderByDesc('id')
                ->value('exam_name');
            if ($name) {
                return ['name' => $name, 'path' => self::PATHS[$pathKey]];
            }
        }
        return null;
    }

    private function nextFreeQuizDate(?int $examTypeId): string
    {
        if (!$examTypeId) {
            return '';
        }
        $date = DB::table('exams')
            ->where('exam_type_id', $examTypeId)
            ->where('status', ExamTypeEnum::FREE_QUIZ->value)
            ->where('exam_mode', 'scheduled')
            ->where('exam_date', '>', now()->toDateString())
            ->min('exam_date');

        return $date ? Carbon::parse($date)->format('l, j M') : '';
    }
}
