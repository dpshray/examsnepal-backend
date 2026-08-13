<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\InstituteStudentLoginRequest;
use App\Http\Requests\Institute\InstituteStudentRegisterRequest;
use App\Http\Resources\Institute\InstituteStudentResource;
use App\Models\InstituteStudent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class InstituteStudentAuthController extends Controller
{
    public function register(InstituteStudentRegisterRequest $request, User $institute)
    {
        $data = $request->validated();

        $student = InstituteStudent::create([
            'institute_id' => $institute->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);
        $student->load('institute');

        $token = JWTAuth::fromUser($student);
        $student = new InstituteStudentResource($student);

        return Response::apiSuccess('you have registered successfully', compact('student', 'token'));
    }

    public function login(InstituteStudentLoginRequest $request, User $institute)
    {
        $credentials = $request->validated();
        $credentials['institute_id'] = $institute->id;

        if (! $token = Auth::guard('institute_student')->attempt($credentials)) {
            return Response::apiError('Invalid Credentials', null, 401);
        }

        $student = Auth::guard('institute_student')->user();
        $student->load('institute');
        $student = new InstituteStudentResource($student);

        return Response::apiSuccess('logged in successfully', compact('student', 'token'));
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return Response::apiSuccess('Successfully logged out');
        } catch (\Exception $e) {
            Log::error('JWT Logout Error: ' . $e->getMessage());
            return Response::apiError('Could not log out', null, 500);
        }
    }
}
