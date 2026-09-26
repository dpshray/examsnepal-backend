<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Mail\Marketing\MarketingMessage;
use App\Models\Marketing\Automation;
use App\Models\Marketing\Broadcast;
use App\Models\Marketing\EmailTemplate;
use App\Models\Marketing\MessageSend;
use App\Services\Marketing\AbTestAnalyzer;
use App\Services\Marketing\AutomationEngine;
use App\Services\Marketing\MarketingSettings;
use App\Services\Marketing\SendWindow;
use App\Services\Marketing\StudentFilter;
use App\Services\Marketing\Suppressions;
use App\Services\Marketing\TemplateRenderer;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

/** Email automation admin API (docs/marketing.md, Phase 4). */
class AdminMessagingController extends Controller
{
    use PaginatorTrait;

    /** GET admin/marketing/messaging/status */
    public function status()
    {
        $today = now()->startOfDay();

        return Response::apiSuccess('Messaging status', [
            'paused' => MarketingSettings::paused(),
            'paused_changed' => MarketingSettings::get(MarketingSettings::PAUSED . '_changed_by'),
            'send_window_open' => SendWindow::isOpen(now()),
            'next_window_at' => SendWindow::next(now())->toIso8601String(),
            'queued' => MessageSend::where('status', MessageSend::QUEUED)->count(),
            'sent_today' => MessageSend::where('sent_at', '>=', $today)->count(),
            'sent_last_hour' => MessageSend::where('sent_at', '>=', now()->subHour())->count(),
            'max_per_hour' => (int) config('marketing.mail.max_per_hour'),
            'suppressed_addresses' => DB::table('suppressions')->count(),
            'caps' => config('marketing.caps'),
            'send_windows' => config('marketing.send_windows'),
            'from' => config('marketing.mail.from_name') . ' <' . config('marketing.mail.from_address') . '>',
        ]);
    }

    /** POST admin/marketing/messaging/pause {paused: bool} - global kill switch. */
    public function pause(Request $request)
    {
        $paused = $request->validate(['paused' => 'required|boolean'])['paused'];
        MarketingSettings::set(MarketingSettings::PAUSED, (bool) $paused);
        // Audit trail: who flipped the kill switch, and when.
        MarketingSettings::set(MarketingSettings::PAUSED . '_changed_by', ['admin_id' => Auth::id(), 'at' => now()->toIso8601String(), 'paused' => (bool) $paused]);
        Log::info('Marketing kill switch ' . ($paused ? 'PAUSED' : 'RESUMED'), ['admin_id' => Auth::id()]);

        return Response::apiSuccess($paused ? 'All automations paused' : 'Automations resumed', ['paused' => (bool) $paused]);
    }

    /** GET admin/marketing/automations?days=30 - list with per-variant performance. */
    public function automations(Request $request)
    {
        $days = max(1, min(365, (int) $request->query('days', 30)));
        $stats = $this->stats(now()->subDays($days));
        $templates = EmailTemplate::pluck('name', 'key');

        $rows = Automation::orderByDesc('priority')->orderBy('key')->get()->map(fn (Automation $a) => [
            ...$a->only(['id', 'key', 'name', 'channel', 'allow_sms', 'schedule', 'trigger_type', 'trigger_event', 'conditions', 'template_key',
                'variant_b_template_key', 'ab_split_pct', 'delay_minutes', 'goal_event', 'goal_properties', 'cooldown_days',
                'priority', 'is_upsell', 'is_active']),
            'template_name' => $templates[$a->template_key] ?? null,
            'queued' => MessageSend::where('automation_id', $a->id)->where('status', MessageSend::QUEUED)->count(),
            'stats' => $stats->get($a->id, collect())->values(),
            'ab' => $a->variant_b_template_key ? (new AbTestAnalyzer())->analyze($a) : null,
        ]);

        return Response::apiSuccess('Automations', ['days' => $days, 'automations' => $rows]);
    }

    /** PATCH admin/marketing/automations/{automation} - operational knobs (full editor comes with the template UI). */
    public function updateAutomation(Request $request, Automation $automation)
    {
        $data = $request->validate([
            'is_active' => 'sometimes|boolean',
            'delay_minutes' => 'sometimes|integer|min:0|max:100000',
            'cooldown_days' => 'sometimes|integer|min:0|max:3650',
            'priority' => 'sometimes|integer|min:0|max:1000',
            'ab_split_pct' => 'sometimes|integer|min:1|max:99',
            'template_key' => 'sometimes|string|exists:email_templates,key',
            'variant_b_template_key' => 'sometimes|nullable|string|exists:email_templates,key',
        ]);
        $b = array_key_exists('variant_b_template_key', $data) ? $data['variant_b_template_key'] : $automation->variant_b_template_key;
        abort_if($b && $b === ($data['template_key'] ?? $automation->template_key), 422, 'Variant B must use a different template than variant A.');
        $automation->update($data);

        return Response::apiSuccess('Automation updated', $automation->fresh());
    }

