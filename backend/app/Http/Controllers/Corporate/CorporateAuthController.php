<?php

namespace App\Http\Controllers\Corporate;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\CorporateLoginRequest;
use App\Http\Requests\Corporate\Register\CorporateResisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CorporateAuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/corporate/login",
     *     summary="Login form for corporate user(Note: role 5 is for corporate)",
     *     description="Login form for corporate user",
     *     operationId="corporateLogin",
     *     tags={"CorporateLogin"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Corporate Exam data to be stored",
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="corporate@example.com"),
     *             @OA\Property(property="password", type="string", example="Corporate@123"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(
     *             property="data",
     *             type="object",
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=197),
     *                 @OA\Property(property="username", type="string", example="corp"),
     *                 @OA\Property(property="fullname", type="string", example="CORPORATE"),
     *                 @OA\Property(property="email", type="string", example="corporate@example.com")
     *             ),
     *             @OA\Property(
     *                 property="token",
     *                 type="string",
     *                 example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ..."
     *             )
     *         ),
     *         @OA\Property(property="message", type="string", example="logged in successfull")
     *     )
     * )
     * )
     */
    public function login(CorporateLoginRequest $request){

        $validated = $request->validated();

        $user = DB::table('users')
                                ->join('roles','users.role_id','=','roles.id')
                                ->where([
                                    ['email', $validated['email']],
                                    ['email_verified_at', '!=', null],
                                    ['roles.name', RoleEnum::CORPORATE->value]
                                ])
                                ->first();
        if (empty($user)) {
            return Response::apiError('email is not verified', 403);
        }elseif ($user->name != RoleEnum::CORPORATE->value) {
            return Response::apiError('Please login in as corporate', 500);
        }

        if (! $token = Auth::guard('users')->attempt($validated)) {
            return Response::apiError('Invalid Credentials');
        }
        $user = Auth::guard('users')->user();
        $user->loadMissing('role');
        $user = new UserResource($user);
        return Response::apiSuccess('logged in successfull', compact('user','token'));
    }
    public function logout(){
        JWTAuth::invalidate(JWTAuth::getToken());
        return Response::apiSuccess('logged out successfully');
    }
    public function register(CorporateResisterRequest $request)
    {
        $data = $request->validated();
        $data['role_id'] = RoleEnum::CORPORATE->value;
        $user = User::create($data);
        $user->loadMissing('role');
        event(new Registered($user));
        return Response::apiSuccess('email verification link sent to your email address');
    }
}
