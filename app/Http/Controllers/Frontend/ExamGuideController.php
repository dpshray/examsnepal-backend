<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ExamCategory;
use App\Models\ExamGuide;

class ExamGuideController extends Controller
{
    /**
     * Main exams hub (/exams/) - every category with a published-guide count.
     */
    public function categories()
    {
        $categories = ExamCategory::query()
            ->withCount(['guides as guide_count' => fn ($query) => $query->where('status', 'published')])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($category) => $category->guide_count > 0)
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Exam categories retrieved successfully!',
            'data' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'guide_count' => $category->guide_count,
            ]),
        ], 200);
    }

    /**
     * Category hub (/exams/{categorySlug}/) - category info + every
     * published guide in it.
     */
    public function category(string $categorySlug)
    {
        $category = ExamCategory::where('slug', $categorySlug)->first();

        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'Exam category not found.',
            ], 404);
        }

        $guides = $category->publishedGuides()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Exam category retrieved successfully!',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'guides' => $guides->map(fn ($guide) => [
                    'id' => $guide->id,
                    'name' => $guide->name,
                    'slug' => $guide->slug,
                    'meta_description' => $guide->meta_description,
                ]),
            ],
        ], 200);
    }

    /**
     * Individual exam page (/exams/{categorySlug}/{examSlug}/).
     */
    public function guide(string $categorySlug, string $examSlug)
    {
        $category = ExamCategory::where('slug', $categorySlug)->first();

        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'Exam category not found.',
            ], 404);
        }

        $guide = $category->publishedGuides()->where('slug', $examSlug)->first();

        if (! $guide) {
            return response()->json([
                'success' => false,
                'message' => 'Exam guide not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Exam guide retrieved successfully!',
            'data' => [
                'id' => $guide->id,
                'name' => $guide->name,
                'slug' => $guide->slug,
                'meta_title' => $guide->meta_title,
                'meta_description' => $guide->meta_description,
                'intro' => $guide->intro,
                'conducting_body' => $guide->conducting_body,
                'eligibility' => $guide->eligibility,
                'exam_pattern' => $guide->exam_pattern,
                'passing_marks' => $guide->passing_marks,
                'application_period' => $guide->application_period,
                'syllabus' => $guide->syllabus,
                'faqs' => $guide->faqs ?? [],
                'mock_test_url' => $guide->mock_test_url,
                'question_count_label' => $guide->question_count_label,
                'last_verified_at' => optional($guide->last_verified_at)->toDateString(),
                'category' => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
                'related_guides' => $guide->relatedGuides()->map(fn ($related) => [
                    'name' => $related->name,
                    'slug' => $related->slug,
                ]),
            ],
        ], 200);
    }
}
