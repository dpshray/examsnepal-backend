<?php

namespace App\Http\Controllers\Corporate;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\CorporateLoginRequest;
use App\Http\Requests\Corporate\Register\CorporateResisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class CorporateAuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/corporate/login",
     *     summary="Corporate Login",
     *     description="Login form for corporate user (Note: role_id = 5 is corporate).",
     *     operationId="corporateLogin",
     *     tags={"Corporate Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Login credentials",
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="corporate@example.com"),
     *             @OA\Property(property="password", type="string", example="Corporate@123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=197),
     *                     @OA\Property(property="username", type="string", example="corp"),
     *                     @OA\Property(property="fullname", type="string", example="CORPORATE"),
     *                     @OA\Property(property="email", type="string", example="corporate@example.com")
     *                 ),
     *                 @OA\Property(
     *                     property="token",
     *                     type="string",
     *                     example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="logged in successfull")
     *         )
     *     ),
     *     @OA\Response(response=403, description="User not found or invalid role"),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */

    public function login(CorporateLoginRequest $request)
    {

        $validated = $request->validated();

        $user = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where([
                ['email', $validated['email']],
                ['roles.name', RoleEnum::CORPORATE->value]
            ])
            ->first();
        if (empty($user)) {
            return Response::apiError('User not found or invalid role', 403);
        } elseif ($user->name != RoleEnum::CORPORATE->value) {
            return Response::apiError('Please login in as corporate', 500);
        }

        if (! $token = Auth::guard('users')->attempt($validated)) {
            return Response::apiError('Invalid Credentials');
        }
        $user = Auth::guard('users')->user();
        $user->loadMissing('role');
        $user = new UserResource($user);
        return Response::apiSuccess('logged in successfull', compact('user', 'token'));
    }
    /**
     * @OA\Post(
     *     path="/corporate/logout",
     *     summary="Logout Corporate",
     *     description="Invalidates the JWT token to log out the corporate user.",
     *     tags={"Corporate Authentication"},
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
    /**
     * @OA\Post(
     *     path="/corporate/register",
     *     summary="Register a new corporate user",
     *     description="Register a new corporate user with username, fullname, email, and password.",
     *     tags={"Corporate Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username", "fullname", "email", "password", "password_confirmation"},
     *             @OA\Property(property="username", type="string", example="corpuser"),
     *             @OA\Property(property="fullname", type="string", example="Corporate User"),
     *             @OA\Property(property="email", type="string", format="email", example="corpuser@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="phone", type="string", example="+1234567890")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Registration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="you have registered successfully")
     *         )
     *     )
     * )
     */

    public function register(CorporateResisterRequest $request)
    {
        $data = $request->validated();
        $data['role_id'] = RoleEnum::CORPORATE->value;
        $user = User::create([
            'username' => $data['username'],
            'fullname' => $data['fullname'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => 5,
        ]);
        // $user->loadMissing('role');
        // event(new Registered($user));
        return Response::apiSuccess('you have registered successfully');
    }
    /**
     * @OA\Post(
     *     path="/corporate/forgot-password",
     *     summary="Initiate password reset",
     *     description="Sends a password reset link to the corporate user's email.",
     *     tags={"Corporate Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="corpuser@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset link sent",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="forgot password link sent to your email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unable to send reset link",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="unable to send reset link")
     *         )
     *     )
     * )
     */

    function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);
        $status = Password::sendResetLink(
            $request->only('email')
        );
        $status = Password::ResetLinkSent;
        if (Password::RESET_LINK_SENT == 'passwords.sent') {
            return Response::apiSuccess('forgot password link sent to your email');
        }
        return Response::apiError('unable to send reset link', 500);
    }
    function paswordResetorFormHandler(Request $request, $token)
    {

        if ($request->isMethod('POST')) {
            $request->validate([
                // 'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|min:8|confirmed',
            ]);
            try {
                $credentials = $request->only('email', 'password', 'password_confirmation');
                $credentials['token'] = $token;
                $status = Password::reset(
                    $credentials,
                    function (User $user, string $password) use ($request) {
                        if (Hash::check($password, $user->password)) {
                            throw ValidationException::withMessages([
                                'password' => ['Please choose a different password.'],
                            ]);
                        }
                        $user->forceFill([
                            'password' => Hash::make($password)
                        ])->setRememberToken(Str::random(60));
                        $user->save();
                        event(new PasswordReset($user));
                    }
                );
            } catch (\Exception $e) {
                return Response::apiError($e->getMessage(), 422);
            }
            if ($status === Password::PasswordReset) {
                return Response::apiSuccess('Your password has been reset.');
            }
            $message = match ($status) {
                Password::INVALID_USER => 'Invalid user/email',
                Password::INVALID_TOKEN => 'Token is invalid/expired',
                default => 'Error occured. please try again'
            };
            return Response::apiError($message);
        }
        return view('auth.mail.password-reset', compact('token'));
    }
}
