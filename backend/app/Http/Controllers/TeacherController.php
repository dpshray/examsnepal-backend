<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /**
     * @OA\Get(
     *     path="/teachers",
     *     summary="Get list of teachers",
     *     description="Fetches all teachers with their details such as image, username, fullname, email, phone, and location",
     *     tags={"Teachers"},
     *     @OA\Response(
     *         response=200,
     *         description="Teacher List fetched successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Teacher List fetched successfully."
     *             ),
     *             @OA\Property(
     *                 property="students",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="image", type="string", example="image_url"),
     *                     @OA\Property(property="username", type="string", example="teacher_username"),
     *                     @OA\Property(property="fullname", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="teacher@example.com"),
     *                     @OA\Property(property="phone", type="string", example="+1234567890"),
     *                     @OA\Property(property="location", type="string", example="New York")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Invalid request")
     *         )
     *     )
     * )
     */
    public function index()
    {
        //
        $teachers = User::where('role', 'teacher')
            ->select('id', 'image', 'username', 'fullname', 'email', 'phone', 'location')
            ->get();
        return response()->json([
            'message' => 'Teacher List fetched successfully.',
            'students' => $teachers
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
