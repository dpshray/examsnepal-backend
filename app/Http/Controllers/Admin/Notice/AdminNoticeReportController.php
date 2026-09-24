<?php

namespace App\Http\Controllers\Admin\Notice;

use App\Http\Controllers\Controller;
use App\Models\NoticeReport;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdminNoticeReportController extends Controller
{
    use PaginatorTrait;

    public function index(Request $request)
    {
        $reports = NoticeReport::with('notice:id,slug,title_en,title_original,status')
            ->when(! $request->boolean('include_resolved'), fn ($q) => $q->where('is_resolved', false))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 20), 100));

        return Response::apiSuccess('Notice reports', $this->setupPagination($reports)->data);
    }

    public function resolve(NoticeReport $noticeReport)
    {
        $noticeReport->update(['is_resolved' => true]);

        return Response::apiSuccess('Report resolved', $noticeReport);
    }
}
