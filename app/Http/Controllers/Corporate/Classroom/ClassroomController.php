<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Classroom\ClassroomRequest;
use App\Http\Resources\Corporate\Classroom\ClassCollection;
use App\Http\Resources\Corporate\Classroom\ClassResource;
use App\Models\Corporate\Classroom;
use App\Traits\PaginatorTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class ClassroomController extends Controller
{
    use PaginatorTrait;

    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 12);
        $search = $request->query('search');
        $user = Auth::user();

        $pagination = Classroom::where('institute_id', $user->id)
            ->withCount([
                'notes',
                'exams',
                'meetingLinks',
                'students as enrolled_count' => fn ($q) => $q->where('status', 'enrolled'),
                'students as pending_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->when($search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->latest()
            ->paginate($perPage);

        $data = $this->setupPagination($pagination, ClassCollection::class)->data;

        return Response::apiSuccess('Class list', $data);
    }

    public function show(Classroom $class)
    {
        $this->authorizeOwner($class);

        $class->loadCount([
            'notes',
            'exams',
            'meetingLinks',
            'students as enrolled_count' => fn ($q) => $q->where('status', 'enrolled'),
            'students as pending_count' => fn ($q) => $q->where('status', 'pending'),
        ]);

        return Response::apiSuccess('Class details', new ClassResource($class));
    }

    public function store(ClassroomRequest $request)
    {
        $data = $request->validated();
        $data['institute_id'] = Auth::user()->id;

        $class = Classroom::create($data);

        return Response::apiSuccess('Class created successfully', new ClassResource($class));
    }

    public function update(ClassroomRequest $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        $class->update($request->validated());

        return Response::apiSuccess('Class updated successfully', new ClassResource($class));
    }

    public function destroy(Classroom $class)
    {
        $this->authorizeOwner($class);

        $class->delete();

        return Response::apiSuccess('Class deleted successfully');
    }

    private function authorizeOwner(Classroom $class): void
    {
        if ($class->institute_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this class.');
        }
    }
}
