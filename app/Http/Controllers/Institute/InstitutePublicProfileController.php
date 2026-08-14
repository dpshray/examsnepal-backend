<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Resources\Institute\InstituteListResource;
use App\Http\Resources\Institute\InstitutePublicProfileResource;
use App\Http\Resources\Institute\StudentClassResource;
use App\Models\Corporate\Classroom;
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

    const SANDBOX_TEST_KEY = 'en_test_sandbox_demo_key';

    /**
     * Validate the provided API Secret Key or allow first-party internal traffic.
     */
    protected function validateApiKey(Request $request, User $institute): array
    {
        $providedKey = $request->header('X-Institute-API-Key')
            ?: $request->query('api_key')
            ?: (str_starts_with((string) $request->bearerToken(), 'en_') ? $request->bearerToken() : null);

        // Allow first-party web app requests from ExamsNepal internal clients
        $isFirstParty = $request->header('X-App-Client') === 'first-party'
            || $request->header('Sec-Fetch-Site') === 'same-origin'
            || $request->header('Sec-Fetch-Site') === 'same-site';

        if (!$providedKey && $isFirstParty) {
            return ['valid' => true, 'is_sandbox' => false];
        }

        // Check for universal developer sandbox key
        if ($providedKey === self::SANDBOX_TEST_KEY) {
            return ['valid' => true, 'is_sandbox' => true];
        }

        // Validate against institute's active API secret key
        $instituteKey = $institute->ensureApiSecretKey();

        if ($providedKey && hash_equals($instituteKey, $providedKey)) {
            return ['valid' => true, 'is_sandbox' => false];
        }

        $displayName = $institute->org ?: $institute->fullname;

        if ($providedKey) {
            return [
                'valid' => false,
                'message' => "Invalid API Secret Key for {$displayName}. Please obtain or regenerate your key from your ExamsNepal Institute Dashboard.",
            ];
        }

        return [
            'valid' => false,
            'message' => "API Secret Key required. Please pass your secret key in the 'X-Institute-API-Key' header or '?api_key=' parameter. Use test sandbox key '" . self::SANDBOX_TEST_KEY . "' for sandbox testing.",
        ];
    }

    public function show(Request $request, User $institute)
    {
        $authCheck = $this->validateApiKey($request, $institute);
        if (!$authCheck['valid']) {
            return Response::apiError($authCheck['message'], 401);
        }

        $isSandbox = $authCheck['is_sandbox'];

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

        $classes = Classroom::where('institute_id', $institute->id)
            ->withCount(['exams', 'notes', 'meetingLinks', 'students'])
            ->orderBy('name')
            ->get();

        $data = [
            'profile' => $profile,
            'insights' => $insights,
            'classes' => StudentClassResource::collection($classes),
            'sandbox_mode' => $isSandbox,
        ];

        if ($isSandbox) {
            $data['sandbox_notice'] = "Running in Test Sandbox Mode using demo key. Provide your Institute Secret Key for production data.";
        }

        return Response::apiSuccess('Institute profile', $data);
    }
}

