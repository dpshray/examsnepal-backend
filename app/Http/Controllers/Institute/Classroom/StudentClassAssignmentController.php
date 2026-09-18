<?php

namespace App\Http\Controllers\Institute\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Resources\Corporate\Classroom\ClassAssignmentResource;
use App\Http\Resources\Corporate\Classroom\ClassAssignmentSubmissionResource;
use App\Models\Corporate\ClassAssignment;
use App\Models\Corporate\ClassAssignmentSubmission;
use App\Models\Corporate\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class StudentClassAssignmentController extends Controller
{
    public function index(string $slug)
    {
        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);

        $assignments = $class->assignments()->latest()->get();

        $mySubmissions = ClassAssignmentSubmission::where('institute_student_id', $student->id)
            ->whereIn('class_assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('class_assignment_id');

        $assignments->each(function (ClassAssignment $assignment) use ($mySubmissions) {
            $assignment->my_submission = $mySubmissions->get($assignment->id);
        });

        $data = $assignments->map(function (ClassAssignment $assignment) {
            $resource = (new ClassAssignmentResource($assignment))->toArray(request());
            $resource['my_submission'] = $assignment->my_submission
                ? (new ClassAssignmentSubmissionResource($assignment->my_submission))->toArray(request())
                : null;

            return $resource;
        });

        return Response::apiSuccess('Class assignments', $data);
    }

    public function submit(Request $request, string $slug, ClassAssignment $assignment)
    {
        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);

        abort_unless($assignment->class_id === $class->id, 404, 'This assignment is not part of this class.');

        $existing = ClassAssignmentSubmission::where('class_assignment_id', $assignment->id)
            ->where('institute_student_id', $student->id)
            ->first();

        if ($existing && $existing->graded_at) {
            return Response::apiError('This assignment has already been graded and can no longer be resubmitted.', null, 409);
        }

        $data = $request->validate([
            'type' => 'required|in:pdf,image,text',
            'content_text' => 'required_if:type,text|nullable|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:10240',
        ]);

        if (in_array($data['type'], ['pdf', 'image'], true)) {
            if (!$request->hasFile('file') && (!$existing || $existing->type !== $data['type'] || !$existing->file_path)) {
                return Response::apiError($data['type'] === 'pdf' ? 'Please upload a PDF file.' : 'Please upload an image.');
            }
            if ($request->hasFile('file')) {
                if ($existing?->file_path) {
                    Storage::disk('public')->delete($existing->file_path);
                }
                $data['file_path'] = $request->file('file')->store('classes/assignment-submissions', 'public');
            } else {
                $data['file_path'] = $existing->file_path;
            }
            $data['content_text'] = null;
        } else {
            if ($existing?->file_path) {
                Storage::disk('public')->delete($existing->file_path);
            }
            $data['file_path'] = null;
        }
        unset($data['file']);

        $submission = ClassAssignmentSubmission::updateOrCreate(
            ['class_assignment_id' => $assignment->id, 'institute_student_id' => $student->id],
            $data
        );

        return Response::apiSuccess('Assignment submitted successfully', new ClassAssignmentSubmissionResource($submission));
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
