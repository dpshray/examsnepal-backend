<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\InstituteStudentUpdateProfileRequest;
use App\Http\Resources\Institute\InstituteStudentResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;

class InstituteStudentProfileController extends Controller
{
    public function show()
    {
        $student = Auth::guard('institute_student')->user();
        $student->load('institute');

        return Response::apiSuccess('Student profile', new InstituteStudentResource($student));
    }

    public function update(InstituteStudentUpdateProfileRequest $request)
    {
        $student = Auth::guard('institute_student')->user();
        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $student->update($data);
        $student->load('institute');

        return Response::apiSuccess('Profile updated successfully', new InstituteStudentResource($student));
    }
}
