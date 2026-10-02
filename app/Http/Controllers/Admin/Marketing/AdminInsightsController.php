<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\Insights\InsightsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Throwable;

/**
 * Web & social insights: Google Search Console, Google Analytics 4 and the
 * Facebook Page (docs/marketing.md#web--social-insights).
 */
class AdminInsightsController extends Controller
{
    /** GET admin/marketing/insights/{search|analytics|facebook}?from=&to=&refresh=1 */
    public function source(Request $request, string $source)
    {
        $data = $this->service($request)->source($source, $request->boolean('refresh'));

        return Response::apiSuccess("Insights: $source", $data);
    }

    /** GET admin/marketing/insights/summary?from=&to= - call after the sources have loaded (reads cache only). */
    public function summary(Request $request)
    {
        return Response::apiSuccess('Insights summary', $this->service($request)->summary());
    }

    /** POST admin/marketing/insights/brief {from, to, refresh} */
    public function brief(Request $request)
    {
        try {
            return Response::apiSuccess('AI marketing brief', $this->service($request)->brief($request->boolean('refresh')));
        } catch (Throwable $e) {
            report($e);

            return Response::apiError('Could not generate the brief: '.$e->getMessage(), null, 502);
        }
    }

    private function service(Request $request): InsightsService
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);
        $to = CarbonImmutable::parse($request->input('to', CarbonImmutable::today()->toDateString()));
        $from = CarbonImmutable::parse($request->input('from', $to->subDays(27)->toDateString()));
        if ($from->diffInDays($to) > 364) {
            $from = $to->subDays(364);
        }

        return new InsightsService($from, $to);
    }
}
