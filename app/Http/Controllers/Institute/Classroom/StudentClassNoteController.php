<?php

namespace App\Http\Controllers\Institute\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Resources\Corporate\Classroom\ClassNoteResource;
use App\Models\Corporate\Classroom;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class StudentClassNoteController extends Controller
{
    public function index(string $slug)
    {
        $class = $this->resolveEnrolledClass($slug);

        $notes = $class->notes()->latest()->get();

        return Response::apiSuccess('Class notes', ClassNoteResource::collection($notes));
    }

    private function resolveEnrolledClass(string $slug): Classroom
    {
        $student = Auth::guard('institute_student')->user();

        $class = Classroom::where('slug', $slug)
            ->where('institute_id', $student->institute_id)
            ->firstOrFail();

        $pivot = $class->students()->where('institute_student_id', $student->id)->first();
        abort_unless($pivot?->pivot?->status === 'enrolled', 403, 'You must be enrolled in this class to view this.');

        return $class;
    }
}
