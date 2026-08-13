<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Classroom\ClassMeetingLinkRequest;
use App\Http\Resources\Corporate\Classroom\ClassMeetingLinkResource;
use App\Models\Corporate\Classroom;
use App\Models\Corporate\ClassMeetingLink;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class ClassMeetingLinkController extends Controller
{
    public function index(Classroom $class)
    {
        $this->authorizeOwner($class);

        $links = $class->meetingLinks()->orderBy('scheduled_at')->get();

        return Response::apiSuccess('Meeting links', ClassMeetingLinkResource::collection($links));
    }

    public function store(ClassMeetingLinkRequest $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        $link = $class->meetingLinks()->create($request->validated());

        return Response::apiSuccess('Meeting link added successfully', new ClassMeetingLinkResource($link));
    }

    public function update(ClassMeetingLinkRequest $request, Classroom $class, ClassMeetingLink $meetingLink)
    {
        $this->authorizeOwner($class);
        $this->authorizeLinkBelongsToClass($class, $meetingLink);

        $meetingLink->update($request->validated());

        return Response::apiSuccess('Meeting link updated successfully', new ClassMeetingLinkResource($meetingLink));
    }

    public function destroy(Classroom $class, ClassMeetingLink $meetingLink)
    {
        $this->authorizeOwner($class);
        $this->authorizeLinkBelongsToClass($class, $meetingLink);

        $meetingLink->delete();

        return Response::apiSuccess('Meeting link deleted successfully');
    }

    private function authorizeOwner(Classroom $class): void
    {
        if ($class->institute_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this class.');
        }
    }

    private function authorizeLinkBelongsToClass(Classroom $class, ClassMeetingLink $meetingLink): void
    {
        if ($meetingLink->class_id !== $class->id) {
            throw new AuthorizationException('This meeting link does not belong to this class.');
        }
    }
}