    /** POST admin/marketing/automations/{automation}/promote {variant: A|B} - end the A/B test, keep one variant. */
    public function promote(Request $request, Automation $automation)
    {
        $variant = $request->validate(['variant' => 'required|in:A,B'])['variant'];
        abort_if(!$automation->variant_b_template_key, 422, 'This automation has no A/B test running.');

        $automation->update([
            'template_key' => $automation->templateKeyFor($variant),
            'variant_b_template_key' => null,
        ]);

        return Response::apiSuccess("Variant {$variant} is now the only version", $automation->fresh());
    }

    /** GET admin/marketing/automations/{automation}/preview - dry run, writes nothing. */
    public function preview(Automation $automation)
    {
        $result = (new AutomationEngine())->preview($automation, 50)[0];
        $result['note'] = $automation->trigger_type === Automation::TRIGGER_EVENT
            ? "Event automation: shows who would get it if \"{$automation->trigger_event}\" happened for them right now."
            : 'Scheduled automation: these students would be queued on the next hourly run.';

        return Response::apiSuccess('Dry run', $result);
    }

    /** GET admin/marketing/sends?status=&automation_id=&student_id=&q= */
    public function sends(Request $request)
    {
        $page = MessageSend::query()
            ->from('message_sends as s')
            ->leftJoin('automations as a', 'a.id', '=', 's.automation_id')
            ->join('student_profiles as p', 'p.id', '=', 's.student_id')
            ->when($request->query('status'), fn ($q, $v) => $q->where('s.status', $v))
            ->when($request->query('automation_id'), fn ($q, $v) => $q->where('s.automation_id', $v))
            ->when($request->query('student_id'), fn ($q, $v) => $q->where('s.student_id', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('p.email', 'like', "%{$v}%")->orWhere('p.name', 'like', "%{$v}%")))
            ->orderByDesc('s.id')
            ->select([
                's.id', 's.student_id', 'p.name', 'p.email', 'a.key as automation_key', 's.template_key', 's.variant',
                's.category', 's.status', 's.suppress_reason', 's.subject', 's.scheduled_for', 's.sent_at',
                's.opened_at', 's.clicked_at', 's.bounced_at', 's.unsubscribed_at', 's.goal_event', 's.goal_met_at', 's.error',
            ])
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return Response::apiSuccess('Message log', $this->setupPagination($page)->data);
    }

    /** GET admin/marketing/suppressions?q= */
    public function suppressions(Request $request)
    {
        $page = DB::table('suppressions')
            ->when($request->query('q'), fn ($q, $v) => $q->where('email', 'like', '%' . addcslashes($v, '%_\\') . '%'))
            ->when($request->query('reason'), fn ($q, $v) => $q->where('reason', $v))
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return Response::apiSuccess('Suppressions', $this->setupPagination($page)->data);
    }

    /** POST admin/marketing/suppressions {email} - manually block an address. */
    public function suppress(Request $request)
    {
        $email = $request->validate(['email' => 'required|email|max:255'])['email'];
        Suppressions::add($email, Suppressions::MANUAL, 'added by admin #' . Auth::id());

        return Response::apiSuccess('Address suppressed', null, 201);
    }

    /** DELETE admin/marketing/suppressions/{id} */
    public function unsuppress(int $id)
    {
        $row = DB::table('suppressions')->where('id', $id)->first();
        abort_if(!$row, 404, 'Not found');
        Suppressions::remove($row->email);
        if ($row->reason === Suppressions::UNSUBSCRIBED) {
            DB::table('student_profiles')->whereRaw('LOWER(email) = ?', [$row->email])
                ->update(['marketing_email_opt_in' => true, 'unsubscribed_at' => null]);
        }

        return Response::apiSuccess('Suppression removed');
    }

    /** GET admin/marketing/templates */
    public function templates()
    {
        return Response::apiSuccess('Email templates', [
            'templates' => EmailTemplate::orderBy('key')->get()->map(fn ($t) => $t->toArray() + ['used_by' => Automation::where('template_key', $t->key)->orWhere('variant_b_template_key', $t->key)->pluck('key')]),
            'variables' => TemplateRenderer::VARIABLES,
        ]);
    }

    /** POST admin/marketing/templates */
    public function storeTemplate(Request $request)
    {
        $data = $this->validateTemplate($request);
        $template = EmailTemplate::create($data);

        return Response::apiSuccess('Template created', $template, 201);
    }

