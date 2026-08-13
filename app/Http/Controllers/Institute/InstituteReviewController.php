<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Resources\Institute\InstituteReviewResource;
use App\Models\InstituteReview;
use App\Models\User;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class InstituteReviewController extends Controller
{
    use PaginatorTrait;

    public function index(Request $request, User $institute)
    {
        $reviews = InstituteReview::where('institute_id', $institute->id)
            ->where('is_published', true)
            ->with('student:id,name')
            ->latest()
            ->paginate($request->get('per_page', 10));

        $summary = [
            'average_rating' => round((float) InstituteReview::where('institute_id', $institute->id)
                ->where('is_published', true)
                ->avg('rating'), 1),
            'reviews_count' => InstituteReview::where('institute_id', $institute->id)
                ->where('is_published', true)
                ->count(),
        ];

        $this->setupPagination($reviews, fn ($items) => InstituteReviewResource::collection($items), $summary);

        return Response::apiSuccess('Institute reviews', $this->data);
    }

    public function mine()
    {
        $student = Auth::guard('institute_student')->user();

        $review = InstituteReview::where('institute_id', $student->institute_id)
            ->where('institute_student_id', $student->id)
            ->first();

        return Response::apiSuccess('My review', $review ? new InstituteReviewResource($review) : null);
    }

    public function store(Request $request)
    {
        $student = Auth::guard('institute_student')->user();

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = InstituteReview::updateOrCreate(
            [
                'institute_id' => $student->institute_id,
                'institute_student_id' => $student->id,
            ],
            $data
        );

        return Response::apiSuccess('Review submitted successfully', new InstituteReviewResource($review));
    }
}
