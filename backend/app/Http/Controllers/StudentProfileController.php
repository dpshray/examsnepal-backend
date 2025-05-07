<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\ValidationException;

class StudentProfileController extends Controller
{

    /**
     * @OA\Post(
     *     path="/student/register", 
     *     summary="Register a new student",
     *     description="This endpoint allows you to register a new student by providing necessary details.",
     *     operationId="registerStudent",
     *     tags={"Student Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "exam_type_id"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="johndoe@example.com"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="exam_type_id", type="integer", example="1")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Student successfully registered",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Student registered successfully."),
     *             @OA\Property(property="student", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="johndoe@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="exam_type", type="string", example="mdms")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="name", type="array", @OA\Items(type="string", example="The name field is required.")),
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string", example="The email has already been taken.")),
     *                 @OA\Property(property="password", type="array", @OA\Items(type="string", example="The password confirmation does not match.")),
     *             )
     *         )
     *     )
     * )
     */
    public function register(Request $request)
    {
        $email_link_expires_at = StudentProfile::EMAIL_LINK_EXPIRES_AT;
        $student = StudentProfile::where('email', $request->email)->first();
        if ($student) {
            if ($student->email_verified_at == null) {
                $time = Carbon::createFromFormat('m/d/Y h:i:s a', $student->date);
                $minutesDiff = $time->diffInMinutes(now(), false);
                if ($minutesDiff > $email_link_expires_at) {
                    DB::table('password_reset_tokens')->where('email',$request->email)->delete();
                    $student->delete();
                } else {
                    return Response::apiError("A verification like has already been sent to your email.(or please wait for {$email_link_expires_at} minute(s))",null,400);
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:student_profiles,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'exam_type_id' => 'exists:exam_types,id',
        ]); 
        if ($validator->fails()) {
            $an_error = $validator->errors()->all();
            return Response::apiError($an_error[0] ?? 'Validation error occurred',null,422);
        }
        
        // Create student profile
        $student = StudentProfile::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'exam_type_id' => $request->exam_type_id,
            'date' => Carbon::now()->format('m/d/Y h:i:s a')
        ]);
        return Response::apiSuccess('An verification link has been sent to your email.');

    }


    /**
     * @OA\Get(
     *     path="/all-students",
     *     summary="Get all students",
     *     description="Retrieve a list of all students from the StudentProfile model.",
     *     operationId="getAllStudents",
     *     tags={"Students"},
     *     @OA\Response(
     *         response=200,
     *         description="Students list fetched successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Students List fetched successfully."),
     *             @OA\Property(
     *                 property="students",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="fullname", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", format="email", example="johndoe@example.com"),
     *                     @OA\Property(property="phone", type="string", example="+977-9800000000"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function allStudents()
    {
        $students = StudentProfile::select(['id', 'name', 'email', 'phone', 'created_at', 'updated_at'])->get();
        return response()->json([
            'message' => 'Students List fetched successfully.',
            'students' => $students
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/student-profile-fetcher",
     *     summary="Get Student Info",
     *     description="Retrieve a students information.",
     *     operationId="studentProfileFetcher",
     *     tags={"Student Authentication"},
     *     @OA\Response(
     *         response=200,
     *         description="Students information fetched successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Students List fetched successfully."),
     *             @OA\Property(
     *                 property="students",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="fullname", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", format="email", example="johndoe@example.com"),
     *                     @OA\Property(property="phone", type="string", example="+977-9800000000"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function getStudentProfile()
    {
        return Response::apiSuccess('User data fetch successfully', Auth::guard('api')->user());
    }


    /**
     * @OA\Put(
     *     path="/update-student-profile", 
     *     summary="Update Student Profile",
     *     description="This endpoint allows you to update student profile.",
     *     operationId="updateStudentProfile",
     *     tags={"Student Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "phone"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="johndoe@example.com"),
     *             @OA\Property(property="phone", type="integer", format="number", example="123456789"),
     *             @OA\Property(property="previous_password", type="string", format="password", example="password123previous"),
     *             @OA\Property(property="new_password", type="string", format="password", example="password123"),
     *             @OA\Property(property="new_password_confirmation", type="string", format="password", example="password123"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Student profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Student registered successfully."),
     *             @OA\Property(property="student", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="johndoe@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="exam_type", type="string", example="mdms")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="name", type="array", @OA\Items(type="string", example="The name field is required.")),
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string", example="The email has already been taken.")),
     *                 @OA\Property(property="password", type="array", @OA\Items(type="string", example="The password confirmation does not match.")),
     *             )
     *         )
     *     )
     * )
     */
    public function studentProfileUpdater(Request $request)
    {
        // return $request->all();
        $validatedData = $request->validate([
            "name" => 'required',
            // "email" => 'required',
            "phone" => "required",
            "previous_password" => 'nullable',
            "new_password" => 'nullable|confirmed',
            "new_password_confirmation" => 'nullable',
        ]);
        $student_id = Auth::guard('api')->id();
        $student = DB::table('student_profiles')->find($student_id);
        
        $data = [
            "name" => $validatedData["name"],
            // "email" => $validatedData["email"],
            "phone" => $validatedData['phone']
        ];
        if (array_key_exists('previous_password', $validatedData)) {
            if (!Hash::check($validatedData['previous_password'], $student->password)) {
                return Response::apiError('previous password does not match', null, 402);
            }else if (!array_key_exists('new_password', $validatedData) || !array_key_exists('new_password_confirmation', $validatedData)) {
                throw ValidationException::withMessages(['new_password_confirmation' => 'New Password/confirmation field is required.']);
            }else{
                $data['password'] = Hash::make($validatedData['new_password']);
            }
        }
        StudentProfile::find($student_id)->update($data);
        return Response::apiSuccess('User profile updated');
    }

    public function verifyStudentEmail($email) {
        DB::transaction(function () use($email){
            StudentProfile::firstWhere('email', $email)->markEmailAsVerified();
        });
        echo "Email has been verified.Please goto to login page to continue.";
    }
}