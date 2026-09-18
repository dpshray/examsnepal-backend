<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Classroom\ClassAssignmentRequest;
use App\Http\Resources\Corporate\Classroom\ClassAssignmentResource;
use App\Models\Corporate\ClassAssignment;
use App\Models\Corporate\Classroom;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ClassAssignmentController extends Controller
{
    public function index(Classroom $class)
    {
        $this->authorizeOwner($class);

        $assignments = $class->assignments()->withCount('submissions')->latest()->get();

        return Response::apiSuccess('Class assignments', ClassAssignmentResource::collection($assignments));
    }

    public function store(ClassAssignmentRequest $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        $data = $request->validated();

        if (in_array($data['type'], ['pdf', 'image'], true)) {
            if (!$request->hasFile('file')) {
                return Response::apiError($data['type'] === 'pdf' ? 'Please upload a PDF file.' : 'Please upload an image.');
            }
            $data['file_path'] = $request->file('file')->store('classes/assignments', 'public');
            $data['content_text'] = null;
        } else {
            $data['file_path'] = null;
        }
        unset($data['file']);

        $assignment = $class->assignments()->create($data);

        return Response::apiSuccess('Assignment added successfully', new ClassAssignmentResource($assignment));
    }

    public function update(ClassAssignmentRequest $request, Classroom $class, ClassAssignment $assignment)
    {
        $this->authorizeOwner($class);
        $this->authorizeAssignmentBelongsToClass($class, $assignment);

        $data = $request->validated();

        if (in_array($data['type'], ['pdf', 'image'], true)) {
            if ($request->hasFile('file')) {
                if ($assignment->file_path) {
                    Storage::disk('public')->delete($assignment->file_path);
                }
                $data['file_path'] = $request->file('file')->store('classes/assignments', 'public');
            } elseif (!$assignment->file_path || $assignment->type !== $data['type']) {
                return Response::apiError($data['type'] === 'pdf' ? 'Please upload a PDF file.' : 'Please upload an image.');
            } else {
                $data['file_path'] = $assignment->file_path;
            }
            $data['content_text'] = null;
        } else {
            if ($assignment->file_path) {
                Storage::disk('public')->delete($assignment->file_path);
            }
            $data['file_path'] = null;
        }
        unset($data['file']);

        $assignment->update($data);

        return Response::apiSuccess('Assignment updated successfully', new ClassAssignmentResource($assignment));
    }

    public function destroy(Classroom $class, ClassAssignment $assignment)
    {
        $this->authorizeOwner($class);
        $this->authorizeAssignmentBelongsToClass($class, $assignment);

        if ($assignment->file_path) {
            Storage::disk('public')->delete($assignment->file_path);
        }
        $assignment->delete();

        return Response::apiSuccess('Assignment deleted successfully');
    }

    private function authorizeOwner(Classroom $class): void
    {
        if ($class->institute_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this class.');
        }
    }

    private function authorizeAssignmentBelongsToClass(Classroom $class, ClassAssignment $assignment): void
    {
        if ($assignment->class_id !== $class->id) {
            throw new AuthorizationException('This assignment does not belong to this class.');
        }
    }
}
