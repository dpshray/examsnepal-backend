<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use App\Models\User;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
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
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="dhurbac66@gmail.com"),
     *             @OA\Property(property="password", type="string", format="password", example="Nepal123#")
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
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');
        // Debugging: Check if the student exists
        $student = StudentProfile::where('email', $credentials['email'])->first();

        if (!$student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

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

        return $this->respondWithToken($token);
        // return $this->respondWithToken($credentials);

    }


    public function me()
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




    protected function respondWithToken($token)
    {
        $student = Auth::guard('api')->user();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 1,
            'student' => $student,
        ]);
    }

    protected function respondRefreshWithToken($token)
    {
        $student = Auth::guard('api')->user();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'student' => $student,
        ]);
    }
}
