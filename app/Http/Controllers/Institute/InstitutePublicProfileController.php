<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Resources\Institute\InstituteListResource;
use App\Http\Resources\Institute\InstitutePublicProfileResource;
use App\Models\Corporate\CorporateExam;
use App\Models\InstituteReview;
use App\Models\InstituteStudent;
use App\Models\User;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class InstitutePublicProfileController extends Controller
{
    use PaginatorTrait;

    public function index(Request $request)
    {
        $search = $request->query('search');
        $perPage = $request->query('per_page', 12);

        $institutes = User::whereHas('role', fn ($q) => $q->where('name', 'corporate'))
            ->whereNotNull('slug')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                    ->orWhere('org', 'like', "%{$search}%");
            }))
            ->withCount(['instituteStudents as students_count'])
            ->withAvg(['publishedInstituteReviews as average_rating'], 'rating')
            ->orderBy('fullname')
            ->paginate($perPage);

        $data = $this->setupPagination($institutes, fn ($items) => InstituteListResource::collection($items))->data;

        return Response::apiSuccess('Institutes', $data);
    }

    public function show(User $institute)
    {
        $profile = new InstitutePublicProfileResource($institute);

        $publishedReviews = InstituteReview::where('institute_id', $institute->id)
            ->where('is_published', true);

        $insights = [
            'published_exams_count' => CorporateExam::where('corporate_id', $institute->id)
                ->where('is_published', true)
                ->count(),
            'students_count' => InstituteStudent::where('institute_id', $institute->id)->count(),
            'average_rating' => round((float) (clone $publishedReviews)->avg('rating'), 1),
            'reviews_count' => (clone $publishedReviews)->count(),
        ];

        return Response::apiSuccess('Institute profile', compact('profile', 'insights'));
    }
}
