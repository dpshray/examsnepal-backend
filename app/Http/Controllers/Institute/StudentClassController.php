<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Resources\Institute\StudentClassResource;
use App\Models\Corporate\Classroom;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class StudentClassController extends Controller
{
    public function index()
    {
        $student = Auth::guard('institute_student')->user();

        $classes = Classroom::where('institute_id', $student->institute_id)
            ->withCount(['exams', 'notes', 'meetingLinks'])
            ->orderBy('name')
            ->get();

        $myStatuses = $student->classes()->pluck('class_students.status', 'classes.id');

        $classes->each(function (Classroom $class) use ($myStatuses) {
            $class->my_status = $myStatuses[$class->id] ?? null;
        });

        return Response::apiSuccess('Classes', StudentClassResource::collection($classes));
    }

    public function show(string $slug)
    {
        $student = Auth::guard('institute_student')->user();

        $class = Classroom::where('slug', $slug)
            ->where('institute_id', $student->institute_id)
            ->withCount(['exams', 'notes', 'meetingLinks'])
            ->firstOrFail();

        $pivot = $class->students()->where('institute_student_id', $student->id)->first();
        $class->my_status = $pivot?->pivot?->status;

        return Response::apiSuccess('Class', new StudentClassResource($class));
    }

    public function apply(string $slug)
    {
        $student = Auth::guard('institute_student')->user();

        $class = Classroom::where('slug', $slug)
            ->where('institute_id', $student->institute_id)
            ->firstOrFail();

        $existing = $class->students()->where('institute_student_id', $student->id)->first();
        if ($existing) {
            return Response::apiError('You have already applied to this class.');
        }

        $class->students()->attach($student->id, ['status' => 'pending']);

        return Response::apiSuccess('Application submitted. The institute will review it shortly.');
    }
}