    /** PUT admin/marketing/templates/{template} */
    public function updateTemplate(Request $request, EmailTemplate $template)
    {
        $data = $this->validateTemplate($request, $template);
        unset($data['key']); // keys are referenced by automations and sends
        $template->update($data);

        return Response::apiSuccess('Template saved', $template->fresh());
    }

    /** DELETE admin/marketing/templates/{template} - only when nothing uses it. */
    public function destroyTemplate(EmailTemplate $template)
    {
        $inUse = Automation::where('template_key', $template->key)->orWhere('variant_b_template_key', $template->key)->exists()
            || Broadcast::where('template_key', $template->key)->where('status', Broadcast::SCHEDULED)->exists();
        abort_if($inUse, 422, 'This template is used by an automation or scheduled broadcast.');
        $template->delete();

        return Response::apiSuccess('Template deleted');
    }

    // ------------------------------------------------------------ broadcasts

    /** GET admin/marketing/broadcasts */
    public function broadcasts()
    {
        $stats = DB::table('message_sends')->whereNotNull('broadcast_id')->groupBy('broadcast_id')->get([
            'broadcast_id',
            DB::raw("SUM(CASE WHEN status IN ('sent','delivered','opened','clicked','bounced') THEN 1 ELSE 0 END) as sent"),
            DB::raw("SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued"),
            DB::raw("SUM(CASE WHEN status = 'suppressed' THEN 1 ELSE 0 END) as suppressed"),
            DB::raw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked'),
            DB::raw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened'),
        ])->keyBy('broadcast_id');

        return Response::apiSuccess('Broadcasts', Broadcast::orderByDesc('id')->limit(100)->get()->map(fn (Broadcast $b) => $b->toArray() + [
            'stats' => $stats->get($b->id),
        ]));
    }

    /** POST admin/marketing/broadcasts/preview {template_key, filters} - recipients now, nothing written. */
    public function previewBroadcast(Request $request)
    {
        $data = $request->validate([
            'template_key' => 'required|string|exists:email_templates,key',
            'filters' => 'present|array',
        ]);
        $preview = (new AutomationEngine())->previewBroadcast(StudentFilter::clean($data['filters']), $data['template_key']);
        $perDay = (int) config('marketing.mail.max_per_hour') * 4.5; // hours of send window per day
        $preview['estimated_days'] = $perDay > 0 ? (int) ceil($preview['would_send'] / $perDay) : null;

        return Response::apiSuccess('Broadcast dry run', $preview);
    }

