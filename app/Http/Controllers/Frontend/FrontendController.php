<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionCollection;
use App\Models\Exam;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;

class FrontendController extends Controller
{
    //
    use PaginatorTrait;
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
}
