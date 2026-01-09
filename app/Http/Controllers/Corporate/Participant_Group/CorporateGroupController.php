<?php

namespace App\Http\Controllers\Corporate\Participant_Group;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Participant_Group\CorporateGroupRequest;
use App\Http\Resources\Corporate\Participant_Group\CorporateGroupCollection;
use App\Http\Resources\Corporate\Participant_Group\CorporateGroupResource;
use App\Models\Corporate\ParticipantGroup;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class CorporateGroupController extends Controller
{
    //
    use PaginatorTrait;
    function index(Request $request)
    {
        $per_page = $request->query('per_page', 12);
        $search = $request->query('search');
        $user = Auth::user();
        $pagination = ParticipantGroup::where('Corporate_id', $user->id)->withCount('participants')->when($search, function ($query, $search) {
            $query->where('group_name', 'like', '%' . $search . '%');
        })->paginate($per_page);
        $data = $this->setupPagination($pagination, CorporateGroupCollection::class)->data;
        return Response::apiSuccess("corporate group list", $data);
    }
    function show(ParticipantGroup $group)
    {
        $data = new CorporateGroupResource($group);
        return Response::apiSuccess("corporate group details", $data);
    }
    function store(CorporateGroupRequest $request)
    {
        $data = $request->validated();
        $user = Auth::user();
        $data['Corporate_id'] = $user->id;
        $group = ParticipantGroup::create($data);
        return Response::apiSuccess("corporate group created successfully");
    }
    function update(CorporateGroupRequest $request, ParticipantGroup $group)
    {
        $data = $request->validated();
        $group->update($data);
        return Response::apiSuccess("corporate group updated successfully");
    }
    function destroy(ParticipantGroup $group)
    {
        $group->delete();
        return Response::apiSuccess("corporate group deleted successfully");
    }
}
