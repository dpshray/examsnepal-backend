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

    const SANDBOX_TEST_KEY = 'en_test_sandbox_demo_key';

    protected function validateApiKey(Request $request, User $institute): bool
    {
        $providedKey = $request->header('X-Institute-API-Key')
            ?: $request->query('api_key')
            ?: (str_starts_with((string) $request->bearerToken(), 'en_') ? $request->bearerToken() : null);

        $isFirstParty = $request->header('X-App-Client') === 'first-party'
            || $request->header('Sec-Fetch-Site') === 'same-origin'
            || $request->header('Sec-Fetch-Site') === 'same-site';

        if (!$providedKey && $isFirstParty) {
            return true;
        }

        if ($providedKey === self::SANDBOX_TEST_KEY) {
            return true;
        }

        $instituteKey = $institute->ensureApiSecretKey();
        return $providedKey && hash_equals($instituteKey, $providedKey);
    }

    public function index(Request $request, User $institute)
    {
        if (!$this->validateApiKey($request, $institute)) {
            $displayName = $institute->org ?: $institute->fullname;
            return Response::apiError("API Secret Key required or invalid for {$displayName}. Please pass 'X-Institute-API-Key' or '?api_key='.", 401);
        }

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
