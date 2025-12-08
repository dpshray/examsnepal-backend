<?php

namespace App\Http\Controllers;

use App\Enums\RequestedFromEnum;
use App\Http\Requests\Teacher\Register\TeacherRegisterRequest;
use App\Http\Resources\StudentProfileResource;
use App\Http\Resources\StudentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use App\Models\User;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use hisorange\BrowserDetect\Facade as Browser;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{

    /**
     * @OA\Post(
     *     path="/register",
     *     summary="User Registration",
     *     description="Register a new user with username, password, fullname, email, and role. Triggers email verification.",
     *     operationId="registerUser",
     *     tags={"Users"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username", "password", "password_confirmation", "fullname", "email", "role"},
     *             @OA\Property(property="username", type="string", example="john_doe"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="fullname", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="johndoe@example.com"),
     *             @OA\Property(property="role", type="string", example="teacher")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="User registered successfully. Please verify your email.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="User already registered with this email",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="User already registered with this email.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function register(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'fullname' => 'required|string|max:255',
            'email' => 'required|string|email',
            'role' => 'required|string',
        ]);

        // If validation fails, return the error response
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if the email already exists in the database
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            return response()->json([
                'message' => 'User already registered with this email.',
            ], 409); // 409 Conflict status code
        }

        // Create a new user
        $user = User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'fullname' => $request->fullname,
            'role' => $request->role,
            'email' => $request->email,
        ]);

        // Trigger email verification event
        event(new Registered($user));

        return response()->json([
            'message' => 'User registered successfully. Please verify your email.',
        ], 201);
    }

    public function verifyEmail(Request $request)
    {
        $user = User::find($request->id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $loginUrl = env('FRONTEND_URL') . '/login';

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email already verified.',
                'login_url' => $loginUrl
            ], 200);
        }

        if ($request->hasValidSignature()) {
            $user->markEmailAsVerified();

            return response()->json([
                'message' => 'Email verified successfully.',
                'login_url' => $loginUrl
            ], 200);
        }

        return response()->json(['message' => 'Invalid or expired verification link.'], 400);
    }

    /**
     * @OA\Post(
     *     path="/student/login",
     *     summary="Login Student",
     *     description="Authenticate a student and return a JWT token",
     *     operationId="loginStudent",
     *     tags={"Student Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","fcm_token"},
     *             @OA\Property(property="email", type="string", format="email", example="dhurbac66@gmail.com"),
     *             @OA\Property(property="password", type="string", format="password", example="Nepal123#"),
     *             @OA\Property(property="fcm_token", type="string", example="Q8nR457CD")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful Login",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string", example="your_generated_jwt_token"),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Incorrect password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Student Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Student not found")
     *         )
     *     )
     * )
     */

    public function loginStudent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:student_profiles,email',
            'password' => 'required|string',
            "fcm_token" => 'nullable'
        ]);
        if ($validator->fails()) {
            $an_error = $validator->errors()->all();
            return Response::apiError($an_error[0] ?? 'Validation error occurred', null, 422);
        }

        $credentials = $request->only('email', 'password');
        // Debugging: Check if the student exists
        $student = StudentProfile::where('email', $credentials['email'])->first();
        $email_is_not_verified = !$student->hasVerifiedEmail();
        if ($email_is_not_verified) {
            return Response::apiError('Email is not verified.', null, 403);
        } elseif (empty($student->exam_type_id)) {
            return Response::apiError('Exam type not found.', null, 403);
        }
        // if (!$student) {
        //     return response()->json(['error' => 'Student not found'], 404);
        // }

        // Debugging: Check if the password is correct
        if (!Hash::check($credentials['password'], $student->password)) {
            return response()->json([
                'error' => 'Incorrect password',
            ], 401);
        }

        // Attempt to generate a token
        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $student->update(['fcm_token' => $request->fcm_token]);
        $data = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 1,
            'student' => new StudentProfileResource($student),
        ];
        return response()->json($data, 200);
        // return $this->respondWithToken($token);
        // // return $this->respondWithToken($credentials);

        // Validate the incoming request

    }


    /**
     * @OA\Post(
     *     path="/admin/login",
     *     summary="Login Admin",
     *     description="Authenticate a admin and return a JWT token",
     *     operationId="loginAdmin",
     *     tags={"Admin Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@examsnepal.com"),
     *             @OA\Property(property="password", type="string", format="password", example="Nepal123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful Login",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string", example="your_generated_jwt_token"),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Incorrect password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="user Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="user not found")
     *         )
     *     )
     * )
     */

    public function AdminLogin(Request $request)
    {
        // Validate the input fields
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        // Retrieve the user by email
        $admin = User::where('email', $credentials['email'])->first();

        if (!$admin) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Ensure the user is an admin
        Log::info($admin);
        // if ($admin->role !== 'admin') {
        //     return response()->json(['error' => 'Unauthorized: Not an admin user'], 403);
        // }

        // // Verify that the password is correct
        if ($admin && !$admin->isAdmin()) {
            return response()->json(['error' => 'Unauthorized: Not an teacher'], 403);
        }
        if (!Hash::check($credentials['password'], $admin->password)) {
            return response()->json(['error' => 'Incorrect password'], 401);
        }
        $token = JWTAuth::fromUser($admin);
        $data = [
            'user' => [
                'username' => $admin->username,
                'email' => $admin->email
            ],
            'token' => $token
        ];
        return Response::apiSuccess("welcome, {$admin->username}", $data);

        return $this->respondWithToken($token, 'users');
        // Attempt token generation using the admin guard
        // if (!$token = Auth::guard('users')->attempt($credentials)) {
        //     return response()->json(['error' => 'Unauthorized'], 401);
        // }
        // $token = JWTAuth::fromUser($admin);

        // return $this->respondWithToken($token);
        // return response()->json(['data' => $admin]);
    }

    /**
     * @OA\Post(
     *     path="/teacher/login",
     *     summary="Login Teachers",
     *     description="Authenticate a admin and return a JWT token",
     *     operationId="loginTeachers",
     *     tags={"Teacher Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="hariofhungi@gmail.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful Login",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="username", type="string", example="teacher"),
     *                     @OA\Property(property="email", type="string", example="info@examsnepal.com")
     *                 ),
     *                 @OA\Property(
     *                     property="token",
     *                     type="string",
     *                     example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="welcome, teacher")
     *         )
     *     )
     * )
     */

    public function teacherLogin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:users,email',
            'password' => 'required|string',
        ]);
        $credentials = $request->only('email', 'password');
        $user = User::where('email', $credentials['email'])->first();
        if ($user && !$user->isTeacher()) {
            return response()->json(['error' => 'Unauthorized: Not an teacher'], 403);
        }
        if (!Hash::check($credentials['password'], $user->password)) {
            return response()->json(['error' => 'Incorrect password'], 401);
        }
        $token = JWTAuth::fromUser($user);
        $data = [
            'user' => [
                'username' => $user->username,
                'email' => $user->email
            ],
            'token' => $token
        ];
        return Response::apiSuccess("welcome, {$user->username}", $data);

        return $this->respondWithToken($token, 'users');
    }



    public function me() #me = student
    {
        return response()->json(Auth::guard('api')->user());
    }

    /**
     * @OA\Post(
     *     path="/student/logout",
     *     summary="Logout Student",
     *     description="Invalidates the JWT token to log out the student.",
     *     tags={"Student Authentication"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully logged out")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Could not log out",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Could not log out")
     *         )
     *     )
     * )
     */

    public function logoutStudent()
    {
        try {
            // Invalidate the current JWT token
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            \Log::error('JWT Logout Error: ' . $e->getMessage());
            return response()->json(['error' => 'Could not log out'], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/student/refresh",
     *     summary="Refresh Student Token",
     *     description="Generates a new access token using the old (expired) token.",
     *     tags={"Student Authentication"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="New access token generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Could not refresh token",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Could not refresh token")
     *         )
     *     )
     * )
     */
    public function refreshStudent()
    {
        try {
            $newToken = Auth::guard('api')->refresh();
            return $this->respondRefreshWithToken($newToken);
        } catch (\Exception $e) {
            \Log::error('JWT Refresh Error: ' . $e->getMessage());
            return response()->json(['error' => 'Could not refresh token'], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/auth-student",
     *     summary="Get auth student info",
     *     description="Retrieve auth student info.",
     *     operationId="authStudent",
     *     tags={"Student Authentication"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Free quizzes retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="exam_name", type="string", example="Math Quiz"),
     *                         @OA\Property(property="status", type="string", example="free"),
     *                         @OA\Property(property="user_id", type="integer", example=12),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=12),
     *                             @OA\Property(property="fullname", type="string", example="John Doe")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=50)
     *             )
     *         )
     *     )
     * )
     */
    public function studentAuthResponse()
    {
        $student = Auth::guard('api')->user();
        $data = new StudentProfileResource($student);
        return Response::apiSuccess('Authenticated student info', $data);
    }

    protected function respondWithToken($token, $user)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 1,
            'student' => new StudentProfileResource($user),
        ]);
    }

    protected function respondRefreshWithToken($token)
    {
        $student = Auth::guard('api')->user();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'student' => new StudentProfileResource($student),
        ]);
    }
    function teacher_register(TeacherRegisterRequest $request)
    {
        $validated = $request->validated();

        $teacher = User::create([
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'fullname' => $validated['fullname'],
            'role' => 'teacher',
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        event(new Registered($teacher));

        return Response::apiSuccess('Teacher registered successfully. Please verify your email.', null, 201);
    }

    /**
     * @OA\get(
     *     path="/admin/manual-student-email-verify/{id}",
     *     summary="Manually verify email of a particular student using student profile id/",
     *     description="Manually verify email of a particular student using student profile id/",
     *     operationId="ManualStudentEmailVerifier",
     *     tags={"Admin Authentication"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of a student",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Student email verification response",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="Student email has been verified.")
     *         )
     *     )
     * )
     */
    function manualStudentEmailVerifier(Request $request, $student_profile_id) {
        $student_profile = StudentProfile::firstWhere('id', $student_profile_id);
        if (empty($student_profile)) {
            return Response::apiError('Student does not exists.');
        }
        $student_profile->update(['email_verified_at' => now()]);
        return Response::apiSuccess('Student email has been verified.');

    }

    /**
     * @OA\Post(
     *     path="/student/resend-email-verification",
     *     summary="Resend student email verification.",
     *     description="Resend student email verification.",
     *     operationId="ResendStudentEmailVerification",
     *     tags={"Student Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="hariofhungi@gmail.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Verification email sent response",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="A verification link has been sent to your email address.")
     *         )
     *     )
     * )
     */
    function resendStudentEmailVerification(Request $request) {
        $form_data = $request->validate([
            'email' => 'required|exists:student_profiles,email'
        ]);
        // return $requested_from;
        Log::info('resend verificationn link : '.Browser::platformFamily());
        try {
            DB::transaction(function () use($form_data){
                $requested_from = RequestedFromEnum::WEB->value;
                if (Browser::isAndroid() || Browser::isTablet()) {
                    $requested_from = RequestedFromEnum::ANDROID->value;
                }else if (Browser::platformFamily() === 'iOS') {
                    $requested_from = RequestedFromEnum::IOS->value;
                }
                $student_profile = StudentProfile::firstWhere('email', $form_data['email']);
                $student_profile->update(['requested_from' => $requested_from]);
                $student_profile->resendEmailVerificationLink();
            });
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return Response::apiError('Something went wrong while sending verification email.');
        }
        return Response::apiSuccess("A verification link has been sent to your email address.");
    }
}
