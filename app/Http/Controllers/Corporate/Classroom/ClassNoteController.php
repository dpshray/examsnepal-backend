<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Classroom\ClassNoteRequest;
use App\Http\Resources\Corporate\Classroom\ClassNoteCollection;
use App\Http\Resources\Corporate\Classroom\ClassNoteResource;
use App\Models\Corporate\Classroom;
use App\Models\Corporate\ClassNote;
use App\Traits\PaginatorTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ClassNoteController extends Controller
{
    use PaginatorTrait;

    public function index(Classroom $class)
    {
        $this->authorizeOwner($class);

        $pagination = $class->notes()->latest()->paginate(20);
        $data = $this->setupPagination($pagination, ClassNoteCollection::class)->data;

        return Response::apiSuccess('Class notes', $data);
    }

    public function store(ClassNoteRequest $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        $data = $request->validated();

        if ($data['type'] === 'pdf') {
            if (!$request->hasFile('file')) {
                return Response::apiError('Please upload a PDF file.');
            }
            $data['file_path'] = $request->file('file')->store('classes/notes', 'public');
            $data['video_url'] = null;
        } else {
            if (empty($data['video_url'])) {
                return Response::apiError('Please provide a video link.');
            }
            $data['file_path'] = null;
        }
        unset($data['file']);

        $note = $class->notes()->create($data);

        return Response::apiSuccess('Note added successfully', new ClassNoteResource($note));
    }

    public function update(ClassNoteRequest $request, Classroom $class, ClassNote $note)
    {
        $this->authorizeOwner($class);
        $this->authorizeNoteBelongsToClass($class, $note);

        $data = $request->validated();

        if ($data['type'] === 'pdf') {
            if ($request->hasFile('file')) {
                if ($note->file_path) {
                    Storage::disk('public')->delete($note->file_path);
                }
                $data['file_path'] = $request->file('file')->store('classes/notes', 'public');
            } elseif (!$note->file_path) {
                return Response::apiError('Please upload a PDF file.');
            } else {
                $data['file_path'] = $note->file_path;
            }
            $data['video_url'] = null;
        } else {
            if (empty($data['video_url'])) {
                return Response::apiError('Please provide a video link.');
            }
            if ($note->file_path) {
                Storage::disk('public')->delete($note->file_path);
            }
            $data['file_path'] = null;
        }
        unset($data['file']);

        $note->update($data);

        return Response::apiSuccess('Note updated successfully', new ClassNoteResource($note));
    }

    public function destroy(Classroom $class, ClassNote $note)
    {
        $this->authorizeOwner($class);
        $this->authorizeNoteBelongsToClass($class, $note);

        if ($note->file_path) {
            Storage::disk('public')->delete($note->file_path);
        }
        $note->delete();

        return Response::apiSuccess('Note deleted successfully');
    }

    private function authorizeOwner(Classroom $class): void
    {
        if ($class->institute_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this class.');
        }
    }

    private function authorizeNoteBelongsToClass(Classroom $class, ClassNote $note): void
    {
        if ($note->class_id !== $class->id) {
            throw new AuthorizationException('This note does not belong to this class.');
        }
    }
}
