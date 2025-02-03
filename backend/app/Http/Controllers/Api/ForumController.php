<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ForumQuestion;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ForumController extends Controller {
    // Existing method to fetch questions
    public function fetchQuestions() {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Fetch the student profile using the logged-in user's id
        $studentProfile = StudentProfile::find($user->id);

        if (!$studentProfile) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        // Fetch questions where the stream matches the student's exam_type
        $questions = ForumQuestion::where('stream', $studentProfile->exam_type)
            ->where('forum_questions.deleted','0') // Only fetch non-deleted questions
            ->with(['studentProfile:id,name,email', 'answers.studentProfile:id,name,email'])
            ->withCount('answers')
            ->get();

        return response()->json($questions);
    }

    // Method to add a question
    public function addQuestion(Request $request) {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Fetch the student profile using the logged-in user's id
        $studentProfile = StudentProfile::find($user->id);

        if (!$studentProfile) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create the question
        $question = ForumQuestion::create([
            'user_id' => $studentProfile->id, // Use the id from student_profiles
            'question' => $request->input('question'),
            'stream' => $studentProfile->exam_type, // Use the exam_type from student_profiles as the stream
            'deleted' => '0', // Ensure deleted_at is null for new questions
        ]);

        return response()->json([
            'message' => 'Question added successfully',
            'question' => $question,
        ], 201);
    }
}