    /** POST admin/marketing/broadcasts {name, template_key, filters, scheduled_for?} */
    public function storeBroadcast(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'template_key' => 'required|string|exists:email_templates,key',
            'filters' => 'required|array',
            'scheduled_for' => 'nullable|date|after_or_equal:' . now()->subMinute()->toDateTimeString(),
        ]);
        $filters = StudentFilter::clean($data['filters']);
        abort_if(!$filters, 422, 'Pick a segment or at least one filter. Broadcasting to everyone is not allowed.');

        $broadcast = Broadcast::create([
            'name' => $data['name'],
            'template_key' => $data['template_key'],
            'filters' => $filters,
            // Browsers send ISO times in UTC; store in the app's timezone like every other timestamp.
            'scheduled_for' => isset($data['scheduled_for']) ? \Carbon\Carbon::parse($data['scheduled_for'])->setTimezone(config('app.timezone')) : now(),
            'created_by' => Auth::id(),
        ]);

        return Response::apiSuccess('Broadcast scheduled', $broadcast, 201);
    }

    /** POST admin/marketing/broadcasts/{broadcast}/cancel - stops anything not yet sent. */
    public function cancelBroadcast(Broadcast $broadcast)
    {
        abort_if($broadcast->status === Broadcast::CANCELLED, 422, 'Already cancelled.');
        $dropped = MessageSend::where('broadcast_id', $broadcast->id)->where('status', MessageSend::QUEUED)
            ->update(['status' => MessageSend::SUPPRESSED, 'suppress_reason' => 'broadcast_cancelled', 'updated_at' => now()]);
        $broadcast->update(['status' => Broadcast::CANCELLED]);

        return Response::apiSuccess("Broadcast cancelled ({$dropped} unsent message(s) dropped)");
    }

    private function validateTemplate(Request $request, ?EmailTemplate $template = null): array
    {
        return $request->validate([
            'key' => [$template ? 'sometimes' : 'required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('email_templates', 'key')->ignore($template?->id)],
            'name' => 'required|string|max:150',
            'category' => ['required', Rule::in(EmailTemplate::CATEGORIES)],
            'subject' => 'required|string|max:200',
            'preheader' => 'nullable|string|max:200',
            'html_body' => 'required|string|max:100000',
            'text_body' => 'nullable|string|max:50000',
            'cta_label' => 'nullable|string|max:60',
            'cta_path' => ['nullable', 'string', 'max:255', 'regex:#^/[^\s]*$#'],
            'is_active' => 'sometimes|boolean',
        ]);
    }

    /** POST admin/marketing/templates/{template}/test {student_id?} - render with a real student's data and send to the admin. */
    public function sendTest(Request $request, EmailTemplate $template)
    {
        $data = $request->validate(['student_id' => 'nullable|integer|exists:student_profiles,id']);
        $admin = Auth::user();
        abort_if(!$admin?->email, 422, 'Your admin account has no email address.');
        $studentId = $data['student_id'] ?? DB::table('student_metrics')->orderByDesc('total_attempts')->value('student_id');
        abort_if(!$studentId, 422, 'No student data to render with yet.');

        $rendered = (new TemplateRenderer())->render($template, (int) $studentId);
        Mail::to($admin->email)->send(new MarketingMessage('[TEST] ' . $rendered['subject'], $rendered['html'], $rendered['text'], 0));

        return Response::apiSuccess("Test sent to {$admin->email}", ['rendered_for_student_id' => $studentId]);
    }

    /** GET admin/marketing/templates/{template}/preview?student_id= - rendered HTML, nothing sent. */
    public function renderTemplate(Request $request, EmailTemplate $template)
    {
        $studentId = $request->integer('student_id') ?: DB::table('student_metrics')->orderByDesc('total_attempts')->value('student_id');
        abort_if(!$studentId, 422, 'No student data to render with yet.');

        return Response::apiSuccess('Rendered template', (new TemplateRenderer())->render($template, (int) $studentId) + ['student_id' => $studentId]);
    }

    /** POST admin/marketing/templates/draft-preview {template fields, student_id?} - render unsaved edits. */
    public function draftPreview(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:200',
            'preheader' => 'nullable|string|max:200',
            'html_body' => 'required|string|max:100000',
            'text_body' => 'nullable|string|max:50000',
            'cta_label' => 'nullable|string|max:60',
            'cta_path' => 'nullable|string|max:255',
            'category' => ['nullable', Rule::in(EmailTemplate::CATEGORIES)],
            'student_id' => 'nullable|integer|exists:student_profiles,id',
        ]);
        $studentId = $data['student_id'] ?? DB::table('student_metrics')->orderByDesc('total_attempts')->value('student_id');
        abort_if(!$studentId, 422, 'No student data to render with yet.');
        $draft = new EmailTemplate(collect($data)->except('student_id')->all() + ['key' => 'draft', 'category' => 'lifecycle']);

        return Response::apiSuccess('Draft preview', (new TemplateRenderer())->render($draft, (int) $studentId) + ['student_id' => $studentId]);
    }

    /** Per automation and variant: sent, opened, clicked, bounced, unsubscribed, suppressed, goal conversions. */
    private function stats($since)
    {
        return DB::table('message_sends')
            ->whereNotNull('automation_id')
            ->where('created_at', '>=', $since)
            ->groupBy('automation_id', 'variant')
            ->get([
                'automation_id', 'variant',
                DB::raw("SUM(CASE WHEN status IN ('sent','delivered','opened','clicked','bounced') THEN 1 ELSE 0 END) as sent"),
                DB::raw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened'),
                DB::raw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked'),
                DB::raw('SUM(CASE WHEN bounced_at IS NOT NULL THEN 1 ELSE 0 END) as bounced'),
                DB::raw('SUM(CASE WHEN unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as unsubscribed'),
                DB::raw("SUM(CASE WHEN status = 'suppressed' THEN 1 ELSE 0 END) as suppressed"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw('SUM(CASE WHEN goal_met_at IS NOT NULL THEN 1 ELSE 0 END) as goal_met'),
            ])
            ->map(function ($r) {
                $sent = (int) $r->sent;
                $rate = fn ($n) => $sent ? round($n / $sent * 100, 1) : null;
                return [
                    'automation_id' => (int) $r->automation_id,
                    'variant' => $r->variant,
                    'sent' => $sent,
                    'opened' => (int) $r->opened,
                    'clicked' => (int) $r->clicked,
                    'bounced' => (int) $r->bounced,
                    'unsubscribed' => (int) $r->unsubscribed,
                    'suppressed' => (int) $r->suppressed,
                    'failed' => (int) $r->failed,
                    'goal_met' => (int) $r->goal_met,
                    'open_rate' => $rate((int) $r->opened),
                    'click_rate' => $rate((int) $r->clicked),
                    'goal_rate' => $rate((int) $r->goal_met),
                ];
            })
            ->groupBy('automation_id');
    }
}
