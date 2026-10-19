<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionCollection;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Traits\PaginatorTrait;
use App\Services\Seo\McqTopics;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class FrontendController extends Controller
{
    //
    use PaginatorTrait;

    private const SITEMAP_CHUNK_SIZE = 20000;

    function dashboard()
    {
        $exam_count = Exam::count();
        $student_count = StudentProfile::count();
        return response()->json([
            'success' => true,
            'message' => 'Dashboard data retrieved successfully!',
            'data'    => [
                'exam_count' => $exam_count,
                'student_count' => $student_count,
            ],
        ], 200);
    }
    public function searchQuestions(Request $request)
    {
        $keyword = $request->query('keyword'); // Get the keyword from the query parameter
        if (! $keyword) {
            return response()->json(['message' => 'Keyword is required'], 400);
        }
        if (strlen($keyword) < 3) {
            return response()->json([
                'success' => false,
                'message' => 'Keyword must be at least 3 characters long.',
            ], 400);
        }
        $questions = Question::with('options')->where('question', 'LIKE', '%' . $keyword . '%')
            ->paginate();
        $data = $this->setupPagination($questions, QuestionCollection::class)->data;

        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No Results Found...',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Questions retrieved successfully!',
            'data'    => $data,
        ], 200);
    }

    /**
     * Exam categories (exam types) that have at least one question, with
     * counts - used as the default landing state for /find-mcq.
     */
    public function examCategories()
    {
        $categories = ExamType::query()
            ->select('id', 'name', 'slug')
            ->selectSub(
                Question::selectRaw('count(*)')->whereColumn('questions.exam_type_id', 'exam_types.id'),
                'question_count'
            )
            ->having('question_count', '>', 0)
            ->orderByDesc('question_count')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Exam categories retrieved successfully!',
            'data' => $categories,
        ], 200);
    }

    /**
     * Paginated question list for a single category (10 per page, see
     * Question::$perPage) - what /find-mcq/{categorySlug} loads.
     */
    public function questionsByCategory(Request $request, string $categorySlug)
    {
        $examType = ExamType::where('slug', $categorySlug)->first();

        if (! $examType) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }

        $questions = Question::with(['options', 'student_answers'])
            ->where('exam_type_id', $examType->id)
            ->orderBy('id')
            ->paginate();

        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No Results Found...',
            ], 404);
        }

        $data = $this->setupPagination($questions, QuestionCollection::class)->data;

        return response()->json([
            'success' => true,
            'message' => 'Questions retrieved successfully!',
            'data' => $data,
        ], 200);
    }

    /**
     * Public, unauthenticated single-question page - used by the SEO-facing
     * /mcq/{slug} route on the student frontend.
     */
    public function showQuestionBySlug(string $slug)
    {
        $question = Question::with(['options', 'comments' => fn ($query) => $query->latest()])
            ->where('slug', $slug)
            ->first();

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found.',
            ], 404);
        }

        $question->increment('view_count');

        return response()->json([
            'success' => true,
            'message' => 'Question retrieved successfully!',
            'data' => [
                'id' => $question->id,
                'slug' => $question->slug,
                'question' => $question->question,
                'explanation' => $question->explanation,
                'image_url' => $question->image_url,
                'explanation_image_url' => $question->explanation_image_url,
                'view_count' => $question->view_count,
                // Set by `php artisan mcq:score-seo`: thin/broken pages are
                // noindexed, exact duplicates canonicalize to one copy.
                // Topic page (category + subject) this question belongs to, for breadcrumbs.
                'topic' => ($topic = app(McqTopics::class)->forQuestion($question))
                    ? Arr::only($topic, ['category_slug', 'category_name', 'subject_slug', 'subject_name'])
                    : null,
                'seo' => [
                    'indexable' => $question->seo_noindex_reason === null,
                    'canonical_slug' => $question->canonical_question_id
                        ? Question::whereKey($question->canonical_question_id)->value('slug')
                        : null,
                ],
                'options' => $question->options->map(fn ($option) => [
                    'id' => $option->id,
                    'option' => $option->option,
                    'is_correct' => (bool) $option->value,
                    'image_url' => $option->image_url,
                ]),
                'comments' => $question->comments->map(fn ($comment) => [
                    'id' => $comment->id,
                    'name' => $comment->name,
                    'comment' => $comment->comment,
                    'created_at' => $comment->created_at->toIso8601String(),
                ]),
            ],
        ], 200);
    }

    /**
     * Every MCQ topic page (category + subject) - for the sitemap, category
     * pages and exam guides to link to.
     */
    public function mcqTopics(McqTopics $topics)
    {
        return response()->json([
            'success' => true,
            'data' => array_map(fn ($t) => Arr::except($t, ['exam_type_id', 'subject_id']), $topics->all()),
        ], 200);
    }

    /** GET free/mcq-topics/{categorySlug}/{subjectSlug}?page= - one topic page. */
    public function mcqTopic(Request $request, McqTopics $topics, string $categorySlug, string $subjectSlug)
    {
        $topic = $topics->find($categorySlug, $subjectSlug);
        if (! $topic) {
            return response()->json(['success' => false, 'message' => 'Topic not found.'], 404);
        }

        $page = max(1, (int) $request->query('page', 1));
        $questions = $topics->questions($topic, $page);
        if ($page > $questions->lastPage()) {
            return response()->json(['success' => false, 'message' => 'Page not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'topic' => Arr::except($topic, ['exam_type_id', 'subject_id']),
                'questions' => $questions->getCollection()->map(fn (Question $q) => [
                    'id' => $q->id,
                    'slug' => $q->slug,
                    'question' => $q->question,
                    'explanation' => $q->explanation,
                    'image_url' => $q->image_url,
                    'options' => $q->options->map(fn ($o) => ['option' => $o->option, 'is_correct' => (bool) $o->value]),
                ]),
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'related' => array_map(fn ($t) => Arr::only($t, ['subject_slug', 'subject_name', 'question_count']), $topics->related($topic)),
                'guides' => $topics->guides($topic),
            ],
        ], 200);
    }

    /**
     * Count a website page view. The /mcq/{slug} pages are now cached (ISR), so
     * showQuestionBySlug() only runs when a page regenerates - the site calls
     * this from the visitor's browser instead.
     */
    public function recordQuestionView(string $slug)
    {
        $updated = Question::where('slug', $slug)->increment('view_count');

        return response()->json(['success' => (bool) $updated], $updated ? 200 : 404);
    }

    /**
     * Post a public comment on a question - name + comment only, no auth.
     */
    public function storeQuestionComment(Request $request, string $slug)
    {
        $question = Question::where('slug', $slug)->first();

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $comment = $question->comments()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Comment posted successfully!',
            'data' => [
                'id' => $comment->id,
                'name' => $comment->name,
                'comment' => $comment->comment,
                'created_at' => $comment->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Fresh comment list for a question - the /mcq/{slug} page itself is
     * ISR-cached (up to an hour), so the client fetches this separately on
     * mount to avoid showing a stale comment count/list.
     */
    public function questionComments(string $slug)
    {
        $question = Question::where('slug', $slug)->first();

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $question->comments()->latest()->get()->map(fn ($comment) => [
                'id' => $comment->id,
                'name' => $comment->name,
                'comment' => $comment->comment,
                'created_at' => $comment->created_at->toIso8601String(),
            ]),
        ], 200);
    }

    /**
     * Chunking metadata for the /mcq sitemap - used by the frontend's
     * generateSitemaps() to know how many sitemap files to generate.
     */
    public function sitemapMcqMeta()
    {
        $total = Question::query()->inSitemap()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'chunk_size' => self::SITEMAP_CHUNK_SIZE,
                'chunks' => (int) ceil($total / self::SITEMAP_CHUNK_SIZE),
            ],
        ], 200);
    }

    /**
     * One page of slugs for the /mcq sitemap, 0-indexed.
     */
    public function sitemapMcqs(Request $request)
    {
        $page = max(0, (int) $request->query('page', 0));

        $questions = Question::query()->inSitemap()
            ->orderBy('id')
            ->skip($page * self::SITEMAP_CHUNK_SIZE)
            ->take(self::SITEMAP_CHUNK_SIZE)
            ->get(['slug']);

        return response()->json([
            'success' => true,
            'data' => $questions->map(fn ($question) => [
                'slug' => $question->slug,
            ]),
        ], 200);
    }
}
