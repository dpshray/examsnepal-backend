<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Classroom\ClassSyllabusTopicRequest;
use App\Http\Resources\Corporate\Classroom\ClassSyllabusTopicResource;
use App\Models\Corporate\Classroom;
use App\Models\Corporate\ClassSyllabusTopic;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class ClassSyllabusTopicController extends Controller
{
    public function index(Classroom $class)
    {
        $this->authorizeOwner($class);

        $topics = $class->syllabusTopics()->get();

        return Response::apiSuccess('Syllabus topics', ClassSyllabusTopicResource::collection($topics));
    }

    public function store(ClassSyllabusTopicRequest $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        $data = $request->validated();
        $data['order'] = ((int) $class->syllabusTopics()->max('order')) + 1;

        $topic = $class->syllabusTopics()->create($data);

        return Response::apiSuccess('Syllabus topic added successfully', new ClassSyllabusTopicResource($topic));
    }

    public function update(ClassSyllabusTopicRequest $request, Classroom $class, ClassSyllabusTopic $topic)
    {
        $this->authorizeOwner($class);
        $this->authorizeTopicBelongsToClass($class, $topic);

        $topic->update($request->validated());

        return Response::apiSuccess('Syllabus topic updated successfully', new ClassSyllabusTopicResource($topic));
    }

    public function destroy(Classroom $class, ClassSyllabusTopic $topic)
    {
        $this->authorizeOwner($class);
        $this->authorizeTopicBelongsToClass($class, $topic);

        $topic->delete();

        return Response::apiSuccess('Syllabus topic deleted successfully');
    }

    private function authorizeOwner(Classroom $class): void
    {
        if ($class->institute_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this class.');
        }
    }

    private function authorizeTopicBelongsToClass(Classroom $class, ClassSyllabusTopic $topic): void
    {
        if ($topic->class_id !== $class->id) {
            throw new AuthorizationException('This syllabus topic does not belong to this class.');
        }
    }
}
