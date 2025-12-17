<?php

namespace App\Http\Controllers\Student\ExamRegister;

use App\Http\Controllers\Controller;
use App\Models\Corporate\CorporateExam;
use App\Models\ExamAttempt;
use App\Models\Participant;
use App\Models\ParticipantExam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentExamRegisterController extends Controller
{
    //
    /**
     * Register a student for a public exam (no login required).
     *
     * @OA\Post(
     *     path="/exam/{exam}/register-public",
     *     summary="Register student for public exam",
     *     tags={"Corporate Exams Auth"},
     *
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="Corporate Exam ID",
     *         @OA\Schema(type="string", example="")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","phone"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="phone", type="string", example="9800000000")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Student registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="student register successfull"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="email", type="string"),
     *                     @OA\Property(property="phone", type="string"),
     *                     @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGci...")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Exam requires login"
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Attempt limit exceeded"
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function registerStudent_public_exam(Request $request, CorporateExam $exam)
    {
        if ($exam->exam_type !== 'public') {
            return Response::apiError('This exam requires login');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
        ]);

        // Attempt limit check
        $attemptCount = ExamAttempt::where('corporate_exam_id', $exam->id)
            ->where('email', $request->email)
            ->whereNull('participant_id') // Only count public exam attempts
            ->count();

        if ($exam->limit_attempts > 0 && $attemptCount >= $exam->limit_attempts) {
            return Response::apiError('Attempt limit exceeded');
        }
        $payload = [
            'sub' => 'public_' . $request->email, // subject identifier
            'exam_id' => $exam->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'exam_type' => 'public',
            'iat' => time(), // issued at
            'exp' => time() + (60 * 60 * 24), // expires in 24 hours
        ];
        // Generate token using JWT provider directly
        $token = JWTAuth::getJWTProvider()->encode($payload);
        $data[] = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'token' => $token,
        ];
        return Response::apiSuccess("student register successfull", $data);
    }
    /**
     * Login participant for a specific exam.
     *
     * @OA\Post(
     *     path="/exams/{exam}/private-login",
     *     summary="Participant login for exam",
     *     tags={"Corporate Exams Auth"},
     *
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="Corporate Exam ID",
     *         @OA\Schema(type="string", example="")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="participant@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Participant logged in successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Participant logged in successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="Participant Name"),
     *                     @OA\Property(property="email", type="string", example="participant@example.com")
     *                 ),
     *                 @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGci...")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials"
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Participant not assigned to this exam"
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */

    function login(Request $request, CorporateExam $exam)
    {
        $credentials = $request->validate([
            'email'    => 'required|email|exists:participants,email',
            'password' => 'required'
        ]);
        if (! $token = Auth::guard('participant')->attempt($credentials)) {
            return Response::apiError('Invalid credentials');
        }
        $participant = Auth::guard('participant')->user();
        $exists = ParticipantExam::where('corporate_exam_id', $exam->id)
            ->where('participant_id', $participant->id)
            ->exists();
        if (!$exists) {
            Auth::guard('participant')->logout();
            return Response::apiError('You are not assigned to this exam');
        }

        return Response::apiSuccess('Participant logged in successfully', [
            'user'  => Auth::guard('participant')->user(),
            'token' => $token,
        ]);
    }
    /**
     * Logout authenticated user (invalidate JWT token).
     *
     * @OA\Post(
     *     path="/auth/logout",
     *     summary="Logout user",
     *     tags={"Corporate Exams Auth"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully logged out")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Logout failed"
     *     )
     * )
     */

    public function logout()
    {
        try {
            // Invalidate the current JWT token
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            Log::error('JWT Logout Error: ' . $e->getMessage());
            return response()->json(['error' => 'Could not log out'], 500);
        }
    }
}
