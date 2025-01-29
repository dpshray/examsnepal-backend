<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\StudentProfile;

class StudentProfileController extends Controller
{
    public function register(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:student_profiles',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'exam_type' => 'nullable|string',
            
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create student profile
        $student = StudentProfile::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'exam_type' => $request->exam_type,
        ]);

        return response()->json([
            'message' => 'Student registered successfully.',
            'student' => $student
        ], 201);
    }
}
