<?php

namespace App\Http\Controllers\Corporate\Participant_Group;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Participant\ParticipantRequest;
use App\Http\Resources\Corporate\Participant_Group\CorporateGroupParticipantCollection;
use App\Http\Resources\Corporate\Participant_Group\CorporateGroupParticipantResource;
use App\Models\Corporate\ParticipantGroup;
use App\Models\Participant;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CorporateParticipantGroupController extends Controller
{
    //
    use PaginatorTrait;
    function index(ParticipantGroup $group, Request $request)
    {
        $per_page = $request->query('per_page', 12);
        $search = $request->query('search');
        $user = Auth::user();
        $pagination = $group->participants()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->paginate($per_page);
        $data = $this->setupPagination($pagination, CorporateGroupParticipantCollection::class)->data;
        return Response::apiSuccess("corporate group participant list", $data);
    }
    function show(ParticipantGroup $group, $member)
    {
        $user = Auth::user();
        // Verify the group belongs to the authenticated user
        if ($group->Corporate_id !== $user->id) {
            return Response::apiError("Unauthorized access to this group", 403);
        }
        $participant = $group->participants()->where('participant_id', $member)->first();
        if (!$participant) {
            return Response::apiError("Participant not found in this group", 404);
        }
        return Response::apiSuccess("Participant details", new CorporateGroupParticipantResource($participant));
    }
    function store(ParticipantGroup $group, ParticipantRequest $request)
    {
        $data = $request->validated();
        $user = Auth::user();
        if ($group->Corporate_id !== $user->id) {
            return Response::apiError("Unauthorized access to this group", 403);
        }

        // Create or update participant
        $participant = Participant::updateOrCreate(
            [
                'email' => $data['email'],
                'corporate_id' => $user->id
            ],
            [
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'raw_password' => $data['password']
            ]
        );
        // Add participant to the group (avoid duplicates)
        $group->participants()->syncWithoutDetaching([$participant->id]);
        return Response::apiSuccess("Participant added to group successfully");
    }
    function update(ParticipantRequest $request, ParticipantGroup $group, $member)
    {
        $data = $request->validated();
        $user = Auth::user();
        $members = Participant::findOrFail($member);
        // Verify the group belongs to the authenticated user
        if ($group->Corporate_id !== $user->id) {
            return Response::apiError("Unauthorized access to this group", 403);
        }
        $updateData = [
            'name' => $data['name'] ?? $members->name,
            'phone' => $data['phone'] ?? $members->phone,
            'email' => $data['email'] ?? $members->email,
            'raw_password' => $data['password'] ?? $members->raw_password,
        ];

        // Only update password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $members->update($updateData);

        return Response::apiSuccess("Participant updated successfully");
    }
    function destroy(ParticipantGroup $group, $member)
    {
        $user = Auth::user();

        // Verify the group belongs to the authenticated user
        if ($group->Corporate_id !== $user->id) {
            return Response::apiError("Unauthorized access to this group", 403);
        }
        $members = Participant::findOrFail($member);
        // Remove participant from the group
        $group->participants()->detach($members->id);

        return Response::apiSuccess("Participant removed from group successfully");
    }
    function bulk_delete(Request $request, $group)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:participants,id',
        ]);
        $user = Auth::user();
        $group = ParticipantGroup::where('slug', $group)->firstOrFail();
        // Verify the group belongs to the authenticated user
        if ($group->Corporate_id !== $user->id) {
            return Response::apiError("Unauthorized access to this group", 403);
        }

        // Remove participants from the group
        $group->participants()->detach($data['ids']);

        return Response::apiSuccess("Participants removed from group successfully");
    }
    function bulk_upload(Request $request, $group)
    {
        $user = Auth::user();

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        $group = ParticipantGroup::where('slug', $group)->firstOrFail();
        DB::transaction(function () use ($rows, $group, $user) {

            foreach ($rows as $index => $row) {

                if ($index === 0) continue; // skip header

                $email = $row[2] ?? null;
                if (!$email) continue;


                //Find or Create Participant
                $participant = Participant::updateOrCreate(
                    [
                        'corporate_id' => $user->id,
                        'email'        => $email,
                    ],
                    [
                        'name'     => $row[0] ?? null,
                        'phone'    => $row[1] ?? null,
                        'password' => isset($row[3]) ? Hash::make($row[3]) : null,
                        'raw_password' => isset($row[3]) ? $row[3] : null,
                    ]
                );


                //Check if participant already added to group
                $group->participants()->syncWithoutDetaching([$participant->id]);
            }
        });

        return Response::apiSuccess('Participants uploaded and added to group successfully');
    }
}
