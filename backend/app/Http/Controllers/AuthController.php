<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use App\Models\User;

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


}
