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
use App\Enums\ExamTypeEnum;
use App\Enums\RequestedFromEnum;
use App\Http\Resources\Student\AllStudentResource;
use App\Http\Resources\StudentProfileResource;
use App\Traits\PaginatorTrait;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;
use hisorange\BrowserDetect\Facade as Browser;


class StudentProfileController extends Controller
{
    use PaginatorTrait;
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
            'exam_type_id' => 'required|exists:exam_types,id',
        ]);
        if ($validator->fails()) {
            $an_error = $validator->errors()->all();
            return Response::apiError($an_error[0] ?? 'Validation error occurred',null,422);
        }

        // Create student profile
        Log::info('normal register : '.Browser::platformFamily());
        $requested_from = RequestedFromEnum::WEB->value;
        if (Browser::isAndroid() || Browser::isTablet()) {
            $requested_from = RequestedFromEnum::ANDROID->value;
        } else if (Browser::platformFamily() === 'iOS') {
            $requested_from = RequestedFromEnum::IOS->value;
        }
        try {
            DB::transaction(function () use($request, $requested_from){            
                $student = StudentProfile::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => Hash::make($request->password),
                    'exam_type_id' => $request->exam_type_id,
                    'date' => Carbon::now()->format('m/d/Y h:i:s a'),
                    'requested_from' => $requested_from
                ]);
            });
        } catch (\Exception $e) {
            return Response::apiError('Unable to send the email right now. Please retry in a moment.');
        }
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
    public function allStudents(Request $request)
    {
        $email= $request->query('search');
        $exam_type_id= $request->query('exam_type');
        $limit = $request->input('limit', 10);
        $query = StudentProfile::with(['subscriptions','examType']);
        if ($email) {
            $query->where('email', 'like', '%' . $email . '%');
        }
        if ($exam_type_id) {
            $query->where('exam_type_id',$exam_type_id);
        }
        $students=$query->orderBy('id', 'DESC')->paginate($limit);
        $data = $this->setupPagination($students, fn($item) => AllStudentResource::collection($item));

        return response()->json([
            'message' => 'Students List fetched successfully.',
            'students' => $data->data
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

    public function verifyStudentEmail(Request $request, $email) {
        if (!$request->hasValidSignature()) {
            return Response::apiError('The link you used is invalid or has already expired.', null, 410);
        }
        $student_profile = null;
        try {
            DB::transaction(function () use($email, &$student_profile){
                $student_profile = StudentProfile::firstWhere('email', $email);
                if (!$student_profile) {
                    throw new \Exception('Student not found');
                }
                $student_profile->markEmailAsVerified();
            });
        } catch (\Exception $e) {
            Log::info('Error while verifying student email', ['error' => $e->getMessage()]);
            echo 'Error while verifying email';
        }
        // dd([$student_profile->requested_from, RequestedFromEnum::ANDROID, $student_profile->requested_from == RequestedFromEnum::ANDROID->value]);
        if ($student_profile->requested_from->value == RequestedFromEnum::ANDROID->value) {
            return redirect()->away("https://play.google.com/store/apps/details?id=com.dwork.examsnepal");
        }else if($student_profile->requested_from->value == RequestedFromEnum::IOS->value){
            return redirect()->away("https://play.google.com/store/apps/details?id=com.dwork.examsnepal");
        }else{
            return redirect()->away(env('EXAMSNEPAL_STUDENT_LOGIN_PAGE_URL'));
        }
    }

    /**
     * @OA\Post(
     *     path="/student-password-reset",
     *     summary="Password reset form(sending email)",
     *     description="This endpoint allows you to send message to student email which contains a token.",
     *     operationId="studentPasswordReset",
     *     tags={"Student Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="email", example="tester.123@example.com"),
     *
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
    public function sendPasswordResetMail(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:student_profiles,email',
        ]);
        if ($validator->fails()) {
            $an_error = $validator->errors()->all();
            return Response::apiError($an_error[0] ?? 'Validation error occurred', null, 422);
        }
        $already_sent = DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if ($already_sent) {
            $token_valid_until = StudentProfile::PASSWORD_RESET_TOKEN_VALID_UNTIL;
            $timestamp = $already_sent->created_at;
            $time = Carbon::parse($timestamp);
            $hasPassed = $time->diffInMinutes(now(), false) > $token_valid_until;
            if (!$hasPassed) {
                return Response::apiError('Mail has already been sent/please wait for '.$token_valid_until.' minute(s)');
            }
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        }
        StudentProfile::firstWhere('email', $request->email)->sendPasswordResetEmail();
        return Response::apiSuccess('A mail has been sent to your email');
    }

    /**
     * @OA\Post(
     *     path="/verify-password-reset-otp",
     *     summary="Send password reset token for verification",
     *     description="This endpoint checks token received from email which was sent for password reset token.",
     *     operationId="verifyPasswordResetOtp",
     *     tags={"Student Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token"},
     *             @OA\Property(property="token", type="string", example="C1EW5"),
     *
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
    public function verifyPasswordReseToken(Request $request) {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|exists:password_reset_tokens,token',
        ],[
            'token.exists' => 'Token does not match/exists'
        ]);
        if ($validator->fails()) {
            $an_error = $validator->errors()->all();
            return Response::apiError($an_error[0], null, 422);
        }
        $row = DB::table('password_reset_tokens')->where('token', $request->token)->first();
        $token = $row->token;
        $email = $row->email;
        $data = compact('token','email');
        return Response::apiSuccess('Token is verified',$data);
    }

    /**
     * @OA\Post(
     *     path="/handle-password-reset-form",
     *     summary="Handles password reset form",
     *     description="This endpoint handle password reset form.",
     *     operationId="handlePasswordResetForm",
     *     tags={"Student Authentication"},
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token","email","password","password_confirmation"},
     *             @OA\Property(property="token", type="string", example="EC1D0"),
     *             @OA\Property(property="email", type="string", example="tester@example.com"),
     *             @OA\Property(property="password", type="string", example="secret"),
     *             @OA\Property(property="password_confirmation", type="string", example="secret"),
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
    public function passwordResetor(Request $request){
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|exists:password_reset_tokens,token',
            'email' => 'required|string|exists:password_reset_tokens,email',
            'password' => 'required|string|confirmed',
            'password_confirmation' => 'required|string',
        ]);
        if ($validator->fails()) {
            $an_error = $validator->errors()->all();
            return Response::apiError($an_error[0], null, 422);
        }
        $row = DB::table('password_reset_tokens')->where([
            ['email', $request->email],
            ['token', $request->token]
        ]);
        if (empty($row->first())) {
            return Response::apiError('Requested token does not match with the email that you want the password to reset',null,400);
        }
        DB::transaction(function () use($row, $request){
            $row->delete();
            StudentProfile::where('email',$request->email)->update([
                'password' => Hash::make($request->password)
            ]);
        });

        return Response::apiSuccess('Password has been updated');
    }


    /**
     * @OA\DELETE(
     *     path="/student-account-removal/{student}",
     *     summary="Removes currently logged in student account permanently",
     *     description="Removed student account.",
     *     tags={"Student Authentication"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="ID of the student",
     *         @OA\Schema(type="integer", example=1)
     *     ),
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
    public function permanentStudentRemoveAccount(StudentProfile $student)
    {
        $logged_in_user = Auth::guard('api')->user();
        if ($logged_in_user->isNot($student)) {
            return Response::apiError('Cannot delete account.(user does not match)',null,403);
        }
        $logged_in_user->delete();
        return Response::apiSuccess('Your account has been removed permanently');
    }

    /**
     * @OA\Get(
     *     path="/student-exams-stats",
     *     summary="Get an student exams stats",
     *     description="Fetches a list of exams of a student with its statistics.",
     *     operationId="studentExamsStats",
     *     tags={"Student Authentication"},
     *     @OA\Response(
     *         response=200,
     *         description="List of exams retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="exam_name", type="string", example="Math Final"),
     *                 @OA\Property(property="exam_date", type="string", format="date", example="2025-06-10"),
     *                 @OA\Property(
     *                     property="organization",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="ABC University")
     *                 ),
     *                 @OA\Property(
     *                     property="examType",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Final Exam")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function studentProfileExamStats() {
        return Auth::guard('api')->user()->student_exams()->with('answers')->get();

    }

    /**
     * @OA\Get(
     *     path="/get-student-performance-data",
     *     summary="Get student performance report",
     *     description="Retrieve student performance report.",
     *     operationId="studentPerformanceReport",
     *     tags={"Students"},
     *     @OA\Response(
     *         response=200,
     *         description="Performance data for user",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="exams_given", type="integer", example=51),
     *                         @OA\Property(property="total_questions", type="integer", example=7340),
     *                         @OA\Property(property="correct_answers", type="integer", example=3553),
     *                         @OA\Property(property="exam_type", type="string", example="MOCK_TEST"),
     *                         @OA\Property(property="average_score", type="number", format="float", example=48.41)
     *                     )
     *                 ),
     *                 @OA\Property(property="total_average_score", type="number", format="float", example=49.01),
     *                 @OA\Property(property="total_exams", type="integer", example=2129)
     *             ),
     *             @OA\Property(property="message", type="string", example="performance data for user : Sandy")
     *         )
     *     )
     * )
    */
    public function studentPerformanceReport(){

        $student = Auth::guard('api')->user();

        $total_exams = DB::table('exams')->count();
        $data = DB::table('student_profiles as sp')
                ->join('student_exams as se', 'sp.id', '=', 'se.student_id')
                ->join('answersheets as a', 'se.id', '=', 'a.student_exam_id')
                ->join('exams as e', 'e.id', '=', 'se.exam_id')
                ->where('sp.id', $student->id)
                ->select(
                    // 'sp.id as sp_id',
                    'e.status',
                    DB::raw('COUNT(DISTINCT se.exam_id) as exams_given'),
                    DB::raw('COUNT(a.question_id) as total_questions'),
                    DB::raw('CAST(SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) AS UNSIGNED) as correct_answers')
                )
                ->groupBy('sp.id', 'e.status')
                ->get()
                ->map(function ($item) {
                    $item->exam_type = ExamTypeEnum::getKeyByValue((int) $item->status);
                    $item->exams_given = (int) $item->exams_given;
                    $item->total_questions = (int) $item->total_questions;
                    $item->correct_answers = (int) $item->correct_answers;
                    unset($item->status);

                    $item->average_score = (float) $item->total_questions > 0
                        ? round(($item->correct_answers / $item->total_questions) * 100, 2)
                        : 0;

                    return $item;
                });
        $totalCorrect = $data->sum('correct_answers');
        $totalQuestions = $data->sum('total_questions');

        $total_average_score = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 2) : 0;
        return Response::apiSuccess('performance data for user : '.$student->name, compact('data','total_average_score','total_exams'));
    }

    /**
     * @OA\Post(
     *     path="/student-google-login",
     *     summary="Google Login for student",
     *     description="Google Login for student",
     *     operationId="GoogleLogin",
     *     tags={"Google"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="google_token", type="string", example="41ht56d4h5td"),
     *             @OA\Property(property="fcm_token", type="string", example="m8skbtfgb7"),
     *             @OA\Property(property="exam_type_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=10),
     *                 @OA\Property(property="name", type="string", example="Lilly Lee"),
     *                 @OA\Property(property="dob", type="string", format="date", example="2082-01-25"),
     *                 @OA\Property(property="gender", type="boolean", example="FEMALE"),
     *                 @OA\Property(property="media", type="string", example="http://127.0.0.1:8000/storage/36/conversions/flowers-7382926_1920-thumbnail.jpg")
     *             ),
     *             @OA\Property(property="message", type="string", example="Infant information has been updated")
     *         )
     *     )
     * )
     */
    function googleLogin(Request $request){

        $request->validate([
            'google_token' => 'required',
            'fcm_token' => 'required',
            'exam_type_id' => 'required|exists:exam_types,id'
        ], [
            'google_token.required' => 'google token id is required',
            'fcm_token.required' => 'fcm token is required'
        ]);
        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($request->input('google_token'));
            $email = $googleUser->getEmail();
            $student = StudentProfile::firstWhere('email', $email);
            if (empty($student)) {
                $student = StudentProfile::create([
                    'email' => $googleUser->getEmail(),
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                    'fcm_token' => $request->fcm_token,
                    'date' => now(),
                    'exam_type_id' => $request->exam_type_id
                ]);
            } else {
                $student = tap($student, function ($student) use ($googleUser, $request) {
                    $student->update([
                        'name' => $googleUser->getName(),
                        'google_id' => $googleUser->getId(),
                        'email_verified_at' => now(),
                        'fcm_token' => $request->fcm_token,
                        'date' => now(),
                        'exam_type_id' => $request->exam_type_id
                    ]);
                });
            }
            $token = JWTAuth::fromUser($student);
            $data = [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 1,
                'student' => new StudentProfileResource($student),
            ];
            Log::info($data);
            return response()->json($data, 200);
        } catch (\Exception $e) {
            Log::error($e);
            return Response::apiError('An error occured.', 401);
        }
    }
}
