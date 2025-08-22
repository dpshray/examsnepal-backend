<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/organization",
     *     summary="Get all organizations",
     *     description="Retrieve the list of all organizations.",
     *     operationId="getAllOrganizations",
     *     tags={"Organizations"},
     *     @OA\Response(
     *         response=200,
     *         description="List of organizations retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve organizations")
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json(Organization::all());
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
    /**
     * @OA\Post(
     *     path="/organization",
     *     summary="Create a new organization",
     *     description="Create a new organization and store it in the database.",
     *     operationId="createOrganization",
     *     tags={"Organizations"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "is_active"},
     *             @OA\Property(property="name", type="string", example="My Organization"),
     *             @OA\Property(property="email", type="string", example="info@org.com"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="address", type="string", example="123 Organization St."),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organization created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Organization created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to create organization")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:organizations,email',
            'is_active' => 'required|boolean',
        ]);

        try {
            $organization = Organization::create($request->only('name', 'email', 'phone', 'address', 'is_active'));

            return response()->json([
                'message' => 'Organization created successfully',
                'data' => $organization
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create organization',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/organization/{id}",
     *     summary="Get an organization by ID",
     *     description="Retrieve the details of an organization by its ID.",
     *     operationId="getOrganizationById",
     *     tags={"Organizations"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Organization not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve organization")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        return response()->json(['data' => $organization]);
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
    /**
     * @OA\Put(
     *     path="/organization/{id}",
     *     summary="Update an organization",
     *     description="Update the details of an organization.",
     *     operationId="updateOrganization",
     *     tags={"Organizations"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "is_active"},
     *             @OA\Property(property="name", type="string", example="Updated Org"),
     *             @OA\Property(property="email", type="string", example="updated@org.com"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="address", type="string", example="123 Updated St."),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Organization updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Organization not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to update organization")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:organizations,email,' . $id,
            'is_active' => 'required|boolean',
        ]);

        try {
            $organization->update($request->only('name', 'email', 'phone', 'address', 'is_active'));

            return response()->json([
                'message' => 'Organization updated successfully',
                'data' => $organization
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update organization',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/organization/{id}",
     *     summary="Delete an organization",
     *     description="Delete an organization from the database.",
     *     operationId="deleteOrganization",
     *     tags={"Organizations"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the organization to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Organization deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Organization not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to delete organization")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        try {
            $organization->delete();

            return response()->json(['message' => 'Organization deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete organization',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
