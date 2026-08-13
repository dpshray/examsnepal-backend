<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Classroom\ClassStudentRequest;
use App\Http\Resources\Corporate\Classroom\ClassStudentCollection;
use App\Models\Corporate\Classroom;
use App\Models\InstituteStudent;
use App\Traits\PaginatorTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ClassStudentController extends Controller
{
    use PaginatorTrait;

    public function index(Request $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        $status = $request->query('status');
        $search = $request->query('search');
        $perPage = $request->query('per_page', 20);

        $pagination = $class->students()
            ->when($status, fn ($q) => $q->where('class_students.status', $status))
            ->when($search, fn ($q, $search) => $q->where('institute_students.name', 'like', "%{$search}%"))
            ->orderByDesc('class_students.created_at')
            ->paginate($perPage);

        $data = $this->setupPagination($pagination, ClassStudentCollection::class)->data;

        return Response::apiSuccess('Class students', $data);
    }

    public function store(ClassStudentRequest $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        $data = $request->validated();

        try {
            $student = InstituteStudent::firstOrCreate(
                ['institute_id' => $class->institute_id, 'email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'password' => Hash::make(Str::random(16)),
                ]
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return Response::apiError('That phone number is already used by another student at this institute.');
        }

        $class->students()->syncWithoutDetaching([$student->id => ['status' => 'enrolled']]);

        return Response::apiSuccess('Student added to class successfully');
    }

    public function update(Request $request, Classroom $class, InstituteStudent $student)
    {
        $this->authorizeOwner($class);

        $data = $request->validate([
            'status' => 'required|in:enrolled,rejected,pending',
        ]);

        $class->students()->updateExistingPivot($student->id, ['status' => $data['status']]);

        return Response::apiSuccess('Application updated successfully');
    }

    public function destroy(Classroom $class, InstituteStudent $student)
    {
        $this->authorizeOwner($class);

        $class->students()->detach($student->id);

        return Response::apiSuccess('Student removed from class successfully');
    }

    public function bulk_delete(Request $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:institute_students,id',
        ]);

        $class->students()->detach($data['ids']);

        return Response::apiSuccess('Students removed from class successfully');
    }

    public function bulk_upload(Request $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv'],
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return Response::apiError('Could not read this file. Please upload a valid Excel or CSV file.');
        }

        $rows = $spreadsheet->getActiveSheet()->toArray();
        $instituteId = $class->institute_id;

        $addedCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // skip header row: name, phone, email

            $email = $row[2] ?? null;
            if (!$email) continue;

            try {
                DB::transaction(function () use ($row, $email, $instituteId, $class) {
                    // firstOrCreate (not updateOrCreate): re-uploading a roster must never clobber a
                    // self-registered student's own password/profile, unlike the disposable Participant accounts.
                    $student = InstituteStudent::firstOrCreate(
                        ['institute_id' => $instituteId, 'email' => $email],
                        [
                            'name' => $row[0] ?? null,
                            'phone' => $row[1] ?? null,
                            'password' => Hash::make(Str::random(16)),
                        ]
                    );

                    $class->students()->syncWithoutDetaching([$student->id => ['status' => 'enrolled']]);
                });
                $addedCount++;
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'reason' => "Phone \"{$row[1]}\" is already used by another student in your list.",
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'reason' => 'Failed to save: ' . $e->getMessage(),
                ];
            }
        }

        return Response::apiSuccess('Bulk upload finished', [
            'added_count' => $addedCount,
            'failed_count' => count($errors),
            'errors' => $errors,
        ]);
    }

    private function authorizeOwner(Classroom $class): void
    {
        if ($class->institute_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this class.');
        }
    }
}
